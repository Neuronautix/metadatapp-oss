# Metadatapp Agent Guide

`AGENTS.md` is the canonical source of truth for AI contributors in this repository.
If another agent-facing file conflicts with this document, follow `AGENTS.md` and update the thinner companion file.

## Current Repo Map

```text
metadatapp/
├── api/                    Symfony + API Platform backend
├── osoma/                  Vite + React frontend
├── e2e/                    Playwright tests against live services
├── .devcontainer/          Dev Container + local agent sandbox config
├── infrastructure/docker/  Docker Compose stack definitions
├── .castor/                Castor task implementations
├── tools/                  QA tool dependencies
├── reports/                Investigation notes and ad hoc reports
└── .github/                CI/CD and Copilot-specific instruction adapters
```

## Start Here

Read these files before making non-trivial changes:

- `api/README.md` for backend setup and conventions
- `api/CONNECTED_APPS.md` for Connected Apps architecture
- `osoma/README.md` for frontend setup
- `e2e/README.md` for browser-test setup
- `.github/workflows/ci.yml` if you are changing automation or required checks

## Where To Work

| Task | Primary location | Notes |
| --- | --- | --- |
| API entities/resources | `api/src/Entity/` | Reuse patterns from `Experiment.php`, `Project.php`, `ConnectedApp.php` |
| Custom read/write behavior | `api/src/State/` | Prefer providers/processors over controller logic |
| Connected Apps logic | `api/src/ConnectedApps/` | Keep sync logic out of controllers |
| Backend tests | `api/tests/` | API tests use Foundry factories from `api/src/DataFixtures/Factory/` and `ResetDatabase` |
| Osoma product features | `osoma/src/features/` | Keep feature logic self-contained |
| Osoma shell/shared app code | `osoma/src/app/`, `osoma/src/components/`, `osoma/src/lib/` | Reuse existing providers, router, and utilities |
| Browser coverage | `e2e/tests/` | Tests run against live services with MSW disabled |
| Infra and runtime wiring | `infrastructure/docker/`, `.castor/`, `castor.php` | Keep commands and paths aligned with the actual stack |

## Working Rules

### Repo-wide

- Prefer `castor` for stack lifecycle, database tasks, and routine QA commands.
- Keep repo facts in this file once. Companion docs should point here instead of duplicating structure and command references.
- Keep the canonical AI foundation guidance here, and keep the governed vertical slice documented only in `api/README.md`.
- Every agent-facing path you mention must exist in the repo.
- Every command example must map to a real task, script, or file in the current tree.
- Never commit secrets or invent missing security documentation paths.

### API and Symfony

- Every PHP file starts with `declare(strict_types=1);`.
- Use `#[ApiResource]` plus Doctrine attributes on entities.
- UUID identifiers use Doctrine UUID columns plus `UuidGenerator`.
- Prefer `api/src/State/Provider/` and `api/src/State/Processor/` for custom API behavior.
- Keep Connected Apps logic in `api/src/ConnectedApps/`; commands are orchestration only.
- Reuse `AccountAwareInterface` and `UserAwareInterface` patterns where the resource is tenant- or user-scoped.

### Testing

- Do not instantiate entities manually in backend tests; use Foundry factories from `api/src/DataFixtures/Factory/`.
- API tests typically extend `ApiTestCase` and use `Factories` plus `ResetDatabase`.
- E2E tests must disable MSW with `localStorage.setItem('use_msw', 'false')`.
- Do not use `page.waitForNetworkIdle()` in Playwright; use explicit selectors or targeted waiters.

### Frontend and Osoma

- `osoma/` is the active frontend. Do not use or recreate `osomapp` or `pwa` paths.
- Keep feature code under `osoma/src/features/`; shared app bootstrapping belongs in `osoma/src/app/`.
- Prefer `@tanstack/react-query` with helpers from `osoma/src/lib/api.ts`.
- Respect existing auth and mode wiring such as `AuthGuard` and the MSW toggle behavior.
- Frontend code must not talk directly to external Connected Apps.

## Commands That Exist Today

### Stack and database

```bash
castor start
castor up
castor stop
castor logs
castor ps
castor migrate
castor fixture
castor cache-clear
castor db:client
```

### Backend QA

```bash
castor phpunit
castor qa:phpstan
castor qa:cs --dry-run
castor qa:agent-docs
castor qa:all
castor schema-validate
```

### Frontend and browser checks

```bash
castor qa:osoma:build

cd osoma
pnpm build
pnpm run test:integration
pnpm run openapi:types:dvc-proxy

cd ../e2e
npm test
npm run test:osoma
```

