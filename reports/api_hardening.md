# API Hardening Plan

**Owner:** Integration Blade & Mock Master
**Status:** PRIORITY 0

## Objective
Ensure that `osoma/src/lib/api.ts` correctly aligns with `osoma/src/mocks/handlers/*` and the generated OpenAPI domain types. The mock data must perfectly mirror real backend responses.

## Validation Checklist
1. **Endpoint Naming**: Do `osoma/src/mocks/handlers/investigations.ts` URLs exactly match `/api/investigations`? Yes.
2. **Payload Shapes**: Are all `hydra:member` arrays typed properly in the frontend? We must remove inline declarations like `interface Investigation` and prefer types from `osoma/src/metadatapp/openapi-types.ts` if available.
3. **Pagination**: Verify that MSW mocks return `hydra:totalItems` and `hydra:view` where expected by `toPaginatedResponse` in `api.ts`.
4. **Error Responses**: All HTTP 400/4xx mock responses must return `{"hydra:description": "..."}` or `{"detail": "..."}` so `apiFetch` parses the error consistently.

## Required Tasks
- [ ] **Task 1**: Audit `osoma/src/mocks/data/` (e.g., `investigations.ts`) against the real API Platform JSON-LD response. Ensure fields like `@id` and `@type` are present.
- [ ] **Task 2**: Add debug logging inside `apiFetch` specifically for Dev/Mock mode to warn when mock payloads miss required Hydra fields.
- [ ] **Task 3**: Update `osoma/src/features/integrations/tecniplast/dvc/dvc.integration.api.ts` to strictly consume OpenAPI generated types from `osoma/src/domain/dvc/dvc-proxy.generated.ts`.

## Execution Rules
- No UI component should rely on a field that only exists in `osoma/src/mocks/data/*`.
- If a field is needed in the UI, it MUST exist in the backend schema (and thus OpenAPI types).
