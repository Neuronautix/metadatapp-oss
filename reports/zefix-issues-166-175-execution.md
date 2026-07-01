# Zefix Issues 166-175 Execution Log

This file is the working record for the sequential delivery of GitHub issues `#166` through `#175`.

## Operating Rules

- Process one issue at a time.
- Keep one branch and one PR per issue.
- Run `castor qa:all` before opening each PR.
- Run `pnpm build` in `osoma/` before opening each PR.
- Add an issue comment summarizing scope and checks once the PR is open.
- Close the issue only after the PR is pushed and linked.

## Planned Sequence

1. `#166` Back Zefix Lines pages with real API
2. `#167` Back Zefix Batches pages and add create-batch workflow
3. `#168` Persist mortality records and batch population event history
4. `#170` Add environmental observations and real Systems/Rooms data
5. `#171` Implement Zefix alerts persistence and workflow
6. `#169` Back the Zefix dashboard with real aggregate endpoints
7. `#172` Add first concrete Zefix CSV exports and one PDF report
8. `#173` Enforce Zefix roles and permissions
9. `#174` Add CRYO conservation tracking for zebrafish lines
10. `#175` Add API and end-to-end coverage for core Zefix workflows

## Status Board

| Issue | Title | Status | Notes |
| --- | --- | --- | --- |
| `#166` | Back Zefix Lines pages with real API | PR opened, issue closed | Draft PR `#180` opened from `dhuzard/issue166`; issue commented and closed. |
| `#167` | Back Zefix Batches pages and add create-batch workflow | PR opened, issue closed | Draft PR `#181` opened from `dhuzard/issue167`; issue commented and closed. |
| `#168` | Persist mortality records and batch population event history | PR opened, issue closed | Draft PR `#182` opened from `dhuzard/issue168`; issue commented and closed. |
| `#170` | Add environmental observations and real Systems/Rooms data | PR opened, issue closed | Draft PR `#183` opened from `dhuzard/issue170`; issue commented and closed after backend/frontend validation passed. |
| `#171` | Implement Zefix alerts persistence and workflow | PR opened, issue closed | Draft PR `#184` opened from `dhuzard/issue171`; issue commented and closed after backend/frontend validation passed. |
| `#169` | Back the Zefix dashboard with real aggregate endpoints | PR opened, issue closed | Draft PR `#185` opened from `dhuzard/issue169`; issue commented and closed after backend/frontend validation passed. |
| `#172` | Add first concrete Zefix CSV exports and one PDF report | PR opened, issue closed | Draft PR `#188` opened from `dhuzard/issue172`; issue commented and closed after backend/frontend validation passed. |
| `#173` | Enforce Zefix roles and permissions | PR opened, issue closed | Draft PR `#189` opened from `dhuzard/issue173`; issue commented and closed after backend/frontend validation passed. |
| `#174` | Add CRYO conservation tracking for zebrafish lines | In progress | Branch `dhuzard/issue174` will start from the validated `#173` head. |
| `#175` | Add API and end-to-end coverage for core Zefix workflows | Pending | Hardening pass across completed slices. |

## Issue Log

### `#166`

- Current branch: `dhuzard/issue166`
- Initial local state:
  - modified `api/src/Zefix/Lines/State/ZefixLineProvider.php`
  - untracked `api/tests/Functional/ZefixLineProviderTest.php`
- Completed:
  - verified the line collection and detail contract end-to-end
  - added API-level regression coverage for collection, detail, and cross-account access
  - fixed JSON serialization and API Platform typing issues around the Zefix line DTO resources
  - updated the frontend Zefix line client to request the DTO format explicitly
  - repaired local QA blockers caused by stale Docker buildx plugin path and root-owned build/cache artifacts
- Validation:
  - `docker compose ... vendor/bin/phpunit tests/Functional/ZefixLineProviderTest.php tests/Api/ZefixResourceTest.php`
  - `pnpm build` in `osoma/`
  - `castor qa:all`
