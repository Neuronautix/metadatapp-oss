---
activation: glob
glob_pattern: "api/**/*.php"
description: PHP backend specific rules for Symfony/API Platform development
---

# PHP Backend Development Rules

**Activation:** Glob pattern `api/**/*.php`

Read `AGENTS.md` first for repo-wide commands, ownership, and current paths.

## Core Rules

- Every PHP file starts with `declare(strict_types=1);`.
- Reuse existing API Platform and Doctrine patterns from `api/src/Entity/Experiment.php`, `Project.php`, and `ConnectedApp.php`.
- Keep custom API behavior in `api/src/State/Provider/` and `api/src/State/Processor/` unless there is a strong reason not to.
- Keep controllers thin; do not move Connected Apps or resource orchestration logic into controllers.

## Entity and Tenant Patterns

- Use UUID identifiers with `UuidGenerator`.
- Define resource security on the `#[ApiResource]` where appropriate.
- Reuse `AccountAwareInterface` and `UserAwareInterface` for tenant- and user-scoped resources.
- Prefer matching the conventions already used in neighboring entities over inventing a new pattern.

## Testing

- Backend tests should use Foundry factories from `api/src/DataFixtures/Factory/`.
- API and functional tests commonly use `ApiTestCase`, `Factories`, and `ResetDatabase`.
- Do not instantiate entities manually in tests.

## Connected Apps

- Keep integration logic under `api/src/ConnectedApps/`.
- Commands stay orchestration-only; sync logic belongs in services, mappers, synchronizers, or state classes.
- Keep sync behavior idempotent and resumable.

## Verification

- Use the Castor QA commands documented in `AGENTS.md`.
- If you add or change console-only behavior without a Castor alias, prefer the one-off `docker compose ... exec api ...` pattern from `AGENTS.md` rather than inventing a new wrapper.
