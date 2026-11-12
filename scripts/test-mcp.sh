#!/usr/bin/env bash

set -euo pipefail

MCP_PROTOCOL_VERSION="${MCP_PROTOCOL_VERSION:-2025-06-18}"
MCP_ENDPOINT="${MCP_ENDPOINT:-https://metadatapp.test/mcp}"
MCP_CLIENT_NAME="${MCP_CLIENT_NAME:-metadatapp-mcp-smoke-test}"
MCP_CLIENT_VERSION="${MCP_CLIENT_VERSION:-0.1.0}"
MCP_INSECURE_TLS="${MCP_INSECURE_TLS:-0}"
MCP_CA_CERT="${MCP_CA_CERT:-}"
export MCP_PROTOCOL_VERSION
export MCP_CLIENT_NAME
export MCP_CLIENT_VERSION
TOOL_NAME="${1:-list_mice}"
TOOL_ARGUMENTS='{"species":"mouse"}'

if [ "${#}" -ge 2 ]; then
    TOOL_ARGUMENTS="$2"
fi

tmp_dir="$(mktemp -d)"
trap 'rm -rf "${tmp_dir}"' EXIT

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Missing required command: $1" >&2
        exit 1
    fi
}

require_env() {
    local name="$1"

    if [ -z "${!name:-}" ]; then
        echo "Missing required environment variable: ${name}" >&2
        exit 1
    fi
}

pretty_print_json() {
    python3 -m json.tool
}

validate_json_argument() {
    python3 -c 'import json, sys; json.loads(sys.argv[1])' "$1" >/dev/null
}

extract_access_token() {
    python3 -c 'import json, sys; print(json.load(sys.stdin)["access_token"])'
}

require_command curl
require_command python3
validate_json_argument "${TOOL_ARGUMENTS}"

curl_args=(-sS)
if [ "${MCP_INSECURE_TLS}" = "1" ]; then
    curl_args+=(-k)
fi
if [ -n "${MCP_CA_CERT}" ]; then
    curl_args+=(--cacert "${MCP_CA_CERT}")
fi

access_token="${MCP_ACCESS_TOKEN:-${MCP_BEARER_TOKEN:-}}"

if [ -z "${access_token}" ]; then
    require_env KEYCLOAK_TOKEN_URL
    require_env OIDC_CLIENT_ID
    require_env OIDC_CLIENT_SECRET
    require_env MCP_USERNAME
    require_env MCP_PASSWORD

    echo "Requesting OIDC token from ${KEYCLOAK_TOKEN_URL}" >&2

    access_token="$(
        curl "${curl_args[@]}" "${KEYCLOAK_TOKEN_URL}" \
            -H 'Content-Type: application/x-www-form-urlencoded' \
            --data-urlencode "client_id=${OIDC_CLIENT_ID}" \
            --data-urlencode "client_secret=${OIDC_CLIENT_SECRET}" \
            --data-urlencode 'grant_type=password' \
            --data-urlencode "username=${MCP_USERNAME}" \
            --data-urlencode "password=${MCP_PASSWORD}" \
        | extract_access_token
    )"
fi

initialize_headers_file="${tmp_dir}/initialize.headers"
initialize_body_file="${tmp_dir}/initialize.body"

initialize_payload="$(
    python3 - <<'PY'
import json
import os

print(json.dumps({
    "jsonrpc": "2.0",
    "id": "initialize-1",
    "method": "initialize",
    "params": {
        "protocolVersion": os.environ["MCP_PROTOCOL_VERSION"],
        "capabilities": {},
        "clientInfo": {
            "name": os.environ["MCP_CLIENT_NAME"],
            "version": os.environ["MCP_CLIENT_VERSION"],
        },
    },
}))
PY
)"

echo "Initializing MCP session at ${MCP_ENDPOINT}" >&2

curl "${curl_args[@]}" \
    -D "${initialize_headers_file}" \
    -o "${initialize_body_file}" \
    "${MCP_ENDPOINT}" \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json, text/event-stream' \
    -H "Mcp-Protocol-Version: ${MCP_PROTOCOL_VERSION}" \
    -H "Authorization: Bearer ${access_token}" \
    --data "${initialize_payload}" >/dev/null

session_id="$(
    python3 - <<'PY' "${initialize_headers_file}"
import sys

with open(sys.argv[1], encoding="utf-8") as handle:
    for line in handle:
        if line.lower().startswith("mcp-session-id:"):
            print(line.split(":", 1)[1].strip())
            break
PY
)"

if [ -z "${session_id}" ]; then
    echo "Failed to extract Mcp-Session-Id from initialize response headers." >&2
    echo "Initialize response body:" >&2
    cat "${initialize_body_file}" >&2
    exit 1
fi

echo "MCP session: ${session_id}" >&2

send_mcp_request() {
    local payload="$1"

    curl "${curl_args[@]}" "${MCP_ENDPOINT}" \
        -H 'Content-Type: application/json' \
        -H 'Accept: application/json, text/event-stream' \
        -H "Mcp-Protocol-Version: ${MCP_PROTOCOL_VERSION}" \
        -H "Authorization: Bearer ${access_token}" \
        -H "Mcp-Session-Id: ${session_id}" \
        --data "${payload}"
}

initialized_payload='{"jsonrpc":"2.0","method":"notifications/initialized","params":{}}'
send_mcp_request "${initialized_payload}" >/dev/null

echo >&2
echo "=== tools/list ===" >&2

tools_list_payload='{"jsonrpc":"2.0","id":"tools-list-1","method":"tools/list","params":{}}'
send_mcp_request "${tools_list_payload}" | pretty_print_json

echo >&2
echo "=== tools/call ${TOOL_NAME} ===" >&2

tool_call_payload="$(
    python3 - <<'PY' "${TOOL_NAME}" "${TOOL_ARGUMENTS}"
import json
import sys

print(json.dumps({
    "jsonrpc": "2.0",
    "id": "tool-call-1",
    "method": "tools/call",
    "params": {
        "name": sys.argv[1],
        "arguments": json.loads(sys.argv[2]),
    },
}))
PY
)"

send_mcp_request "${tool_call_payload}" | pretty_print_json