- Next:
  - draft PR opened: `#180`
  - issue comment posted with validation summary
  - issue `#166` closed

### `#167`

- Current branch: `dhuzard/issue167`
- Starting point:
  - branched from the validated `#166` head so batch and line contracts stay aligned
- Completed:
  - added real `/zefix/batches` collection and detail DTO endpoints backed by persisted `ZebrafishBatch` data
  - enforced server-side batch creation rules so only empty available pools can receive an active or quarantined batch
  - synchronized pool occupancy status updates when batches are created or lifecycle state changes
  - extended the location explorer occupancy provider to treat quarantined batches as occupying a pool
  - replaced `getZefishBatches` / `getZefishBatchDetail` mock reads with real API fetches from the Zefix batch endpoints
  - added a `CreateBatchDialog` flow in the location explorer for empty, available pools
  - wired query invalidation for batches, batch detail, location explorer, lines, systems, rooms, alerts, and overview after create
- Validation:
  - `docker compose ... vendor/bin/phpunit tests/Api/ZefixResourceTest.php`
  - `pnpm build` in `osoma/`
  - `castor qa:all`
- Next:
  - draft PR opened: `#181`
  - issue comment posted with validation summary
  - issue `#167` closed

### `#168`

- Current branch: `dhuzard/issue168`
- Starting point:
  - branched from the validated `#167` head so mortality persistence can reuse the real batch detail and create workflow
- Delivered scope:
  - added persisted `MortalityRecord` storage with authenticated recorder attribution and population guards
  - recomputed current batch population, line mortality summaries, and batch history from stored mortality entries
  - added `/zefix/mortality-trends` aggregation by line, room, and plateau
  - replaced the mock mortality log in Osoma batch detail with a persisted entry form and refreshed timeline/history
- Validation:
  - `pnpm build` in `osoma/`
  - `castor qa:all`
  - targeted API coverage in `tests/Api/ZefixResourceTest.php` and `tests/Api/MortalityRecordTest.php`
- Next:
  - draft PR opened: `#182`
  - issue comment posted with validation summary
  - issue `#168` closed

### `#170`

- Current branch: `dhuzard/issue170`
- Delivered scope:
  - added persisted `System` and `EnvironmentalObservation` backend models with account/user scoping and a migration covering `system`, `environmental_observation`, and nullable `rack.system`
  - added real Zefix DTO endpoints for `/zefix/systems`, `/zefix/systems/{systemID}/history`, `/zefix/rooms`, and `/zefix/environmental-observations`
  - aligned line and batch location projections so `systemID` resolves from the persisted rack-to-system relation with rack-code fallback
  - replaced the demo-only Osoma systems and rooms views with backend-backed queries for persisted environmental observations
  - added a shared manual observation form for temperature and pH entry
  - drove the systems and rooms trend charts from observation history rather than static mock data
  - surfaced the latest persisted observation timestamp in the selected system and room detail cards
  - kept the mock data path working by overlaying runtime observation state in Osoma-only mode
- Validation:
  - `castor phpunit --filter=ZefixEnvironmentalObservationTest`
  - `castor qa:all`
  - `pnpm build` in `osoma/`
- Next:
  - draft PR opened: `#183`
  - issue comment posted with validation summary
  - issue `#170` closed

### `#171`

- Current branch: `dhuzard/issue171`
- Delivered scope:
  - added persisted `Alert` backend storage with acknowledge/resolve timestamps and account-scoped Zefix alert DTO endpoints
  - implemented rule evaluation for temperature outside range, pH outside range, and high mortality in batch from persisted environmental observations and mortality records
  - updated system snapshots to report active alert counts from persisted alert state
  - replaced the frontend-only Zefix alert stream with real alert fetch and persisted acknowledge/resolve actions while keeping mock mode working
  - updated the dashboard spotlight and active alert stat to use the real alert stream consistently
  - surfaced active alert badges in the location explorer by overlaying the persisted alert stream onto the explorer hierarchy
