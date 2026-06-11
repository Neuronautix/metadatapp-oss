# Evolution Report - PR #250

## Merge metadata

- Date: 2026-05-27
- PR: #250
- Title: fix: fallback docker-only api hostname to /api

Co-authored-by: dhuzard <48721374+dhuzard@users.noreply.github.com>
- Branch: copilot/error-resource-unavailable
- Contributors: dhuzard, Copilot
- Reviewer(s): not recorded in available PR metadata
- Merged by: dhuzard

## What was merged

- Updated `osoma/src/lib/api.ts` so browser-incompatible API hostnames now include both `frontend` and `api`, forcing those docker-only bases back through the relative `/api` proxy path.
- Added `osoma/src/lib/api.test.ts` coverage that exercises the Tecniplast alarms request when `VITE_API_URL` is set to `http://api`.
- Closed a configuration gap where the browser could inherit a Docker-internal hostname that only worked from inside the Compose network.

## What it brings

- Prevents frontend API calls from breaking when local or CI configuration points at the internal `api` hostname instead of a browser-reachable base URL.
- Keeps the same resilient `/api` proxy fallback behaviour that already existed for the `frontend` hostname.
- Gives the repo a focused regression test for this specific failure mode.

## Benefits

- User benefit: Users no longer hit avoidable API fetch failures just because the frontend inherited a Docker-only hostname.
- Product benefit: The application behaves more consistently across development and container-backed environments.
- Engineering benefit: The test in `api.test.ts` documents the expected fallback rule and reduces the chance of future regressions.
- Operational benefit: Local and preview environments are less brittle when env vars are copied between runtime contexts.

## Long-term vision

- Strategic theme: Make environment configuration failures degrade gracefully instead of surfacing as broken application pages.
- Horizon impact: Short term — the fix immediately reduced one concrete class of route-level failures.
- Future opportunities unlocked: Similar hostname / base-URL guards can be added for other deployment mismatches using the same helper pattern.

## Risks and tradeoffs

- The guard only addresses known Docker-internal hostnames; other misconfigured API bases can still fail if they are not browser-reachable.
- Fallback logic in the client can mask underlying configuration mistakes unless teams still monitor env consistency.

## Follow-up actions

- [ ] Review whether any other environment-specific API hostnames should be treated as browser-incompatible in `api.ts` (owner: frontend team, target: backlog)
- [ ] Keep the route-level and API-base regression tests close to future deployment-config changes so this fallback stays intact (owner: frontend team, target: backlog)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/250
- Changed areas: `osoma/src/lib/api.ts`, `osoma/src/lib/api.test.ts`
- Validation evidence (tests, checks, metrics): the merged test verifies that `getAlarms()` still fetches through `/api/tp/alarms` when `VITE_API_URL` is `http://api`.
