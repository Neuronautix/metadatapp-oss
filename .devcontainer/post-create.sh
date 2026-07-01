#!/bin/bash

set -euo pipefail

sudo apt-get update
sudo apt-get install -y --no-install-recommends curl dnsmasq ipset iptables jq unzip software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update
sudo apt-get install -y --no-install-recommends php8.4-cli
if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
  sudo apt-get install -y --no-install-recommends nodejs
fi
# corepack ships with Node and manages pnpm (osoma) without a separate install step.
sudo corepack enable
if ! command -v docker >/dev/null 2>&1; then
  sudo apt-get install -y --no-install-recommends docker.io
  sudo apt-get install -y --no-install-recommends docker-compose-v2 || \
    sudo apt-get install -y --no-install-recommends docker-compose-plugin
fi
sudo rm -rf /var/lib/apt/lists/*

CASTOR_VERSION="1.3.0"
if ! command -v castor >/dev/null 2>&1 || ! castor --version 2>&1 | grep -qF "${CASTOR_VERSION}"; then
  mkdir -p "${HOME}/.local/bin"

  case "$(uname -m)" in
    x86_64|amd64)
      CASTOR_ASSET="castor.linux-amd64"
      CASTOR_SHA256="dbebbd5241a0b8ccfecd9f0328503d31fc2bc5a460505286c9813f7215386d8b"
      ;;
    aarch64|arm64)
      CASTOR_ASSET="castor.linux-arm64"
      CASTOR_SHA256="bef38212074bc495689a27b7ad1ef8e0b3f10286dbdc4f18becdd349e874b806"
      ;;
    *)
      echo "Unsupported architecture for Castor: $(uname -m)" >&2
      exit 1
      ;;
  esac

  castor_tmp="$(mktemp)"
  trap 'rm -f "${castor_tmp}"' EXIT
  curl -fsSL "https://github.com/jolicode/castor/releases/download/v${CASTOR_VERSION}/${CASTOR_ASSET}" -o "${castor_tmp}"
  printf '%s  %s\n' "${CASTOR_SHA256}" "${castor_tmp}" | sha256sum -c -
  install -m 0755 "${castor_tmp}" "${HOME}/.local/bin/castor"
  rm -f "${castor_tmp}"
  trap - EXIT
fi

git config --global --add safe.directory /workspaces/metadatapp

# Install uv (Python package manager) if not already present.
if ! command -v uv >/dev/null 2>&1; then
  curl -fsSL https://astral.sh/uv/install.sh | sh
fi

# Install Deep Agents Code (dcode) as a developer tool, not a project dependency.
# uv tool installs the executable into ~/.local/bin which is already on PATH.
# Version is pinned to the validated release; bump intentionally when upgrading.
DEEPCODE_VERSION="0.1.6"
if ! uv tool list 2>/dev/null | grep -qF "deepagents-code ${DEEPCODE_VERSION}"; then
  uv tool install --force "deepagents-code==${DEEPCODE_VERSION}"
fi

# Fix Docker socket permissions so castor commands work from inside the devcontainer.
sudo chmod 666 /var/run/docker.sock || true

# Auto-detect the real host project root.
# The Docker daemon resolves bind-mount paths on the HOST filesystem, not the devcontainer's.
# We find the host path by inspecting this container's own mounts via the Docker socket,
# then export HOST_PROJECT_ROOT so castor passes it as PROJECT_HOST_ROOT to docker compose.
HOST_ROOT=$(docker inspect "$(hostname)" 2>/dev/null \
    --format '{{range .Mounts}}{{if eq .Destination "/workspaces/metadatapp"}}{{.Source}}{{end}}{{end}}' || true)
if [ -n "$HOST_ROOT" ]; then
    printf '\nexport HOST_PROJECT_ROOT=%s\n' "$HOST_ROOT" | tee -a ~/.bashrc ~/.profile > /dev/null
    echo "HOST_PROJECT_ROOT set to: $HOST_ROOT"
else
    echo "Warning: could not auto-detect HOST_PROJECT_ROOT (Docker socket not accessible yet?)"
fi
