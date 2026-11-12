#!/usr/bin/env bash
# scripts/deepagents-run.sh
#
# Launch Deep Agents Code (dcode) in non-interactive mode for CI/CD pipelines.
#
# Usage:
#   bash scripts/deepagents-run.sh "Your task description"
#
# Optional environment variables:
#   DEEPAGENTS_MODEL               Override the default model (e.g. claude-3-5-sonnet)
#   DEEPAGENTS_CODE_SHELL_ALLOW_LIST  Comma-separated list of allowed shell commands
#                                  (default: recommended,git,castor,composer,php,pnpm)
#   DEEPAGENTS_MAX_TURNS           Maximum agent turns (default: 15)
#   DEEPAGENTS_TIMEOUT_SECONDS     Wall-clock timeout in seconds (default: 900)
#
# Interactive local use:
#   dcode

set -euo pipefail

if [[ $# -eq 0 ]]; then
  echo "Usage: bash scripts/deepagents-run.sh \"task description\"" >&2
  exit 2
fi

TASK="$*"

MODEL_ARGS=()
if [[ -n "${DEEPAGENTS_MODEL:-}" ]]; then
  MODEL_ARGS=(--model "${DEEPAGENTS_MODEL}")
fi

exec dcode \
  "${MODEL_ARGS[@]}" \
  --shell-allow-list "${DEEPAGENTS_CODE_SHELL_ALLOW_LIST:-recommended,git,castor,composer,php,pnpm}" \
  --max-turns "${DEEPAGENTS_MAX_TURNS:-15}" \
  --timeout "${DEEPAGENTS_TIMEOUT_SECONDS:-900}" \
  -n "${TASK}"
