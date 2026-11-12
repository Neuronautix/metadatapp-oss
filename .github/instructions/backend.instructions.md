---
applyTo: api/**/*
---

# Backend (Symfony + API Platform) Instructions

Read `AGENTS.md` first, then use this file as a backend-only supplement.

## Keep

- Strict typing and current Symfony/API Platform patterns already present in `api/src/`.
- Entities and resources aligned with neighboring examples in `api/src/Entity/`.
- Custom API behavior in state providers/processors before reaching for controller logic.
- Connected Apps logic inside `api/src/ConnectedApps/`.
- Foundry-based tests in `api/tests/`.

## Verify

- Use the Castor QA commands listed in `AGENTS.md`.
- For one-off Symfony console commands without a Castor alias, use the documented `docker compose ... exec api ...` pattern from `AGENTS.md`.
