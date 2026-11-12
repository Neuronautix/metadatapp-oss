# Deep Agents Code — Metadatapp project instructions

This file is loaded by `dcode` at startup as project-level context.  It
supplements (and does not replace) the root `AGENTS.md`, which is the canonical
repository playbook.  Read `AGENTS.md` before making any changes.

## Project overview

Metadatapp is an API-first FAIR metadata platform for preclinical behavioural
research.  It records structured metadata about experiments, subjects, and
procedures and exposes them through a JSON-LD / Hydra API.

## Stack

| Layer | Technology |
|---|---|
| Backend | Symfony 7, API Platform 3 |
| Frontend | React Admin, Material UI (in `osoma/`) |
| Database | PostgreSQL 16 |
| Task runner | Castor (`castor <command>`) |
| Package managers | Composer (PHP), pnpm (JS) |

## Coding conventions

- Prefer minimal diffs; do not reformat unrelated code.
- Preserve existing API behaviour unless a breaking change is explicitly requested.
- PHP: strict types, PSR-12, Symfony coding standards.
- JS/TS: follow the conventions established in `osoma/`.
- Do not modify `.env` files, secrets, credentials, or production deployment
  configuration unless explicitly instructed.

## Semantic / metadata concerns

- API resources use JSON-LD context and Hydra descriptions.
- Maintain metadata interoperability: do not remove `@context`, `@type`, or
  `@id` from JSON-LD outputs.
- Validate serialization groups before adding new ones (avoid deep N+1 chains).

## Testing

- Backend unit/integration tests: `cd api && php bin/phpunit`
- Frontend unit tests: `cd osoma && corepack pnpm exec vitest run`
- Frontend build check: `cd osoma && corepack pnpm build`
- E2E tests: `cd e2e && pnpm exec playwright test`
- For API or semantic changes, add or update the relevant test.

## Completion checklist

At the end of every run, report:

1. Files changed, added, or removed.
2. Tests run and their outcomes.
3. Unresolved failures or known limitations.
4. Assumptions made where the task was ambiguous.
