# Phase 7 — Root Cause Analysis

## Executive conclusion

**Exact failure layer:** Backend route binding layer (proxy entrypoint path), triggered by frontend proxy rewrite behavior.

The request failed **before** reaching Tecniplast authentication/requests/response parsing.

---

## Evidence chain

### Evidence A — Frontend client path
- `osoma/src/features/integrations/tecniplast/dvc/dvc.integration.api.ts`
  - calls `/connected_apps/${appId}/dvc/test-api-key` and related endpoints.

### Evidence B — Frontend transport base + rewrite
- `osoma/src/lib/api.ts`: base URL defaults to `/api`.
- `osoma/vite.config.ts`: proxy rewrite strips `/api` (`path.replace(/^\/api/, '')`).

Resulting backend path for test-key becomes:
- Browser request: `/api/connected_apps/{id}/dvc/test-api-key`
- Rewritten upstream path: `/connected_apps/{id}/dvc/test-api-key`

### Evidence C — Backend route before patch
- Controller was mounted at `/api/connected_apps/{id}/dvc`.
- Therefore rewritten request `/connected_apps/...` missed this controller route.

### Evidence D — UI symptom alignment
- `DVCIntegrationTasks` runs `testApiKey(appId)` immediately.
- On failed request it sets disconnected state and blocks wizard.

---

## Root-cause statement

The DVC integration failed because the backend proxy controller route prefix (`/api/connected_apps/...`) was inconsistent with Osoma’s API proxy rewrite contract (which strips `/api` before forwarding). The request path that reached Symfony did not match the controller route.

---

## Minimal fix (implemented)

1. **Align route with actual forwarded path:**
   - `api/src/Controller/Api/TecniplastProxyController.php`
   - changed class route prefix to `/connected_apps/{id}/dvc`.

2. **Add resilience and observability:**
   - backend guarded debug logs (enabled in debug mode)
   - clear `502` error messages for upstream failures
   - frontend disconnect state now displays concrete error detail

3. **Regression protection:**
   - `api/tests/Api/TecniplastProxyControllerTest.php` verifies:
     - endpoint reachable at `/connected_apps/{id}/dvc/test-api-key`
     - helpful error payload returned on upstream exception

---

## Agent mandatory output

### Files inspected
- `osoma/src/lib/api.ts`
- `osoma/vite.config.ts`
- `osoma/src/features/integrations/tecniplast/dvc/dvc.integration.api.ts`
- `osoma/src/features/integrations/tecniplast/dvc/DVCIntegrationTasks.tsx`
- `api/src/Controller/Api/TecniplastProxyController.php`

### Reproduction steps
1. Open Osoma Analytics Hub (Live API mode).
2. Component auto-runs test API key.
3. Before fix: disconnect state due to route mismatch.
4. After fix: request reaches backend proxy route and proceeds to upstream call.

### Fix proposal
- Keep frontend client contract unchanged; enforce route-prefix consistency in backend proxy controller (implemented).
