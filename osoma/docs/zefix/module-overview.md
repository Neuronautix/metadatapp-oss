# Zefix Module Overview

## Purpose

This document freezes the current Zefix frontend contract implemented in `osoma/src/features/zefix`, `osoma/src/domain/zefish`, and `osoma/src/mocks/zefish`.

It is the canonical handoff for backend implementation of the current frontend behavior. The goal is to let backend work start without rereading the frontend code.

## Current State

- The entire Zefix module is frontend-only today.
- All reads and writes flow through `osoma/src/domain/zefish/zefish.api.ts`.
- That file is backed by local mocks from `osoma/src/mocks/zefish`.
- There is no backend API contract yet.
- Some values are pure read models derived in `osoma/src/domain/zefish/zefish.utils.ts`, not persisted source records.

## Route Inventory

| Route | Page | Current frontend data dependency | Scope |
| --- | --- | --- | --- |
| `/zefix` | Dashboard | `getZefixOverview()`, `getZefishAlerts('active')` | MVP |
| `/zefix/lines` | Lines list | `getZefishLines(search)` | MVP |
| `/zefix/lines/:lineID` | Line detail | `getZefishLineDetail(lineID)` | MVP |
| `/zefix/batches` | Batches list | `getZefishBatches(search)` | MVP |
| `/zefix/batches/:batchID` | Batch detail | `getZefishBatchDetail(batchID)` | MVP |
| `/zefix/systems` | Systems | `getZefishSystems()`, `getZefishSystemHistory(systemID)` | MVP |
| `/zefix/rooms` | Rooms | `getZefishRooms()` | MVP |
| `/zefix/alerts` | Alerts | `getZefishAlerts()`, `acknowledgeZefishAlert()`, `resolveZefishAlert()`, `triggerTemperatureAlert()` | MVP for read plus acknowledge/resolve, phase 2 or dev-only for manual trigger |
| `/zefix/location` | Location explorer | `getLocationExplorerData()` | MVP |

## Source Data Files

| File | Exports | Role |
| --- | --- | --- |
| `osoma/src/mocks/zefish/lines.ts` | `zefishLines` | Base line records |
| `osoma/src/mocks/zefish/batches.ts` | `zefishBatches` | Base batch records plus embedded lifecycle events |
| `osoma/src/mocks/zefish/systems.ts` | `zefishFacilityName`, `zefishRooms`, `zefishSystems`, `zefishLocations`, `sensorRecords`, `roomEnvironmentRecords` | Facility, systems, rooms, locations, telemetry |
| `osoma/src/mocks/zefish/alerts.ts` | `zefishAlerts` | Seed alert stream |

## Frontend Service Contract

The frontend is already coded against this logical service surface:

| Current function | Suggested backend read/write shape |
| --- | --- |
| `getZefixOverview()` | `GET /zefix/overview` returning `ZefixOverviewDto` |
| `getZefishLines(search)` | `GET /zefix/lines?search=` returning `ZefishLineSummary[]` |
| `getZefishLineDetail(lineID)` | `GET /zefix/lines/{lineID}` returning `ZefishLineDetail` |
| `getZefishBatches(search)` | `GET /zefix/batches?search=` returning `ZefishBatchView[]` |
| `getZefishBatchDetail(batchID)` | `GET /zefix/batches/{batchID}` returning `ZefishBatchDetail` |
| `getZefishSystems()` | `GET /zefix/systems` returning `ZefishSystemSnapshot[]` |
| `getZefishSystemHistory(systemID)` | `GET /zefix/systems/{systemID}/history` returning `SystemHistoryPoint[]` |
| `getZefishRooms()` | `GET /zefix/rooms` returning `RoomSnapshot[]` |
| `getZefishAlerts(status?)` | `GET /zefix/alerts?status=` returning `ZefishAlert[]` |
| `acknowledgeZefishAlert(alertID)` | `POST /zefix/alerts/{alertID}/acknowledge` |
| `resolveZefishAlert(alertID)` | `POST /zefix/alerts/{alertID}/resolve` |
| `triggerTemperatureAlert()` | Dev-only mutation or phase-2 simulation endpoint, not a production MVP requirement |
| `getLocationExplorerData()` | `GET /zefix/location-explorer` returning `LocationExplorerData` |

The endpoint names above are inferred from current frontend usage. The required part is the payload shape, not the exact path naming.

## Data Model Freeze

### 1. Lines

Base source model: `ZefishLine`

