# Phase 6 — UI Display Check

## Scope checked
- Extraction UI component render gates
- Props/state mapping for DVC wizard
- Empty-state/error display behavior
- Timeseries/chart constraints relevant to this issue

## Findings

### 1) Immediate UI failure point was pre-chart
- Failure occurred at connection gate in `DVCIntegrationTasks`:
  - API key test failed → `isProxyValid=false`
  - component rendered disconnect `EmptyState`
- This blocked the entire extraction wizard before metrics/cage selection/task monitoring.

### 2) Rendering logic itself is coherent
- Wizard step transitions and guards are consistent.
- Search/table rendering expects valid cages array and displays status/date fields safely.
- Task monitoring table handles active/completed states with polling.

### 3) Error visibility improved
- Added `proxyError` rendering in empty-state description.
- Added DEV-only payload debug logs for early diagnostics.

### 4) Common chart/timeseries issues not root in this incident
- Not a timestamp parse crash.
- Not undefined dataset in chart components.
- Not a mismatch between series schema and chart props for this specific observed failure.

---

## Agent mandatory output

### Files inspected
- `osoma/src/features/integrations/tecniplast/dvc/DVCIntegrationTasks.tsx`
- `osoma/src/features/integrations/tecniplast/dvc/AnalyticsHubPage.tsx`
- `osoma/src/features/integrations/tecniplast/dvc-observatory/DVCObservatoryTab.tsx`

### Reproduction steps
1. Open Analytics Hub (Live API mode).
2. Observe immediate API key test behavior.
3. On failure, UI remains at disconnected state and no later visualization steps are reachable.

### Fix proposal
- Ensure upstream call reaches backend route; once fixed, UI gate unblocks (implemented via route alignment).
