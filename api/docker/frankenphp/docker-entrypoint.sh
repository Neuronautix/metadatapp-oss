#!/usr/bin/env sh
set -eu

if [ "${1:-}" = "frankenphp" ] && [ -f /app/bin/console ]; then
    mkdir -p /app/var/cache /app/var/log
fi

exec "$@"