| Frontend field | Type | Meaning | Suggested backend field |
| --- | --- | --- | --- |
| `lineID` | `string` | Stable line identifier used in routes and joins | `id` or `lineId` |
| `name` | `string` | Display name | `name` |
| `genotype` | `string` | Genotype label | `genotype` |
| `origin` | `string` | Provenance text | `origin` |
| `description` | `string` | Free text summary | `description` |
| `creationDate` | `string` ISO datetime | Line creation date | `createdAt` or `creationDate` |
| `responsibleScientist` | `string` | Owner or responsible scientist | `responsibleScientist` |

Derived read model: `ZefishLineSummary`

| Frontend field | Type | Meaning | Backend note |
| --- | --- | --- | --- |
| `totalAnimals` | `number` | Sum of `individualCount` across related batches | Derived |
| `activeBatches` | `number` | Count of related batches with `individualCount > 0` | Derived |
| `mortalityRate` | `number` | `(total mortalities / total initial animals) * 100`, rounded to 1 decimal | Derived |
| `reproductionStatus` | `'Breeding' | 'Stable' | 'Watch' | 'Paused'` | Derived label used in badges | Derived |

Current reproduction status rules:

- `Paused` if `activeBatches === 0`
- `Watch` if `mortalityRate >= 14`
- `Breeding` if `activeBatches >= 4`
- otherwise `Stable`

The current implementation applies those rules in that order, so `Watch` overrides `Breeding`, and `Paused` overrides both.

### 2. Batches

Base source model: `ZefishBatch`

| Frontend field | Type | Meaning | Suggested backend field |
| --- | --- | --- | --- |
| `batchID` | `string` | Stable batch identifier used in routes | `id` or `batchId` |
| `lineID` | `string` | Foreign key to line | `lineId` |
| `birthDate` | `string` ISO datetime | Batch birth date | `birthDate` |
| `sexRatio` | `{ male, female, unknown }` | Current sex distribution counts | `sexRatio` object |
| `initialCount` | `number` | Starting population | `initialCount` |
| `individualCount` | `number` | Current live population | `currentCount` or `individualCount` |
| `locationId` | `string` | Current tank/location identifier | `locationId` |
| `systemID` | `string` | Current system identifier | `systemId` |
| `roomID` | `string` | Current room identifier | `roomId` |
| `events` | `BatchEvent[]` | Lifecycle event log | `events[]` |

Batch event variants:

| Event type | Fields |
| --- | --- |
| `birth` | `id`, `type`, `date`, `count` |
| `transfer` | `id`, `type`, `date`, `count`, `fromLocationId`, `toLocationId` |
| `mortality` | `id`, `type`, `date`, `number`, `reason` |

Allowed mortality reasons today:

- `natural`
- `disease`
- `experimental endpoint`
- `unknown`
- `system issue`

Derived read model: `ZefishBatchView`

| Frontend field | Type | Meaning | Backend note |
| --- | --- | --- | --- |
| `lineName` | `string` | Denormalized line display name | Derived join |
| `roomName` | `string` | Denormalized room display name | Derived join |
| `tankLocation` | `string` | Concatenated `facility / roomName / systemID / rack / tank` | Derived formatting |
| `ageDays` | `number` | `floor((now - birthDate) / day)` | Derived |
| `ageLabel` | `string` | Human display from `ageDays` | Derived |
| `mortalityCount` | `number` | Sum of mortality event counts | Derived |

Current `ageLabel` rules:

- `< 31` days: `"X days"`
- `< 365` days: `"X months"` using `floor(ageDays / 30)`
- otherwise `"X years"` using `floor(ageDays / 365)`

Derived detail DTO: `ZefishBatchDetail`

| Field | Type | Meaning |
| --- | --- | --- |
| `batch` | `ZefishBatchView` | Main batch read model |
| `line` | `ZefishLine` | Joined line metadata |
| `location` | `ZefishLocation` | Joined current location |
| `timeline` | `BatchTimelinePoint[]` | UI-ready timeline nodes from `events[]` |
| `mortalityEvents` | `BatchMortalityEvent[]` | Filtered mortality-only event list |

### 3. Systems

Base source model: `ZefishSystem`

