# Osoma Repository Alignment

**Owner:** Repo Archaeologist & Sensei
**Status:** ACTIVE

## 1. Extension Points Per Module

### Core Domain (`osoma/src/features/core/`)
- Contains the central entities: Investigations, Studies, Assays, Subjects, Samples.
- **Extension Point**: New core entities should be added here, alongside their `.api.ts` (data fetching) and `.types.ts` files. React queries belong here.

### DVC Metadata Fusion vs. DVC Integration
- `osoma/src/features/dvc/`: The **Metadata Fusion** layer. This is where cross-cutting concerns linking DVC metrics to Core Metadatapp entities live (e.g., `DvcMetadataDashboard.tsx`).
- `osoma/src/features/integrations/tecniplast/dvc/`: The **Raw Integration** layer. Specific analytics, periodograms, actograms, and data worker logic (`dvcDataWorker.ts`) stay here.

### System & Settings (`osoma/src/features/system/`)
- User management, Feature Flags, RBAC, Auth.
- **Extension Point**: To add a new tenant-level setting, hook into `FeatureFlagProvider` or create a new route under `SettingsLayout.tsx`.

### Zebrafish Management (`osoma/src/features/zefix/`)
- Independent domain for specific husbandry operations (Batches, Lines, Cryo).
- Uses standard layout patterns but isolates its business logic.

## 2. Reusable Infrastructure
- **API Client**: `osoma/src/lib/api.ts` is the sole mechanism for network requests.
- **State Management**: `@tanstack/react-query` configured in `osoma/src/app/providers.tsx`.
- **UI System**: `osoma/src/components/ui/` contains standard Tailwind + Radix primitives.

## 3. Integration Boundaries
- **Connected Apps**: `osoma/src/features/integrations/connected-apps/` defines the boundaries for pushing/pulling data (e.g., private integration).
- API Adapters must live near the domain they integrate with, utilizing `apiFetch` and exposing strict Promise-based interfaces for TanStack Query.
