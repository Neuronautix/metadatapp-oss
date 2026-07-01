# DVC Contract Hardening Plan

## Purpose

Track the DVC frontend/backend contract hardening work with a clear done vs remaining checklist.

## Status summary

- `reports/todo/` did not exist in this repo before this note. It is created for this plan.
- The DVC proxy routes are still not exposed by the main API Platform `docs.jsonopenapi`.
- For now, the DVC proxy contract source of truth is the dedicated checked-in spec at:
  - `api/src/ConnectedApps/Apps/Tecniplast/docs/DvcProxyApi.openapi.yaml`

## Checklist

### Backend contract as source of truth

- [x] Define a backend-owned contract for the local DVC proxy routes.
  - Implemented with `api/src/ConnectedApps/Apps/Tecniplast/docs/DvcProxyApi.openapi.yaml`.
- [x] Generate frontend types from that DVC proxy contract.
  - Implemented with `osoma/src/domain/dvc/dvc-proxy.generated.ts`.
- [x] Add a generation command for the DVC proxy types.
  - Implemented with `pnpm run openapi:types:dvc-proxy` in `osoma/package.json`.
- [ ] Fold the DVC proxy routes into the main backend OpenAPI output.
  - Task: expose `/connected_apps/{id}/dvc/*` in the primary Symfony/API Platform spec so the repo has one canonical API document.
- [x] Fail CI if generated DVC types are stale.
  - Implemented in `.github/workflows/ci.yml` with `pnpm run openapi:types:dvc-proxy` plus `git diff --exit-code`.
- [ ] Remove remaining hand-written request/response shapes wherever the backend already defines them.
  - Task: extend the same pattern beyond the current DVC proxy client.

### Runtime validation at the frontend API boundary

- [x] Add a dedicated runtime contract module for DVC.
  - Implemented with `osoma/src/domain/dvc/dvc.contract.ts`.
- [x] Validate important DVC responses before UI code consumes them.
  - Implemented for:
    - `test-api-key`
    - `metrics`
    - `cages/search`
    - `animals/search`
    - `submit`
    - `state`
- [x] Validate important DVC requests before they are submitted.
  - Implemented for:
    - `cages/search`
    - `animals/search`
    - `submit`
- [x] Fail fast with explicit contract errors instead of allowing silent UI corruption.
  - Implemented via `parseDvcContract()` and `validateDvcRequest()`.
- [ ] Validate download response metadata explicitly.
  - Task: add a typed download metadata check for response headers or a dedicated metadata endpoint if the current blob path is too opaque.

### Backend API tests for critical DVC flows

- [x] Test that the DVC proxy route is reachable at `/connected_apps/{id}/dvc/...`.
  - Implemented in `api/tests/Api/TecniplastProxyControllerTest.php`.
- [x] Test `test-api-key` payload passthrough and helpful upstream failure payloads.
  - Implemented in `api/tests/Api/TecniplastProxyControllerTest.php`.
- [x] Test that `submit` rejects bad datetime formats.
  - Implemented in `api/tests/Api/TecniplastProxyControllerTest.php`.
- [x] Test that `submit` returns a numeric `taskId`.
  - Implemented in `api/tests/Api/TecniplastProxyControllerTest.php`.
- [x] Test that `state` returns the exact response shape the frontend expects.
  - Implemented in `api/tests/Api/TecniplastProxyControllerTest.php`.
- [x] Test that `download` returns authenticated ZIP content.
  - Implemented in `api/tests/Api/TecniplastProxyControllerTest.php`.

### Frontend integration coverage

- [x] Test the exact payload the UI builds for DVC submit.
  - Implemented in `osoma/src/features/integrations/tecniplast/dvc/dvc.submission-plan.test.ts`.
- [x] Test the exact auth path the browser uses for DVC download/export.
  - Implemented in `osoma/src/features/integrations/tecniplast/dvc/dvc.integration.api.test.ts`.
- [x] Assert jobs are persisted, statuses update, and completed jobs enable plot/export.
  - Implemented in `osoma/src/features/integrations/tecniplast/dvc/DVCExtractionJobsPanel.test.tsx`.

### End-to-end golden path

- [x] Add one Playwright golden-path test for Analytics Hub.
  - Implemented in `e2e/tests/osoma/analytics-hub.spec.ts`.
- [x] Verify the real flow:
  - login
  - open `dvc/analytics`
  - check availability
  - submit one extraction
  - poll until `COMPLETED`
  - download ZIP
  - extract and visualize the result in the UI
- [x] Verify a CSV is present by inspecting ZIP contents inside the test without relying on external host tools.
  - Implemented in `e2e/tests/osoma/analytics-hub.spec.ts` with `jszip`.

### Agent workflow / contribution policy

- [x] Add a DVC contract checklist to the agent workflow documentation.
  - Implemented in `.agents/workflows/organized-contributions.md`.
- [x] Make the rule explicit that this is policy, not protection.
  - Implemented in `.agents/workflows/organized-contributions.md`.

## Immediate next tasks

- [x] Add backend tests for `submit`, `state`, and `download`.
- [x] Add CI enforcement for stale generated DVC proxy types.
- [x] Add frontend integration tests for payload building, job persistence, and authenticated ZIP export.
- [ ] Decide whether to merge the DVC proxy contract into the main backend OpenAPI output or keep the dedicated DVC spec long-term.
- [x] If explicit ZIP entry assertions are required, add a JS ZIP reader to the E2E toolchain and assert the expected CSV filenames directly.