- Validation:
  - `castor phpunit --filter=ZefixAlertTest`
  - `castor qa:all`
  - `pnpm build` in `osoma/`
- Next:
  - draft PR opened: `#184`
  - issue comment posted with validation summary
  - issue `#171` closed

### `#169`

- Current branch: `dhuzard/issue169`
- Delivered scope:
  - added real `/zefix/overview` aggregate endpoint covering active lines, current batches, total fish, today’s mortalities, active alerts, population by line, mortality trend, breeding output, and recent temperature history
  - replaced the dashboard’s real-mode mock overview source with the persisted overview endpoint while preserving mock mode fallback
  - aligned the dashboard KPI cards and spotlight so the KPI alert count comes from the overview aggregate and the spotlight continues to use the active alert stream
- Validation:
  - `castor phpunit --filter=ZefixOverviewTest`
  - `castor qa:all`
  - `pnpm build` in `osoma/`
- Next:
  - draft PR opened: `#185`
  - issue comment posted with validation summary
  - issue `#169` closed

### `#172`

- Current branch: `dhuzard/issue172`
- Starting point:
  - branched from the validated `#169` head so exports can rely on real lines, batches, mortality, location, and overview aggregates
  - issue scope confirmed from GitHub: CSV exports for pool occupancy, batch history, line statistics, and mortality logs; one basic PDF report; all outputs must match current filters or selected scope
- Delivered scope:
  - added concrete download endpoints for pool occupancy, batch history, line statistics, mortality logs, and a basic line PDF report
  - reused the existing Zefix entity/state model to keep export values aligned with current persisted account data and UI scopes
  - wired Osoma download actions into the existing lines list, batches list, line detail, batch detail, and location explorer pages so each export follows the active filter or selected scope
  - added targeted API coverage for the download responses, headers, and scoped filtering behavior
- Validation:
  - `castor phpunit --filter=ZefixExportControllerTest`
  - `castor qa:all`
  - `pnpm build` in `osoma/`
- Next:
  - draft PR opened: `#188`
  - issue comment posted with validation summary
  - issue `#172` closed

### `#173`

- Current branch: `dhuzard/issue173`
- Starting point:
  - branched from the validated `#172` head so permission checks can cover the now-live mutation and export surfaces
  - issue scope confirmed from GitHub: backend-defined admin/editor/viewer access for Zefix resources and mutations, frontend hiding or disabling of restricted actions, and server-side enforcement as the source of truth
- Delivered scope:
  - mapped the existing repo auth model onto Zefix semantics as `ROLE_USER => viewer`, `ROLE_ADMIN => editor`, and `ROLE_SUPER_ADMIN => admin`, without introducing a broader RBAC redesign
  - enforced elevated backend access for Zefix mutations and download surfaces including batch creation, mortality persistence, environmental observations, alert acknowledge/resolve actions, CSV exports, and PDF reports
  - tightened the mutable API Platform operations on core Zefix infrastructure entities so viewers remain read-only on the server side
  - added dedicated API coverage proving viewers can read Zefix resources but cannot mutate or export, while elevated users can perform the intended write and download flows
  - updated the Osoma Zefix pages to hide export controls and replace mutation surfaces with read-only messaging for viewers while preserving full view access
- Validation:
  - `castor phpunit --filter=ZefixPermissionTest`
  - `castor qa:all`
  - `pnpm build` in `osoma/`
- Next:
  - draft PR opened: `#189`
  - issue comment posted with validation summary
  - issue `#173` closed

### `#174`

- Current branch: `dhuzard/issue174`
- Starting point:
  - branched from the validated `#173` head so CRYO inventory can reuse the real line detail surface, the export path, and the finalized permission model
  - issue scope confirmed from GitHub: add `CryoRecord` linked to `ZebrafishLine`, capture storage metadata and status, expose a simple create/view workflow, and export the inventory if that remains straightforward inside this issue
- Initial implementation focus:
  - add the persisted backend model and line-linked query surface first
  - extend the line-level frontend with a focused CRYO inventory view and creation flow
