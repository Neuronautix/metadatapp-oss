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
- `.github/workflows/ci.yml` if you are changing automation or required checks — see [GitHub Actions Workflows](#github-actions-workflows) for the full workflow catalogue

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
| End-user / integration docs | `docs/guide/` | Sphinx + MyST user guide; add a Connected Apps page per `AppCode` (checked by `castor qa:docs-check`) |

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

#### API Platform 4.x reference

Training data for API Platform 4.x is often outdated. Before adding or changing API code, fetch the live docs index:

```
https://api-platform.com/docs/llms.txt          # index
https://api-platform.com/docs/llms-full.txt     # full text
```

Claude Code users: install the official skillset for auto-loading verified code examples:

```
/plugin marketplace add api-platform/skillset
/plugin install api-platform@api-platform-skillset
```

Match the task to the skill and its canonical doc page (the table lists the Symfony doc paths; swap in the Laravel docs at api-platform.com/docs/laravel/ when using Laravel):

| Task | Skill | Canonical doc |
|---|---|---|
| Expose data, add endpoint, DTO, sub-resource | `api-resource` | https://api-platform.com/docs/core/design/ |
| Custom read logic, computed fields, enrichment | `state-provider` | https://api-platform.com/docs/core/state-providers/ |
| Custom write logic, side effects, soft-delete | `state-processor` | https://api-platform.com/docs/core/state-processors/ |
| Multi-tenant isolation, global query filters | `securing-collections` | https://api-platform.com/docs/core/extensions/ |
| Collection filters (`QueryParameter`) | `api-filter` | https://api-platform.com/docs/core/filters/ |
| Operation security, validation groups | `operations` | https://api-platform.com/docs/core/operations/ |
| RFC 7807 error mapping, `#[ErrorResource]` | `errors` | https://api-platform.com/docs/core/errors/ |
| Serialization groups, context-based output | `serialization-groups` | https://api-platform.com/docs/core/serialization/ |
| Pagination (page-based or cursor) | `pagination` | https://api-platform.com/docs/core/pagination/ |
| Functional API tests | `api-test` | https://api-platform.com/docs/core/testing/ |

### Testing

- Do not instantiate entities manually in backend tests; use Foundry factories from `api/src/DataFixtures/Factory/`.
- API tests typically extend `ApiTestCase` and use `Factories` plus `ResetDatabase`.
- E2E tests must disable MSW with `localStorage.setItem('use_msw', 'false')`.
- Do not use `page.waitForNetworkIdle()` in Playwright; use explicit selectors or targeted waiters.
- **Mocks must mirror the real contract.** An MSW handler or stub must match the live API's request/response shape, including write-payload form. API Platform resource enums (e.g. `AppCode`) require an IRI in write bodies — `code: "/app_codes/<value>"`, not the bare string — so a permissive mock that accepts the bare value hides a real `Invalid IRI` 4xx. A green unit test against a missing or lax handler is not evidence the live path works: when a unit test passes but the live call fails, suspect mock/contract divergence first.
- **Rebuild `dist/` before testing frontend changes on `*.metadatapp.test`.** The `osoma` service serves a prebuilt bundle via `vite preview` (Traefik → port 4173, host `dist/` volume-mounted), not the dev server — a committed source fix is not live until `castor qa:osoma:build` runs. If a fix looks correct in source but the live site still shows the old behavior, check the served bundle's timestamp against the fix before debugging further.

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
castor qa:docs-check         # check Connected Apps guide matches the AppCode enum
castor qa:all
castor schema-validate
castor declarations          # regenerate repo declarations (FAIR/TRUST/AI/design)
castor declarations --check  # CI mode: fail if declarations are stale
```

Repo declarations (`FAIR.md`, `TRUST.md`, `AI-declaration.md`, the design declaration,
`CITATION.cff`, `codemeta.json`, `llms.txt`, and the `.well-known/repo-declarations.json`
index) are generated from repository evidence by `scripts/generate-declarations.py`. Facts
the scanner cannot infer live in `.declarations.yml`. See
`docs/architecture/repo-declarations.md` for the full strategy and how to reuse the
generator across repositories.

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

## GitHub Actions Workflows

Eleven workflows live in `.github/workflows/`. They fall into four groups. Read this section before editing any workflow file or modifying required checks.

### Always-on (run automatically on every PR and `main` push)

| File | Trigger | Purpose |
|------|---------|---------|
| `ci.yml` | push `main`, all PRs, `workflow_call` | Full suite: Castor stack, PHPUnit, PHPStan, coding standards, Osoma build + integration tests, schema validation, agent-doc lint. **Primary required check.** |
| `clean-clone-smoke.yml` | push `main`, all PRs | Lightweight, secret-free smoke test that works on forks: validates required docs, composer manifest, PHP lint, frontend build + lint. Runs in parallel with `ci.yml`. |
| `evolution-report.yml` | PR merged to `main` | AI-generates an evolution report (GPT-4o via GitHub Models) for the merged PR and opens a docs PR updating `doc/evolution/` and the wiki. Fully automatic — no action needed. |
| `declarations.yml` | push `main`, all PRs, `workflow_dispatch` | Keeps the generated repo declarations in sync: `--check` (drift fails the PR) and auto-commit of any drift on `main`. See `docs/architecture/repo-declarations.md`. |
| `declarations-reusable.yml` | `workflow_call` | Reusable variant so other repositories can run the same declaration generator without vendoring it. Not triggered in this repo directly. |
| `docs.yml` | push `main`, PRs touching docs, `workflow_dispatch` | Builds the User & Integration Guide (`docs/guide/`, Sphinx + MyST, warnings-as-errors), runs `scripts/check_connected_apps_docs.py` so Connected Apps pages can't drift from `api/src/Enum/AppCode.php`, and **deploys to GitHub Pages on `main`** (PRs build/validate only). Build/check is secret-free and path-filtered; only the deploy job uses Pages permissions. |

### Deployment

| File | Trigger | Purpose |
|------|---------|---------|
| `deploy.yml` | `workflow_dispatch` or `workflow_call` | Deploys `api` or `osoma` to Clever Cloud. Inputs: `environment` (prod) and `app` (api \| osoma). Requires `CLEVER_TOKEN` / `CLEVER_SECRET` secrets. |
| `load-demo-fixtures.yml` | `workflow_dispatch` (requires `confirm: yes`) | Runs DB migrations and reloads demo fixtures remotely via Clever Cloud SSH. Use only when intentionally resetting demo data. The `confirm: yes` gate prevents accidental wipes. |

### Copilot and AI agent tooling

| File | Trigger | Purpose |
|------|---------|---------|
| `copilot-setup-steps.yml` | `workflow_dispatch`, changes to its own file | Bootstraps the GitHub Copilot cloud-agent: installs PHP 8.4, Node 22, Composer, pnpm, and prepares the test DB. Required for Copilot coding agent tasks — otherwise ignore. |
| `wiki-lint.yml` | Weekly cron (Mon 07:00 UTC), `workflow_dispatch` | Lints `doc/wiki/` for stale pages, missing frontmatter, orphan pages, and placeholders. Opens a PR with a report when issues are found. |

### OSS publication

| File | Trigger | Purpose |
|------|---------|---------|
| `publish-oss.yml` | `workflow_dispatch` only | Rebases the OSS cleanup branch onto `main`, creates a history-free snapshot, and optionally force-pushes it to the public mirror. See [OSS Publishing Workflow](#oss-publishing-workflow) for full instructions. |

---

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

### Agentic Loop Constraints

All agents running in this repository — `dcode`, GitHub Copilot, Claude Code, and any custom scripts — must respect these loop requirements.

**Completion requires evidence — the rule above all others.** Never report a task complete on your own say-so. "Done" is a claim that must be backed by evidence already visible in the transcript — a command's output, a passing test, a reproduced behaviour. If you cannot point to that evidence, you are not done: keep working, or stop and say why. "Looks done" is not "is done." Lint, type-check, and build green are floors you keep, never proof that the feature works.

#### Classify the loop before you start

Name which kind of work you are doing before acting; the wrong primitive either burns tokens re-running finished work or never terminates.

- **Goal-bounded** — you are pushing to a finish line a runnable check can confirm. Run it as a single capped pass with a verifiable stop condition (below). Never wrap finish-line work in a perpetual watch loop.
- **Watch / recurring chore** — you are waiting for a change or repeating a routine task with no reachable end state. Use the /loop skill (self-paced or interval) for in-session watching, or the /schedule skill (Routines) for cron-style recurrence. Never put a goal condition on a watch task — it has no end state to satisfy.
- **Surface** — decides where the work runs and who pays (see [Surfaces and billing](#surfaces-and-billing) below).

Decision rule: "Am I pushing this to a finish line, or watching for a change?" A scheduled job whose body must reach a provable end is a Routine (the interval) wrapping a goal-bounded pass (the body) — compose the two, do not conflate them.

#### Writing a goal condition

A goal is only as trustworthy as the check that proves it. The agent or evaluator judging it sees only the transcript, so the work must print its own proof. Every goal-bounded run states all four:

- **End state** — a measurable result (e.g. every test under `api/tests/Security` passes).
- **Stated check** — the exact command that proves it, run with its raw output shown (e.g. the relevant `castor phpunit` invocation). An unproven "all tests pass" is not evidence.
- **Constraints** — what must not change. Always close the cheapest cheat: if the check can be satisfied by deleting a call site, weakening or skipping a test, stubbing a function, or fabricating data, forbid that explicitly (e.g. "no call site is stubbed; no test is weakened or skipped; mark unknowns as null, do not invent facts").
- **Cap** — a circuit breaker (the iteration guard below): stop after N turns and summarise rather than spinning.

Split compound goals ("redesign auth, add OAuth, write tests, update docs") into sequential goals, each with its own end state, check, and cap. If a loop runs without converging, the condition is checking a proxy — restate it against a more directly runnable check instead of retrying unchanged.

#### Runtime guards

**Iteration guard** — Every loop must have an upper bound. `dcode` enforces `DEEPAGENTS_MAX_TURNS=15` via the wrapper script. Any custom agent script must set `max_iterations ≤ 20` and emit a diagnostic when the limit is reached rather than silently stopping. Never remove or bypass iteration limits in automated pipelines.

**Consecutive-failure breaker** — For repeated-cycle work, stop and alert after N failures in a row rather than hammering a broken state. A loop that thrashes without progress is checking a proxy or hit a blocker that needs a human decision — surface it instead of retrying unchanged.

**Errors as data, not exceptions** — Tool failures (subprocess errors, file-not-found, HTTP 4xx) must be fed back into the conversation as tool result messages, not raised as exceptions that abort the loop. The agent system prompt must instruct the model to analyse the error and try a different approach rather than giving up on first failure.

**Stop condition** — After each model call, check the stop reason before issuing the next tool call. Proceed only when the model signals `tool_use` (Anthropic API: `stop_reason == "tool_use"`). When `stop_reason == "end_turn"`, surface the final text response and exit cleanly — do not re-invoke the model with an empty tool list.

**Irreversible action gate** — Before executing destructive tools (database writes, external API calls, `git push`, `docker compose down`, fixture reload), confirm the task was explicitly scoped for that action. In interactive sessions request human approval. In automated pipelines require an explicit `--yes` or equivalent flag from the invoker; never infer consent from context.

**System prompt wiring** — Every agent session must include: (a) a reference to `AGENTS.md` or `.deepagents/AGENTS.md` as the canonical playbook, (b) the ownership scope for the session (see [Ownership Scopes](#ownership-scopes)), and (c) the iteration limit and error-handling policy above. Sessions launched without an ownership scope default to read-only (`repo-explorer`) until a scope is confirmed.

#### Self-check protocol

Per-step discipline for autonomous loops, adapted from the Karpathy CLAUDE.md ten-rule pattern (four edit-time + six runtime). An autonomous loop has no human reviewer at each step, so each step must carry its own verification.

**Verify before fixing** — Before changing code to resolve a reported bug, first write or run a check that reproduces it. Treat the bug as fixed only when that reproducer passes — not when the diff "looks right". The reproducing test is the loop's only gate.

**Maker is not the judge** — The agent that writes must not be the agent that signs off. When verifying, reproduce the real check yourself and paste its raw output — not a summary of it — and trust nothing you cannot reproduce; re-running only the unit tests you also wrote inherits your own blind spots. Reach for the strongest verification the task affords: run the real suite, then exercise the feature as a user would (start the app, hit the endpoint, render the page, run the flow), golden/snapshot diff, schema/contract check. Before trusting any check, ask "if this passes and the feature is still broken, what did it miss?" and add the check that would have caught it. Re-check each acceptance criterion explicitly, one at a time; report PASS only when every one is demonstrably met in your own output, otherwise FAIL with the specific failing evidence.

**Validate output against a declared shape** — Every step that emits structured data (AI output, proxy response, generated payload) is checked against its expected schema before downstream use. Reject mismatches — missing fields, wrong types, out-of-range values — and retry-under-budget or halt; never pass malformed data forward.

**Deterministic over inferred** — Reserve model calls for judgment (classification, drafting, summarization). Routing, filtering, persistence, and dispatch stay in deterministic code so behavior does not drift run-to-run.

**Budget halts, never silent overruns** — Loops run under explicit per-step / per-run bounds (the iteration guard above is the turn budget). Exceeding any bound halts immediately, logs the breach, and surfaces it to the operator rather than continuing.

**Sanitize untrusted input before it reaches a prompt** — Operator/user text injected into a prompt is length-bounded and stripped of role markers and control framing to prevent prompt-injection pivots. This complements, never replaces, the irreversible-action gate above.

#### Lessons and resumable state

**Lesson protocol** — Sessions do not share context; a correction made only in chat dies with the run. When you are corrected, or discover a repeatable mistake or gotcha during an unattended loop, append a one-line, generalised rule to the [Lessons](#lessons) section at the foot of this file, then continue. The rule must be reusable ("never edit `infrastructure/docker/` from a watch loop"), never run-specific ("I fixed file X"). Keep what you load lean — a bloated context is re-sent every cycle and degrades reasoning; curate, do not accumulate.

**State file** — For long or queue-shaped work, maintain an explicit state ledger (a JSON file in the scratch dir). Each cycle: read it, take the next unprocessed item, do and verify the work, mark it done with a timestamp. This makes the loop resumable from recorded progress instead of restarting, and stops it re-processing the same item.

#### Self-improvement with a fitness gate

Your weights do not change between runs; the only thing that improves is the re-read instructions. Amend your own heuristics only as an accept-only-if-better experiment: record a baseline score (test pass count, coverage, time-to-green), propose exactly one candidate rule, apply it, re-score, and keep it only if the score improved — otherwise revert and report it rejected. You may never edit the thing that grades you to raise your score: the scorer, its tests, the coverage config, and the metric definition are out of bounds. Weakening a test to pass is a failure, not a fix.

#### Parallelism

To run agents in parallel they must not share a working tree — give each its own checkout (a git worktree) or they overwrite each other mid-run into irreproducible corruption, not a clean merge conflict. For "do X to every Y," fan out one unit per agent, each producing its own PR, and always include a "no change / nothing to do" reporting path so a silent skip is distinguishable from a real no-op. Even with isolation, respect the [Ownership Scopes](#ownership-scopes) below: never let two agents write the same scope or a shared component. Bound the fan-out to what a human can review.

#### Surfaces and billing

In-session work, desktop tasks, and Routines run under the subscription; headless runs (`claude -p`), the Agent SDK, and GitHub Actions are the per-token API path and have surprised people with four-figure overnight bills. Before wiring any headless or cron automation, verify the route (check /status and a one-turn test) and prefer a Routine or in-session run for subscription-billed scheduled work. Never set `ANTHROPIC_API_KEY` "to be safe" on a subscription — the key owns the bill and routes everything to API billing. Loops re-send context every cycle, so structure repeated context to hit the prompt cache and load only what the task needs. Re-verify billing policy before depending on it; it changes month to month.

#### Anti-patterns to refuse

- **Confident conveyor belt** — checks green, feature broken, called done. Verify as a user; require reproduced evidence.
- **Runaway** — hours pass without convergence. Cap turns and add the failure breaker.
- **Un-verifiable goal** — "complete" with the work undone. Rewrite the condition around a runnable check.
- **Primitive mismatch** — a perpetual watch loop on finish-line work, or a goal on a watch task. Apply the push-vs-watch test.
- **Proxy check** — verified something near the goal. Add the check that catches the real failure.
- **Goldfish** — corrected in chat but never wrote the lesson back. Append to [Lessons](#lessons).
- **Reward hacking** — raised a score by editing what scores you. The scorer is out of bounds.
- **Ungated prod** — propose only; a human owns the merge, and the path to production stays closed to autonomous loops (see the irreversible-action gate and the deploy/OSS workflows).

**Pre-flight checklist** before leaving any unattended loop — type named (goal vs watch); condition complete (end state + check + constraints + cap); proof emitted in the transcript; cheapest cheat forbidden; verification exercises reality and is independent of the maker; cap plus failure breaker present; lessons wired to [Lessons](#lessons) and a state file if resumable; scorer un-editable if self-improving; own checkout per parallel agent with a no-change path; billing route verified; gate intact (proposes only, no prod, in scope, logged); and you watched one full cycle complete correctly before walking away.

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
- Do not assign overlapping write scopes. Parallel agents on different scopes still need isolated checkouts (git worktrees) — see [Parallelism](#parallelism).
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

## Lessons

Generalised, reusable rules written back by autonomous loops under the [Lesson protocol](#lessons-and-resumable-state). One line each, never run-specific. Add the rule that would have prevented the mistake — not a record of the mistake.

_None yet._

## Maintenance Contract

- `AGENTS.md` is the only canonical repo-wide agent playbook.
- Companion files must be short, current, and scoped to their directory or task.
- When repo structure or tooling changes, update `AGENTS.md` first.
- If a referenced file is removed or renamed, remove or replace the reference in the same change.

---

## OSS Publishing Workflow

The public mirror is **Neuronautix/metadatapp-oss**. It receives clean,
history-free snapshots from the private repository; private development history
must never be pushed there.

Use the manual GitHub Actions workflow `.github/workflows/publish-oss.yml` when
publishing from GitHub. It rebases the OSS cleanup branch onto main, recreates
the public-release orphan branch, pushes that snapshot to the private origin,
and can optionally force-push it to the public mirror's main branch.

The final public push requires the private repository secret
`OSS_PUBLISH_TOKEN`, using a fine-grained GitHub token with Contents read/write
access to the public mirror. The workflow is guarded to run only in the private
repository and should use the protected `oss-publication` environment.

Local fallback:

```bash
./scripts/publish-oss.sh
```

If the cleanup branch rebase conflicts, resolve the conflict locally, push the
rebased cleanup branch, then rerun the workflow.

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
