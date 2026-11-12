# CLAUDE.md

`AGENTS.md` is the canonical agent playbook for this repository. Read it before making any non-trivial change; if anything here ever conflicts, `AGENTS.md` wins.

## Where to look in `AGENTS.md`

- Repo map and stack overview — top of the file
- Common Castor / PHPUnit / pnpm / Playwright commands — "Commands That Exist Today"
- Backend conventions (entities, State Provider/Processor, tenant isolation) — "API and Symfony"
- Frontend conventions (`features/`, `apiFetch`, MSW) — "Frontend and Osoma"
- E2E rules (MSW disabled, no `waitForNetworkIdle`) — "Testing"
- Ownership scopes and invocable-agent files — "Ownership Scopes" and "AI agent execution modes"

Do not duplicate the repo map or command catalogue here — keep this file as a pointer only.
