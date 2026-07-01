# Evolution Report - PR #250

## Merge metadata

- Date: 2026-05-27
- PR: #250
- Title: fix: fallback docker-only api hostname to /api
- Branch: copilot/error-resource-unavailable
- Contributors: dhuzard, Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Fixed the API hostname resolution fallback for docker-only deployments to use `/api` instead of a hardcoded or unavailable hostname.
- Changed 2 files (+22 / -2 lines).

## What it brings

- Docker-only deployments (e.g. local dev, CI sandbox) no longer fail to reach the API because of hostname resolution errors.
- The fallback ensures the frontend can always locate the backend API path in environments without a custom domain.

## Benefits

- User benefit: Developers running docker-only setups no longer encounter "resource unavailable" errors when the API hostname cannot be resolved.
- Product benefit: Reduces environment-specific friction in local development and CI environments.
- Engineering benefit: Small, targeted fix with minimal risk; improves developer experience without affecting production deployments.
- Operational benefit: CI environments using docker-only mode are more reliable.

## Long-term vision

- Strategic theme: Resilient environment configuration that works out of the box.
- Horizon impact: Short term — immediate fix for a recurring local dev pain point.
- Future opportunities unlocked: Cleaner environment variable documentation for deployment variants.

## Risks and tradeoffs

- The `/api` fallback is a convention; if the backend path changes, this fallback must be updated.
- No new tests were added; the fix is covered by existing smoke tests.

## Follow-up actions

- [ ] Document the `/api` fallback convention in the deployment configuration docs (owner: docs, target: TBD)

## References

- Merge commit: bfc66547bc5abe7fb45fa12cc68ccc33f667b5d7
