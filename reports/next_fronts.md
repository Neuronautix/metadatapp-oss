# Next Fronts (Repo-Aware)

**Owner:** Sensei (Orchestrator)

## Category 1: HIGH-VALUE EXTENSIONS
**A) DVC × Metadata Fusion Hardening**
- **Target**: `osoma/src/features/dvc/DvcMetadataDashboard.tsx` and `osoma/src/features/dvc/AnimalFilterPanel.tsx`
- **Goal**: Hook up the metadata fusion UI to real investigation/animal endpoints, discarding fake mock correlations. Ensure the filter panel utilizes actual backend taxonomy APIs.

**B) Curation Mapping Output**
- **Target**: `osoma/src/features/curation/DataCurationModule.tsx` and `osoma/src/features/curation/MappViewPage.tsx`
- **Goal**: Ensure the JSON-LD mapping outputs adhere strictly to the backend ingestion schemas. Validate via `curation.types.ts`.

## Category 2: STABILIZATION
**C) Tecniplast Integration API Parity**
- **Target**: `osoma/src/features/integrations/tecniplast/dvc/dvc.integration.api.ts`
- **Goal**: Replace manually crafted data models in the UI with strictly verified `dvc-proxy.generated.ts` types to ensure the frontend doesn't break when the proxy API updates.

**D) Core Entity Form Validation**
- **Target**: `osoma/src/features/core/investigations/InvestigationEditPage.tsx`
- **Goal**: Ensure Zod schemas align exactly with the API validation rules (e.g., max lengths, required fields) surfaced in OpenAPI.

## Category 3: WOW FEATURES
**E) Scenario Replay Integration**
- **Target**: `osoma/src/features/core/scenario/ScenarioBuilder.tsx`
- **Goal**: Feed the scenario replay engine real event streams via `/api/events` instead of static mocks. This will provide a visually striking, time-travel visualization of animal health states using the actual dataset.