### One-off Symfony console commands

Use this only when there is no Castor alias:

```bash
docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console <command>'
```

Example:

```bash
docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console doctrine:migrations:diff'
```

## Project Knowledge Wiki

A compiled, persistent knowledge base lives in `doc/wiki/`. It synthesizes all evolution reports
into structured, interlinked pages that do not need to be re-derived from raw reports on each session.

**Entry point:** `doc/wiki/INDEX.md` — read this first when answering questions about the project.

### Wiki operations

| Operation | When | What to do |
|-----------|------|-----------|
| **Ingest** | After writing a new evolution report | Read the report → update relevant feature/area/decision pages → update `tech-debt.md` if new debt introduced → append to `LOG.md` |
| **Query** | When answering project questions | Read `INDEX.md` → read relevant pages → synthesize answer; file the answer as a new page if it produces a non-trivial synthesis |
| **Lint** | Periodically or when asked | Check for orphan pages, stale claims, unresolved debt, missing cross-references |

### What the wiki tracks

- `overview.md` — project synthesis and current capabilities
- `tech-debt.md` — all known debt, limitations, and resolution paths
- `features/` — FAIR checking, curation, Zefix, elabFTW, AI/MCP, metadata standards, sensors
- `decisions/` — architectural decisions extracted from evolution reports (ADR-lite)
- `areas/` — backend, frontend, CI conventions and recent changes

### What NOT to put in the wiki

Code patterns already in `CLAUDE.md` or `AGENTS.md`, git history, or debugging solutions.
The wiki is for synthesized knowledge not derivable from a quick file read.

---

## AI agent execution modes

- Local, sandboxed agent sessions live in `.devcontainer/` and wrap the existing Docker/Castor stack instead of replacing `infrastructure/docker/`.
- GitHub Copilot cloud-agent setup lives in `.github/workflows/copilot-setup-steps.yml`.
- Keep repo-wide agent guidance in this file; `.devcontainer/AGENTS.md` and GitHub adapters should stay thin and point back here for shared rules.

### Invocable Agents

Agent definition files live in `.github/agents/`. Each file declares a `name`, a `description`, allowed `tools`, and an optional `target` (`vscode` or `github-copilot`).

Use the smallest invocable agent that can complete the work. To save context, route by ownership scope instead of restating this guide in prompts.

| File | VS Code name | Roster role(s) covered | Use when |
|---|---|---|---|
| `planner.agent.md` | Planner | `repo-explorer` | Mapping files, producing implementation plans, risk analysis |
| `backend.agent.md` | Backend Agent | `api-backend-worker` | Symfony/API Platform backend work in `api/src/` and `api/tests/`, excluding Connected Apps |
| `connected-apps.agent.md` | Connected Apps Agent | `connected-apps-worker` | Zefix, elabFTW, sync, proxy, and external-service integration logic |
| `frontend-osoma.agent.md` | Frontend Osoma Agent | `osoma-worker` | React/TypeScript product work in `osoma/src/` |
| `contract-e2e.agent.md` | Contract E2E Agent | `contract-and-e2e-worker` | API/frontend payload boundaries and Playwright coverage |
| `security-tenant-reviewer.agent.md` | Security Tenant Reviewer | `security-reviewer` | Auth, tenant isolation, secrets, and external-boundary review |
| `agent-docs-maintainer.agent.md` | Agent Docs Maintainer | agent documentation maintenance | Keeping `AGENTS.md`, adapters, rules, workflows, and agent definitions current |
| `implementer.agent.md` | Implementer | fallback implementation across scoped worker roles | Executing an approved plan when no specialist agent fits cleanly |
| `reviewer.agent.md` | Reviewer | `security-reviewer` + general review | Verifying correctness, regressions, test gaps, security |
| `cloud-pr.agent.md` | Cloud PR Agent | Top-level orchestrator (all roles) | Branch-based work and PR generation on GitHub |

**Invoking agents in VS Code:** open Copilot Chat → type `@` → select the agent by name.

**Invoking the cloud agent on GitHub:** Copilot → "Start a task" or `@github-copilot` with a task description in a GitHub comment.

## Ownership Scopes

The scoped roles below are ownership labels, not separate invocable files unless an agent file exists for them. Use them to keep prompts short and prevent overlapping edits.

- `repo-explorer`: read-only discovery, ownership mapping, and test lookup
- `api-backend-worker`: `api/src/` and `api/tests/`, excluding `api/src/ConnectedApps/`
- `connected-apps-worker`: `api/src/ConnectedApps/` only
- `osoma-worker`: `osoma/src/`
- `contract-and-e2e-worker`: `e2e/tests/` and narrow contract-boundary follow-up
- `security-reviewer`: auth, tenant isolation, secrets, and boundary review

