#!/usr/bin/env bash

# Script used in dev to generate a basic SSL cert

BASE=$(dirname $0)

CERTS_DIR=$BASE/certs

# Keep the directory inode stable for Docker Desktop bind mounts.
# Recreating the directory can leave stale /run/desktop/... mount paths.
mkdir -p "$CERTS_DIR"
find "$CERTS_DIR" -maxdepth 1 -type f -name '*.pem' -delete
touch "$CERTS_DIR/.gitkeep"

openssl req -x509 -sha256 -newkey rsa:4096 \
    -keyout "$CERTS_DIR/key.pem" \
    -out "$CERTS_DIR/cert.pem" \
    -days 3650 -nodes -config \
    "$BASE/openssl.cnf"