| Frontend field | Type | Meaning | Suggested backend field |
| --- | --- | --- | --- |
| `systemID` | `string` | Stable system identifier | `id` or `systemId` |
| `manufacturer` | `string` | Vendor name | `manufacturer` |
| `model` | `string` | System model | `model` |
| `roomID` | `string` | Parent room identifier | `roomId` |
| `roomName` | `string` | Parent room display name | `roomName` |
| `waterTemperature` | `number` | Current water temperature | `waterTemperature` |
| `pH` | `number` | Current pH | `pH` |
| `conductivity` | `number` | Current conductivity | `conductivity` |
| `oxygen` | `number` | Current dissolved oxygen | `oxygen` |
| `waterFlow` | `number` | Current water flow | `waterFlow` |
| `filtrationStatus` | `'OK' | 'MAINTENANCE' | 'FAILURE'` | Current filtration state | `filtrationStatus` |

Derived read model: `ZefishSystemSnapshot`

| Frontend field | Type | Meaning | Backend note |
| --- | --- | --- | --- |
| `activeBatches` | `number` | Related batches count | Derived |
| `fishCount` | `number` | Sum of related `individualCount` | Derived |
| `activeAlerts` | `number` | Count of active alerts on the system or its batches | Derived |

Telemetry history DTO: `SystemHistoryPoint`

| Field | Type | Meaning |
| --- | --- | --- |
| `timestamp` | `string` ISO datetime | Telemetry timestamp |
| `temperature` | `number` | Water temperature at that timestamp |
| `pH` | `number` | pH at that timestamp |

Important current contract detail:

- The systems page only charts `temperature` and `pH`.
- The raw mock telemetry source contains more fields, but the current frontend history DTO does not expose them.

### 4. Rooms

Base source model: `ZefishRoom`

| Frontend field | Type | Meaning | Suggested backend field |
| --- | --- | --- | --- |
| `roomID` | `string` | Stable room identifier | `id` or `roomId` |
| `roomName` | `string` | Display name | `name` or `roomName` |
| `facility` | `string` | Facility label | `facility` |
| `lightCycle` | `string` | Light schedule label | `lightCycle` |

Derived read model: `RoomSnapshot`

| Frontend field | Type | Meaning | Backend note |
| --- | --- | --- | --- |
| `room` | `ZefishRoom` | Embedded room metadata | Nested resource or DTO |
| `roomTemperature` | `number` | Latest room temperature | Derived from environment records |
| `humidity` | `number` | Latest humidity | Derived from environment records |
| `lightCycle` | `string` | Latest or fallback light cycle | Derived |
| `noiseLevel` | `number` | Latest noise level | Derived from environment records |
| `mortality30d` | `number` | Sum of mortality counts in room over last 30 days | Derived |
| `breedingEvents30d` | `number` | Count of `birth` events in room over last 30 days | Derived |

### 5. Locations

Base source model: `ZefishLocation`

| Frontend field | Type | Meaning | Suggested backend field |
| --- | --- | --- | --- |
| `locationId` | `string` | Stable tank/location identifier | `id` or `locationId` |
| `facility` | `string` | Facility label | `facility` |
| `roomID` | `string` | Parent room identifier | `roomId` |
| `roomName` | `string` | Parent room display name | `roomName` |
| `systemID` | `string` | Parent system identifier | `systemId` |
| `rack` | `string` | Rack label | `rack` |
| `tank` | `string` | Tank label | `tank` |

Location explorer read model hierarchy:

| DTO | Fields | Meaning |
| --- | --- | --- |
| `TankSnapshot` | `location`, `batch`, `alerts` | Single tank occupancy plus active alerts |
| `LocationRackNode` | `rack`, `tanks` | Rack grouping |
| `LocationSystemNode` | `systemID`, `roomID`, `roomName`, `racks` | System grouping |
| `LocationRoomNode` | `roomID`, `roomName`, `systems` | Room grouping |
| `LocationExplorerData` | `facility`, `rooms` | Top-level explorer payload |

Important current contract details:

- Each tank can hold at most one displayed batch.
- `batch` is nullable and the UI explicitly supports empty tanks.
- Only active alerts are surfaced in the explorer.

### 6. Alerts

Base source model: `ZefishAlert`

| Frontend field | Type | Meaning | Suggested backend field |
| --- | --- | --- | --- |
| `alertID` | `string` | Stable alert identifier | `id` or `alertId` |
| `type` | `string` | Human-readable alert category | `type` |
| `severity` | `'INFO' | 'WARNING' | 'CRITICAL'` | Severity | `severity` |
| `status` | `'active' | 'acknowledged' | 'resolved'` | Workflow state | `status` |
| `affectedEntityType` | `'batch' | 'system' | 'room' | 'sensor' | 'tank'` | Entity class | `affectedEntityType` |
| `affectedEntityId` | `string` | Target entity identifier | `affectedEntityId` |
| `affectedLabel` | `string` | Human-readable label shown in UI | `affectedLabel` |
| `timestamp` | `string` ISO datetime | Alert occurrence time | `createdAt` or `timestamp` |
| `recommendedAction` | `string` | Operator-facing remediation text | `recommendedAction` |

