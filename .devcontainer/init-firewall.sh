#!/bin/bash

set -euo pipefail

readonly ipset_name="allowed-domains"
readonly dnsmasq_conf="/etc/dnsmasq.d/agent-firewall.conf"
readonly resolv_backup="/etc/resolv.conf.devcontainer-backup"

host_gateway_ip="$(getent hosts host.docker.internal | awk '/^[0-9.]+/ { print $1; exit }')"

if [[ -z "${host_gateway_ip}" ]]; then
  echo "Unable to resolve host.docker.internal" >&2
  exit 1
fi

if [[ ! "${host_gateway_ip}" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
  echo "Resolved host gateway is not a valid IPv4 address: ${host_gateway_ip}" >&2
  exit 1
fi

IFS='.' read -r -a host_gateway_octets <<<"${host_gateway_ip}"
for octet in "${host_gateway_octets[@]}"; do
  if ((octet < 0 || octet > 255)); then
    echo "Resolved host gateway is not a valid IPv4 address: ${host_gateway_ip}" >&2
    exit 1
  fi
done

sudo sysctl -w net.ipv6.conf.all.disable_ipv6=1 >/dev/null
sudo sysctl -w net.ipv6.conf.default.disable_ipv6=1 >/dev/null
sudo sysctl -w net.ipv6.conf.lo.disable_ipv6=1 >/dev/null

mapfile -t upstream_nameservers < <(awk '/^nameserver / { print $2 }' /etc/resolv.conf)

if [[ ${#upstream_nameservers[@]} -eq 0 ]]; then
  upstream_nameservers=("1.1.1.1" "8.8.8.8")
fi

domains=(
  github.com
  githubusercontent.com
  githubcopilot.com
  anthropic.com
  npmjs.com
  npmjs.org
  packagist.org
  repo.packagist.org
  visualstudio.com
  vsassets.io
  sentry.io
  statsig.com
)

if [[ -n "${EXTRA_FIREWALL_DOMAINS:-}" ]]; then
  IFS=',' read -ra extra_domains <<<"${EXTRA_FIREWALL_DOMAINS}"
  for domain in "${extra_domains[@]}"; do
    trimmed_domain="$(echo "${domain}" | xargs)"
    if [[ -n "${trimmed_domain}" ]]; then
      domains+=("${trimmed_domain}")
    fi
  done
fi

domain_spec="/"
for domain in "${domains[@]}"; do
  domain_spec+="${domain}/"
done
domain_spec+="${ipset_name}"

sudo ipset create "${ipset_name}" hash:ip -exist
sudo ipset flush "${ipset_name}"

{
  echo "bind-interfaces"
  echo "listen-address=127.0.0.1"
  echo "cache-size=0"
  echo "no-resolv"
  echo "ipset=${domain_spec}"
  for nameserver in "${upstream_nameservers[@]}"; do
    echo "server=${nameserver}"
  done
} | sudo tee "${dnsmasq_conf}" >/dev/null

if [[ ! -f "${resolv_backup}" ]]; then
  sudo cp /etc/resolv.conf "${resolv_backup}"
fi

{
  echo "nameserver 127.0.0.1"
  grep -E '^(search|options)' "${resolv_backup}" || true
} | sudo tee /etc/resolv.conf >/dev/null

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl restart dnsmasq
else
  mapfile -t dnsmasq_pids < <(pgrep -x dnsmasq || true)
  if [[ ${#dnsmasq_pids[@]} -gt 0 ]]; then
    sudo kill "${dnsmasq_pids[@]}"
    for _ in $(seq 1 10); do
      if ! pgrep -x dnsmasq >/dev/null 2>&1; then
        break
      fi
      sleep 0.2
    done
  fi
  sudo dnsmasq --conf-file="${dnsmasq_conf}"
fi

sudo iptables -P OUTPUT ACCEPT
sudo iptables -F OUTPUT

sudo iptables -A OUTPUT -o lo -j ACCEPT
sudo iptables -A OUTPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
sudo iptables -A OUTPUT -d 127.0.0.1 -p tcp --dport 53 -j ACCEPT
sudo iptables -A OUTPUT -d 127.0.0.1 -p udp --dport 53 -j ACCEPT

for nameserver in "${upstream_nameservers[@]}"; do
  sudo iptables -A OUTPUT -d "${nameserver}" -p udp --dport 53 -j ACCEPT
  sudo iptables -A OUTPUT -d "${nameserver}" -p tcp --dport 53 -j ACCEPT
done

for cidr in 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16; do
  sudo iptables -A OUTPUT -d "${cidr}" -j ACCEPT
done

sudo iptables -A OUTPUT -d "${host_gateway_ip}" -j ACCEPT
sudo iptables -A OUTPUT -m set --match-set "${ipset_name}" dst -p tcp -m multiport --dports 80,443 -j ACCEPT
sudo iptables -A OUTPUT -m set --match-set "${ipset_name}" dst -p udp --dport 443 -j ACCEPT
sudo iptables -A OUTPUT -j REJECT
sudo iptables -P OUTPUT DROP
