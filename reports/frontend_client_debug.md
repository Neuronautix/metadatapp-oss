# Phase 5 — Frontend Client Check

## Scope checked
- Frontend API client endpoints
- Fetch wrapper behavior
- Query/state transitions
- Error handling surfaced to UI
- Request execution paths

## Findings

### 1) Endpoint pathing
- `dvc.integration.api.ts` calls:
  - `/connected_apps/{appId}/dvc/test-api-key`
  - `/connected_apps/{appId}/dvc/metrics`
  - `/connected_apps/{appId}/dvc/cages/search`
  - etc.
- `apiFetch` base is `/api` by default.
- Vite proxy rewrites `/api/...` → `/...` before forwarding to backend.

### 2) Actual breakage mechanics
- Resulting backend path seen by Symfony is `/connected_apps/{id}/dvc/...`.
- Backend originally exposed `/api/connected_apps/{id}/dvc/...`.
- This mismatch caused the request to fail before business logic and made UI show “Integration Disconnected”.

### 3) Client/request execution
- `DVCIntegrationTasks` executes `testApiKey(appId)` on mount.
- Failure path sets `isProxyValid=false` and renders disconnect empty state.

### 4) Added frontend diagnostics
- Added DEV-only logs in `DVCIntegrationTasks`:
  - test-api-key response
  - metrics response
  - raw cages search payload
- Added explicit error detail in disconnect empty state (`proxyError`).

## Verification checklist
- ✔ correct endpoint pattern in client (relative connected_apps path)
- ✔ request executed on component mount
- ✔ error states now expose concrete message
- ✔ payload shape logging added for rapid diagnosis

---

## Agent mandatory output

### Files inspected
- `osoma/src/features/integrations/tecniplast/dvc/dvc.integration.api.ts`
- `osoma/src/lib/api.ts`
- `osoma/vite.config.ts`
- `osoma/src/features/integrations/tecniplast/dvc/DVCIntegrationTasks.tsx`

### Reproduction steps
1. Open Analytics Hub in Live API mode.
2. Observe automatic key test request.
3. If failing, inspect disconnect state detail and browser console debug logs.

### Fix proposal
- Keep client paths as-is; align backend route prefix with rewritten incoming path (implemented).
