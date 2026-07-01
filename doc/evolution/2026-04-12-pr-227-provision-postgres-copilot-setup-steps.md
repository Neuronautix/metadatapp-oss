# Evolution Report - PR #227

## Merge metadata

- Date: 2026-04-12
- PR: #227
- Title: fix(ci): provision PostgreSQL in copilot-setup-steps to unblock PHPUnit API tests
- Branch: copilot/fix-copilot-test-failure
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Added a `postgres:16` service container to `.github/workflows/copilot-setup-steps.yml` bound to `localhost:5432` with credentials matching the app (`app`/`app`).
- Added a setup step that writes a `DATABASE_URL` pointing to `localhost:5432/app` into `api/.env.test.local` (gitignored), overriding the `database` Docker Compose alias that is unreachable in the Copilot cloud-agent environment.
- Added setup steps to run `doctrine:database:create`, `doctrine:migrations:migrate`, and `cache:clear` in the `test` environment so the `app_test` schema is ready before any PHPUnit command executes.

## What it brings

- PHPUnit API tests can now run in the Copilot cloud-agent environment (which does not start Docker Compose) without failing on a missing database connection.
- The test database is pre-migrated and cache-cleared during setup, so individual test runs do not need to perform schema preparation.
- Copilot-driven PRs now benefit from backend test validation, improving the quality of automated contributions.

## Benefits

- User benefit: No direct user-facing impact; increases reliability of Copilot-generated backend changes.
- Product benefit: Backend test coverage is now enforced for Copilot agent PRs, reducing the risk of undetected regressions in AI-authored code.
- Engineering benefit: Removes a persistent blocker that prevented any Symfony kernel test from passing in the cloud-agent sandbox.
- Operational benefit: Sets a reproducible database provisioning pattern for future CI environments that lack Docker Compose.

## Long-term vision

- Strategic theme: Parity between local, CI, and Copilot cloud-agent test environments.
- Horizon impact: Short to medium term — immediately unblocks backend test validation for all future Copilot agent tasks.
- Future opportunities unlocked: Full `castor qa:all` parity in the cloud-agent environment, enabling automated code-quality gates on every Copilot PR.

## Risks and tradeoffs

- The `api/.env.test.local` file is gitignored, so the override is silently applied only in the agent environment; developers running tests locally still use the Docker Compose `database` alias.
- The `postgres:16` service container adds non-trivial setup time to the copilot-setup-steps workflow.
- If migration steps fail (e.g. due to a broken migration file), the entire setup fails and PHPUnit cannot run.

## Follow-up actions

- [ ] Confirm PHPUnit passes fully in the Copilot cloud-agent environment after this change with a test PR (owner: dhuzard, target: 2026-04-18)
- [ ] Consider pinning the `postgres:16` image to a specific digest for reproducibility (owner: CI maintainers, target: 2026-04-30)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/227
- Changed files: `.github/workflows/copilot-setup-steps.yml` (+35 lines, 3 commits)
- Related: `api/.env`, `api/.env.test`
