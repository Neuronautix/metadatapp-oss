# Phase 1 — DVC System Map

## End-to-end flow

Tecniplast DVC Analytics
→ API authentication (x-api-key)
→ DVC API request
→ DVC API response
→ MAPP API backend proxy/service
→ Osoma frontend API client
→ React state/query layer
→ Visualization components

---

## Layer map

### 1) Tecniplast DVC Analytics (external)
- **Code location:** external SaaS API; the archived vendor contract previously used for this investigation was removed from the repository. The retained runtime proxy spec is `api/src/ConnectedApps/Apps/Tecniplast/docs/DvcProxyApi.openapi.yaml`.
- **Expected input:** HTTP requests on `/tasks/api/1/integration/*`
- **Expected output:** JSON (or octet-stream for download)
- **Logging:** external only (not controlled from this repo)

### 2) API authentication
- **Code location:**
  - External API security scheme observed during investigation: `x-api-key` header
  - Backend key source: `api/src/Entity/ConnectedApp.php` (`token`)
  - Token checks: `api/src/Controller/Api/TecniplastProxyController.php` (`getValidToken`)
- **Expected input:** connected app token set in DB
- **Expected output:** request headers include `x-api-key: <token>`
- **Logging:** now available via guarded backend debug/error logs in proxy controller

### 3) API request to Tecniplast
- **Code location:** `api/src/ConnectedApps/Apps/Tecniplast/Client/TecniplastHttpClient.php`
- **Expected input:** method + endpoint + headers + payload
- **Expected output:** normalized arrays via `toArray(false)`
- **Logging:** no native client logs; request/response/error now logged in proxy controller (guarded by `%kernel.debug%`)

### 4) API response from Tecniplast
- **Code location:** parsed in `TecniplastHttpClient`, re-shaped (for cages search) in `TecniplastProxyController`
- **Expected input:** DVC payloads (metrics, cages, task state, etc.)
- **Expected output:** JSON response returned to frontend; `searchCages` wraps `{ cages, min_date, max_date }`
- **Logging:** controller debug/error logs

### 5) Backend adapter / service layer
- **Code location:**
  - Controller entrypoint: `api/src/Controller/Api/TecniplastProxyController.php`
  - Upstream HTTP client: `api/src/ConnectedApps/Apps/Tecniplast/Client/TecniplastHttpClient.php`
- **Expected input:** frontend calls on `/connected_apps/{id}/dvc/*`
- **Expected output:** proxied DVC responses, 502 with clear message on upstream failures
- **Logging:** yes (debug in dev, error always)

### 6) Frontend API client
- **Code location:**
  - DVC integration client: `osoma/src/features/integrations/tecniplast/dvc/dvc.integration.api.ts`
  - Generic fetch wrapper: `osoma/src/lib/api.ts`
  - Vite proxy rewrite: `osoma/vite.config.ts`
- **Expected input:** `appId`, payloads from UI
- **Expected output:** typed JSON/blob data to React components
- **Logging:** browser console API errors, plus added DEV debug logs in DVC integration task component

### 7) React state/query layer
- **Code location:** `osoma/src/features/integrations/tecniplast/dvc/DVCIntegrationTasks.tsx`
- **Expected input:** API client promises
- **Expected output:** `isProxyValid`, metrics list, validated cages, tasks polling state
- **Logging:** added DEV-only `console.debug` for raw payload checkpoints

### 8) Visualization components
- **Code location:**
  - Extraction wizard UI: `DVCIntegrationTasks.tsx`
  - Analytics page container: `AnalyticsHubPage.tsx`
- **Expected input:** parsed metrics/cages/tasks and downloaded data
- **Expected output:** wizard progression + table/status + download/extract actions
- **Logging:** frontend error + debug logs

---

## Agent mandatory output

### Files inspected
- Archived Tecniplast DVC Analytics OpenAPI contract (removed from repository after investigation)
- `api/src/ConnectedApps/Apps/Tecniplast/Client/TecniplastHttpClient.php`
- `api/src/Controller/Api/TecniplastProxyController.php`
- `osoma/src/lib/api.ts`
- `osoma/src/features/integrations/tecniplast/dvc/dvc.integration.api.ts`
- `osoma/src/features/integrations/tecniplast/dvc/DVCIntegrationTasks.tsx`
- `osoma/vite.config.ts`

### Findings
- Integration spans external DVC API + backend proxy + frontend `/api` proxy rewrite + React wizard state.
- Single route-prefix mismatch can break the entire pipeline before upstream API is contacted.

### Reproduction steps
1. Open Osoma Analytics Hub in live mode.
2. Trigger “Test API Key”.
3. Observe integration marked disconnected if proxy route mismatch exists.

### Fix proposal
- Align backend route base with rewritten frontend path (implemented).
