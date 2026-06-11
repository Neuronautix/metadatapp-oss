# Phase 4 — Data Pipeline Check

## Pipeline traced

Raw DVC API response
→ backend proxy payload (`TecniplastProxyController`)
→ frontend API client typed payload (`dvc.integration.api.ts`)
→ React state in `DVCIntegrationTasks`

## Checks performed

### Field presence
- `metrics`: expects `id`, `description`, `type`.
- `cages/search`: frontend expects wrapped response `{ cages, min_date, max_date }` and supports fallback if array.
- `task state`: expects array of objects with `id`, `state`, timestamps.

### Timestamp format
- DVC OpenAPI specifies ISO date-time strings for event/timeline fields.
- Backend preserves values; frontend converts to display via `new Date(...).toLocaleDateString()`.

### Cage/mouse identifiers
- Frontend submits `humanReadableId` from validated cages.
- Backend normalizes cage IDs (`_` to `-` suffix case) before upstream search.

### Empty dataset behavior
- `DVCIntegrationTasks` handles empty validated cages and blocks submit.
- Polling only activates with active tasks.

## Findings

- No schema-breaking transformation mismatch identified in this path.
- The observed production failure occurred **before** data transformation (request could not hit intended backend route layer).

---

## Agent mandatory output

### Files inspected
- `api/src/Controller/Api/TecniplastProxyController.php`
- `osoma/src/features/integrations/tecniplast/dvc/dvc.integration.api.ts`
- `osoma/src/features/integrations/tecniplast/dvc/DVCIntegrationTasks.tsx`
- Archived Tecniplast DVC Analytics OpenAPI contract (removed from repository after investigation)

### Reproduction steps
1. Validate cages in live wizard.
2. Confirm `result.cages` parsing and date bounds mapping.
3. Submit task and monitor task state polling.

### Fix proposal
- No pipeline mapping change required for the root-cause defect.
