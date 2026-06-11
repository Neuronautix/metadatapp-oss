---
activation: always_on
description: Core conventions and patterns for the Metadatapp project
---

# Metadatapp Workspace Conventions

This always-on rule is intentionally short.
Read `AGENTS.md` first for the repo map, commands, agent ownership, and current paths.

## Local Contract

- Treat `AGENTS.md` as the only canonical repo-wide guide.
- Keep rule files scoped to their activation area; do not duplicate the full repo structure here.
- Prefer `castor` for stack lifecycle, database operations, and QA.
- Keep all agent-facing references current: every path must exist and every command example must resolve in the current repo.
- If this file conflicts with `AGENTS.md`, update this file and follow `AGENTS.md`.
