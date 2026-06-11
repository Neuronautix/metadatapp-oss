# Dev Container Agent Adapter

Start with [`../AGENTS.md`](../AGENTS.md).
That file is the canonical source of truth for repo structure, commands, ownership, and agent-specific constraints.

## Local container notes

- The Dev Container wraps the existing Docker/Castor stack; it does not replace `infrastructure/docker/`.
- Start the project stack from inside the container with the normal repo commands (prefer `castor start`).
- The Claude Code bypass-permission settings are intentional only inside this sandboxed container plus firewall setup; do not copy them to an unrestricted host workspace.
- Use the repo Docker services for version-sensitive PHP work so backend commands still run against the project's PHP 8.4 environment.
- The outbound firewall allowlist is managed by `init-firewall.sh`.
- IPv6 is intentionally disabled for this sandbox so outbound traffic cannot bypass the IPv4 allowlist.
- If an agent needs additional network access, keep the allowlist minimal and update `init-firewall.sh` in the same change.