Rules:

- Keep orchestration and cross-surface decisions in the main agent.
- Do not assign overlapping write scopes.
- If the change alters an API payload consumed by Osoma, pair the implementation with boundary verification in `e2e/` or other affected contract tests.
- If ownership is unclear, inspect locally first instead of guessing.

## Task Entry Prompts

Use these compact prompts when invoking an agent:

- Planner: `Map the relevant files, risks, and validation plan for: <task>. Do not edit.`
- Backend Agent: `Implement this approved backend change with minimal diffs. Scope: api-backend-worker. Validate according to AGENTS.md. Task: <task>.`
- Connected Apps Agent: `Implement this approved integration change with minimal diffs. Scope: connected-apps-worker. Validate according to AGENTS.md. Task: <task>.`
- Frontend Osoma Agent: `Implement this approved Osoma change with minimal diffs. Scope: osoma-worker. Validate according to AGENTS.md. Task: <task>.`
- Contract E2E Agent: `Verify or implement boundary coverage for this change. Scope: contract-and-e2e-worker. Validate according to AGENTS.md. Task: <task>.`
- Security Tenant Reviewer: `Review the current diff for auth, tenant isolation, secrets, and external-boundary issues. Do not edit.`
- Agent Docs Maintainer: `Update agent-facing documentation with minimal duplication. Validate with castor qa:agent-docs. Task: <task>.`
- Implementer: `Implement this approved change with minimal diffs. Scope: <ownership-scope>. Validate according to AGENTS.md. Task: <task>.`
- Reviewer: `Review the current diff for correctness, regressions, missing tests, and security/tenant boundaries.`
- Cloud PR Agent: `Create a small branch and draft PR for: <task>. Keep ownership scopes separate and summarize validation.`

## Companion Files

These files remain as thin adapters or scoped supplements:

- `.agent/rules/` for narrow workspace rules tied to real paths
- `.agent/workflows/` for short task-specific playbooks
- `.agents/workflows/organized-contributions.md` for contribution hygiene
- `.devcontainer/AGENTS.md` for the Dev Container agent adapter
- `.github/copilot-instructions.md` and `.github/instructions/` for GitHub/Copilot adapters
- `.github/agents/` for VS Code and GitHub Copilot agent definitions (see [AI agent execution modes](#ai-agent-execution-modes) above)

They should not restate the full repo map, command catalog, or agent ownership model.

## Maintenance Contract

- `AGENTS.md` is the only canonical repo-wide agent playbook.
- Companion files must be short, current, and scoped to their directory or task.
- When repo structure or tooling changes, update `AGENTS.md` first.
- If a referenced file is removed or renamed, remove or replace the reference in the same change.

---

## DeepAgents Code Integration

[Deep Agents Code](https://github.com/deepagents/deepagents) (`dcode`) is an
autonomous terminal coding agent installed in the dev container as a developer
tool.

Project-level startup instructions are in `.deepagents/AGENTS.md`.  That file
is loaded by `dcode` automatically when it starts from the workspace root.  The
root `AGENTS.md` (this file) is the canonical repository playbook and should be
read by any agent before making changes, but it is **not** automatically injected
by `dcode`—`.deepagents/AGENTS.md` handles that role.

### Installation

`dcode` is installed as a `uv` tool (not a project dependency) in
`.devcontainer/post-create.sh`:

```bash
uv tool install deepagents-code
```

After container creation it is available as `dcode`.

### Interactive local use

```bash
dcode
```

### Non-interactive CI/CD use

```bash
# Pass the task as arguments; the script exits with dcode's exit code.
bash scripts/deepagents-run.sh "Describe your task here"
```

Optional environment variables supported by the wrapper:

| Variable | Default | Purpose |
|---|---|---|
| `DEEPAGENTS_MODEL` | _(dcode default)_ | Override the model |
| `DEEPAGENTS_CODE_SHELL_ALLOW_LIST` | `recommended,git,castor,composer,php,pnpm` | Allowed shell commands |
| `DEEPAGENTS_MAX_TURNS` | `15` | Maximum agent turns |
| `DEEPAGENTS_TIMEOUT_SECONDS` | `900` | Wall-clock timeout |

### Constraints

- All rules in this file apply to `dcode` runs.
- Do not grant `dcode` write access to scopes owned by another agent.
- Never use `dcode` to commit secrets or bypass security checks.
- The shell allow-list must never use `-S all` in automated pipelines.