Current alert behavior:

- Alerts are the merge of seeded mock alerts and computed rule-based alerts.
- De-duplication is by `alertID`.
- Alert lists are sorted descending by `timestamp`.
- Filtering by `status` is supported by the frontend contract.

Current computed alert rules:

- System temperature outside range if latest `waterTemperature < 26` or `> 29`
- Temperature severity is `CRITICAL` when `waterTemperature > 30`, otherwise `WARNING`
- Filtration alert when latest `filtrationStatus !== 'OK'`
- Sensor missing data when latest telemetry point is older than 120 minutes
- Batch mortality alert when `totalMortality / initialCount >= 0.18`
- Mortality severity is `CRITICAL` when the ratio is `>= 0.25`, otherwise `WARNING`

## Overview DTO Freeze

The dashboard depends on this exact DTO today:

| Field | Type | Meaning | Backend note |
| --- | --- | --- | --- |
| `totalLines` | `number` | Total line count | Derived |
| `totalBatches` | `number` | Total batch count | Derived |
| `totalFish` | `number` | Sum of current fish across batches | Derived |
| `todaysMortalities` | `number` | Mortality count since local start of day | Derived |
| `activeAlerts` | `number` | Count of active alerts | Derived |
| `populationByLine` | `{ line, fish }[]` | Chart data | Derived |
| `mortalityTrend` | `{ date, mortalities }[]` | Last 14 days | Derived |
| `temperatureTrend` | `{ timestamp, temperature }[]` | Currently fixed to `SYS-02` | Derived |
| `breedingOutput` | `{ period, births }[]` | Dashboard chart data | Currently synthetic mock data |

Important current contract details:

- `mortalityTrend` is a 14-day series.
- `temperatureTrend` is hard-coded to `SYS-02` in the current frontend logic.
- `breedingOutput` is not derived from persisted mock source records. It is synthetic placeholder data.

## MVP vs Phase 2

### MVP

- Lines list and detail
- Batches list and detail
- Systems snapshot and per-system temperature and pH history
- Rooms snapshot
- Alerts list with status filtering
- Alert acknowledge and resolve actions
- Location explorer read model
- Dashboard KPIs
- Dashboard population, mortality, and temperature charts

### Phase 2 or explicitly non-canonical for first backend slice

- Manual `triggerTemperatureAlert()` action from the alerts page
- Synthetic `breedingOutput` series as currently implemented
- Any backend assumption that the dashboard must stay pinned to `SYS-02` forever
- Any write workflow beyond alert acknowledgement and resolution

## Mock-Only Actions That Need Real Persistence

These actions mutate only in-memory frontend state today and will reset on reload:

| Current action | Current implementation | Backend requirement |
| --- | --- | --- |
| Acknowledge alert | Mutates `runtimeAlerts` in `zefish.api.ts` | Persist alert workflow state |
| Resolve alert | Mutates `runtimeAlerts` in `zefish.api.ts` | Persist alert workflow state |
| Trigger temperature alert | Prepends a synthetic alert into `runtimeAlerts` | Treat as dev-only simulation, not required for production MVP |

## Recommended Backend DTO Set

These names are inferred from the current frontend contract and are intended to keep responsibilities clear:

- `ZefixOverviewDto`
- `ZefishLineDto`
- `ZefishLineSummaryDto`
- `ZefishLineDetailDto`
- `ZefishBatchDto`
- `ZefishBatchViewDto`
- `ZefishBatchDetailDto`
- `BatchEventDto`
- `ZefishSystemDto`
- `ZefishSystemSnapshotDto`
- `SystemHistoryPointDto`
- `ZefishRoomDto`
- `RoomSnapshotDto`
- `ZefishLocationDto`
- `TankSnapshotDto`
- `LocationExplorerDto`
- `ZefishAlertDto`

## Backend Implementation Notes

- The frontend already assumes string identifiers, not numeric IDs.
- All displayed dates and timestamps are ISO strings.
- Several page payloads are read models, not 1:1 persistence entities.
- If backend payload names differ from this document, the frontend will need an adapter or refactor.
- The safest first backend slice is to preserve these DTO shapes exactly, then refactor later once the contract is stable on both sides.
