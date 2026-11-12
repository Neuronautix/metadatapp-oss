#!/usr/bin/env bash
# validate-ro-crate.sh
#
# Validates the assembled RO-Crate using the `rocrate` CLI tool (ro-crate-py).
# Must be run from the repository root after assemble-ro-crate.py has produced
# artifact/ro-crate-metadata.json.
#
# Exit codes:
#   0 – validation passed
#   1 – validation failed or artifact directory is missing

set -euo pipefail

ARTIFACT_DIR="artifact"
METADATA_FILE="${ARTIFACT_DIR}/ro-crate-metadata.json"

# ── Pre-flight checks ──────────────────────────────────────────────────────

if [[ ! -d "${ARTIFACT_DIR}" ]]; then
  echo "ERROR: '${ARTIFACT_DIR}' directory not found." >&2
  echo "       Run fetch-mapp-entities.py and assemble-ro-crate.py first." >&2
  exit 1
fi

if [[ ! -f "${METADATA_FILE}" ]]; then
  echo "ERROR: '${METADATA_FILE}' not found." >&2
  echo "       Run assemble-ro-crate.py first." >&2
  exit 1
fi

# ── Check that rocrate CLI is available ───────────────────────────────────

if ! command -v rocrate &>/dev/null; then
  echo "ERROR: 'rocrate' command not found." >&2
  echo "       Install it with: pip install rocrate" >&2
  exit 1
fi

# ── Run validation ────────────────────────────────────────────────────────

echo "Validating RO-Crate in '${ARTIFACT_DIR}' …"
if rocrate validate "${ARTIFACT_DIR}"; then
  echo "RO-Crate validation passed."
else
  echo "ERROR: RO-Crate validation failed. See errors above." >&2
  exit 1
fi
