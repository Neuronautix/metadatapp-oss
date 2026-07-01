# Frontend Mock Data Audit

Date: 2026-06-11

Scope: Osoma frontend runtime code under `osoma/src` and static mock payloads under
`osoma/public`.

## Summary

The large `osoma/src/mocks` tree is MSW-only and should be kept while mock mode
exists. The higher-risk items are runtime imports or hardcoded arrays used by
feature pages when the app is in real mode. Those should be replaced first with
real API calls or hidden behind explicit demo/mock mode checks.

## Production-Mode Mock Risks

| Priority | Area | Files | Current behavior | Replacement path |
| --- | --- | --- | --- | --- |
| P0 | Zefix/Zefish domain API | `osoma/src/domain/zefish/zefish.api.ts`, `osoma/src/mocks/zefish/*` | Imports mock Zefish lines, batches, systems, alerts, and cryo records directly into the domain API. Some methods use real endpoints only conditionally and still derive views from mock records. | Replace every direct `@/mocks/zefish/*` import with backend endpoints for overview, room/system snapshots, environmental observations, alerts, batches, lines, and cryo records. Keep mock data only in MSW handlers. |
| P0 | Connected Apps fake sync history | `osoma/src/features/integrations/connected-apps/components/SyncHistoryLog.tsx` | Removed. It showed hardcoded “Delta sync completed” and “Token expired” entries. | Add a real backend sync-event/log endpoint before showing transfer history again. |
| P1 | Connected Apps catalog fallback | `osoma/src/features/integrations/connected-apps/connected-apps.api.ts` | Hardcoded app catalog is used to show missing connected apps when the DB has no row. This is useful, but not true backend data. | Move catalog ownership to the backend, e.g. `GET /connected_app_catalog`, including supported actions, required credentials, default URL, logo, and capability metadata. |
| P1 | Connected Apps mock handlers/data | `osoma/src/mocks/handlers/connectedApps.ts`, `osoma/src/mocks/data/connectedApps.ts` | MSW mode has hardcoded connected apps, fake sync responses, and FAIR3R demo payloads. | Keep only for mock mode. Make sure real mode uses backend connected-app endpoints and live proxies. |
| P1 | Tecniplast DVC integration mocks | `osoma/src/mocks/handlers/dvcIntegration.ts`, `osoma/src/mocks/handlers/tecniplast.ts`, `osoma/src/mocks/data/tecniplast.ts` | MSW mode returns mock metrics, cages, animals, tasks, downloads, operations, alarms, sensors, and room data. | Continue moving UI to `/connected_apps/{id}/dvc/*`, `/tp/*`, and real backend import/download endpoints. |
| P1 | Demo sensor panel | `osoma/src/features/demo-sensors/*`, `osoma/src/mocks/handlers/demoSensors.ts`, `osoma/src/mocks/data/demoSensors.ts` | Component calls real `/demo/sensors/*` API in real mode, but the feature is explicitly demo-labeled and mock-mode uses static demo sensor data. | Rename from `demo-sensors` to a neutral sensor-agent feature once the backend source is stable. Keep mock handler only for mock mode. |
| P2 | Subject imaging mock widget | `osoma/src/features/core/subjects/ImagingMocks.tsx` | Component has `MOCK_IMAGES` hardcoded in the page component. | Replace with an imaging/acquisition endpoint or hide the component until the backend exists. |
| P2 | Subject housing batch mock widget | `osoma/src/features/core/subjects/HousingBatches.tsx` | Component has `MOCK_BATCHES` and `MOCK_WATER` hardcoded. | Replace with real cage/batch/environment endpoints or hide the component until backend coverage exists. |
| P2 | Auth bypass session | `osoma/src/lib/auth.ts` | Bypass mode creates `mock-access-token`, `mock-user-id`, and `Mock Organization`. | Keep only for explicit auth bypass/dev mode. Never use for production login flows. |
| P2 | Profile placeholder avatar/default copy | `osoma/src/features/system/settings/ProfilePage.tsx` | Uses `/placeholder-avatar.jpg` and placeholder profile copy. | Wire to current user/profile endpoint or remove avatar until profile media exists. |
| P3 | Public HCM mock JSON | `osoma/public/mock/hcm/systemComparison.json` | Static mock HCM comparison payload. | Remove after HCM comparison is served by real backend/Tecniplast endpoints. |

## MSW-Only Fixture Inventory

These are intentionally mock-mode datasets and should not be imported by runtime
feature code outside `osoma/src/mocks`:

- `osoma/src/mocks/data/animals.ts`
- `osoma/src/mocks/data/assays.ts`
- `osoma/src/mocks/data/atmp.ts`
- `osoma/src/mocks/data/audit.ts`
- `osoma/src/mocks/data/cages.ts`
- `osoma/src/mocks/data/calendar.ts`
- `osoma/src/mocks/data/connectedApps.ts`
- `osoma/src/mocks/data/datasets.ts`
- `osoma/src/mocks/data/demoSensors.ts`
- `osoma/src/mocks/data/dvcScenarios.ts`
- `osoma/src/mocks/data/dvcVcg.ts`
- `osoma/src/mocks/data/investigationActivity.ts`
- `osoma/src/mocks/data/investigationDashboards.ts`
- `osoma/src/mocks/data/investigationExports.ts`
- `osoma/src/mocks/data/investigationOperators.ts`
- `osoma/src/mocks/data/investigations.ts`
- `osoma/src/mocks/data/keyboardShortcuts.ts`
- `osoma/src/mocks/data/organizations.ts`
- `osoma/src/mocks/data/samples.ts`
- `osoma/src/mocks/data/scenarios.ts`
- `osoma/src/mocks/data/studies.ts`
- `osoma/src/mocks/data/subjectCurationSuggestions.ts`
- `osoma/src/mocks/data/subjects.ts`
- `osoma/src/mocks/data/tecniplast.ts`
- `osoma/src/mocks/data/users.ts`
- `osoma/src/mocks/zefish/*`

## MSW Handler Coverage

Mock mode currently intercepts many `/api/*` endpoints, including:

- Core resources: `/api/projects`, `/api/experiments`, `/api/subjects`, `/api/cages`, `/api/procedures`, `/api/datasets`, `/api/users`, `/api/organizations`
- Animal workspace: `/api/animals/*`, `/api/investigations/:id/animals`
- Connected Apps: `/api/connected_apps*`, `/api/fair3r/*`, `/api/connected_apps/:id/dvc/*`
- Tecniplast operations: `/api/tp/*`, `/api/dvc/*`, `/api/nam/*`
- System features: `/api/calendar/events`, `/api/search`, `/api/audit`, `/api/feature-flags/*`
- AI/curation/lookups: `/api/ai/*`, `/api/curation/*`, `/api/lookups/*`
- Demo sensors: `/api/demo/sensors/*`

## Recommended Removal Order

1. Keep MSW as a deliberate mock mode, but prevent runtime feature modules from
   importing `@/mocks/*` directly.
2. Replace Zefix/Zefish direct mock imports with backend endpoints. This is the
   largest remaining production-mode mock surface.
3. Move the Connected Apps catalog to the backend so the UI no longer hardcodes
   app capabilities and defaults.
4. Hide or API-wire `ImagingMocks` and `HousingBatches`.
5. Rename/demo-gate sensor-agent UI once all sensor endpoints are production
   backed.
6. Remove static public mock payloads once their backend replacements are live.
