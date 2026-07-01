# Evolution Report - PR #216

## Merge metadata

- Date: 2026-04-11
- PR: #216
- Title: Add alarms-route regression coverage to Playwright
- Branch: copilot/setup-e2e-with-playwright
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Added an Osoma E2E Playwright spec for the `/tp/alarms` route, asserting the page renders correctly and does not surface backend route errors.
- Added a Vitest unit test verifying that Tecniplast alarms API requests are routed through the browser-safe `/api/tp/alarms` path when `VITE_API_URL` points to the Docker-only `frontend` hostname.
- Updated the E2E README coverage list to include the Tecniplast alarms page.

## What it brings

- The previously uncovered `/tp/alarms` route is now guarded by automated regression tests.
- Any future backend routing regression (e.g. `No route found for "GET /tp/alarms"`) will be caught by the E2E suite before merge.
- The frontend request-path rewrite logic for Docker-only hostnames is explicitly validated in isolation, preventing silent regressions when `VITE_API_URL` changes.

## Benefits

- User benefit: Tecniplast alarms page regressions surface in CI before they reach users.
- Product benefit: Broader Playwright coverage strengthens confidence in the Tecniplast integration as a supported feature.
- Engineering benefit: A focused Vitest test pins the API request-path rewrite contract without requiring a full stack.
- Operational benefit: Reduces the risk of silent breakage in the alarms monitoring feature after future refactors.

## Long-term vision

- Strategic theme: Systematic E2E regression coverage of all connected-app integrations.
- Horizon impact: Short term — guards an existing integration path that was previously unprotected.
- Future opportunities unlocked: Establishes the pattern for adding lightweight E2E smoke tests to other Tecniplast and Connected Apps routes.

## Risks and tradeoffs

- The Playwright test runs against live services; if the test environment does not have a running backend or the `/tp/alarms` route is mocked, the test may produce a false negative.
- Firewall rules in the CI sandbox blocked `pecl.php.net`, which may affect PHP extension availability in agent runs (not directly related to this change, but noted).

## Follow-up actions

- [ ] Extend Playwright coverage to other Tecniplast routes (`/tp/operations`, `/tp/conditions`, `/tp/sensors`) using this spec as a template (owner: contract-and-e2e-worker, target: 2026-04-30)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/216
- Changed files: E2E spec, Vitest test, E2E README (52 additions, 3 files, 2 commits)
