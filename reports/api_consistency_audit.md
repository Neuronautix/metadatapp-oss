# API Consistency Audit

**Owner:** Integration Blade & Data Monk
**Status:** VALIDATION PHASE
**Date:** 2026-04-02

## 1. Core API Alignment (Hydra / JSON-LD)
- **`osoma/src/lib/api.ts`** enforces the standard API Platform / Hydra structure via `apiFetchHydraCollection` and `apiFetchHydraMapped`.
- **Validation**: Mocks in `osoma/src/mocks/handlers/` and mock data in `osoma/src/mocks/data/` must wrap collection responses in `hydra:member` and include `hydra:totalItems`.
- **Finding**: The mock handlers broadly comply with this structure, but we must continually audit the mocked entity fields against `api/public/docs.json` (OpenAPI spec).

## 2. Type Consistency
- OpenAPI types are generated via `pnpm run openapi:types:dvc-proxy` into `osoma/src/domain/dvc/dvc-proxy.generated.ts` and `pnpm run openapi:types:official` into `osoma/src/metadatapp/openapi-types.ts`.
- **Finding**: We have defined custom UI types in modules like `osoma/src/features/core/investigations/investigation.types.ts`.
- **Action**: Must ensure UI models strictly map to the generated OpenAPI types to prevent "fake field" drift between MSW and the real API.

## 3. Endpoints & Mocks Parity
### Core Entities
- `GET /api/investigations`
- `GET /api/studies`
- `GET /api/subjects`
- `GET /api/assays`
- `GET /api/datasets`
- **Status**: Mocks exist in `osoma/src/mocks/handlers/investigations.ts`, etc. Consistency looks high, but we need to verify edge cases in error responses (e.g. 400 Bad Request structure).

### DVC & Integrations
- `GET /api/dvc/analytics/...`
- **Status**: The integration API client `osoma/src/features/integrations/tecniplast/dvc/dvc.integration.api.ts` must use standard `apiFetch` with correct headers to avoid CORS/Auth issues.

## 4. Auth Header & Error Handling
- `apiFetch` automatically injects `Bearer ${token}` via `AuthService`.
- Error handling in `apiFetch` falls back to `hydra:description` or `detail`.
- **Action**: MSW mock errors should reproduce standard API Platform JSON-LD error shapes.

## Conclusion
The foundation is strong. The next step is enforcing strict typing based on generated OpenAPI definitions rather than manually declaring `interface Investigation` in feature folders where they might diverge from the backend schema.
