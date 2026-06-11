# Phase 3 — Backend Integration Check

## Scope checked
- Tecniplast API client wiring
- Env/runtime assumptions
- Request construction
- Headers
- Response handling
- Error handling + debug logging

## Findings

### 1) Critical route mismatch (confirmed root-cause layer)
- **Before:** backend controller prefix was `#[Route('/api/connected_apps/{id}/dvc', ...)]`
- **Frontend effective target path:** `/connected_apps/{id}/dvc/*` after Vite `/api` rewrite.
- **Effect:** requests were sent to a path backend did not expose in the same shape from Osoma flow, causing immediate disconnect state.

### 2) Backend client itself is aligned with DVC contract
- `TecniplastHttpClient` uses the documented DVC endpoints and `x-api-key` header.
- Payloads and endpoints match OpenAPI path list.

### 3) Error handling improved
- Added try/catch around proxy operations (`test-api-key`, `metrics`, `search`, `submit`, `task state`) returning explicit `502` payloads.
- Added guarded debug logging (`%kernel.debug%`) for request/response breadcrumbs.

## Changes implemented

### File: `api/src/Controller/Api/TecniplastProxyController.php`
- Route prefix changed to:
  - `#[Route('/connected_apps/{id}/dvc', name: 'api_tecniplast_dvc_')]`
- Added:
  - injected `LoggerInterface`
  - debug guard via `#[Autowire('%kernel.debug%')] bool $debugEnabled`
  - `debugLog()` helper
  - structured error responses for upstream failures

## Required checklist status
- ✔ endpoint used
- ✔ headers
- ✔ response mapping
- ✔ error handling
- ✔ temporary debug logs (request/response/error in controller)

---

## Agent mandatory output

### Files inspected
- `api/src/Controller/Api/TecniplastProxyController.php`
- `api/src/ConnectedApps/Apps/Tecniplast/Client/TecniplastHttpClient.php`
- `api/config/services.yaml`

### Reproduction steps
1. Open Osoma → Analytics Hub → Live API Extraction.
2. Trigger API key test.
3. Before fix: disconnected state due to failing proxy path.
4. After fix: request reaches backend route and upstream integration path.

### Fix proposal
- Route base alignment + explicit backend diagnostics (implemented).
