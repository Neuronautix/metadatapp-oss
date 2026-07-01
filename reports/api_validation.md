# Phase 2 — API Layer Verification

## Scope
Authentication, token usage, base URL, headers, endpoint correctness for Tecniplast DVC Analytics.

## Validation summary

- **Auth scheme:** `x-api-key` header (OpenAPI security scheme)
- **Base URL (configured):** `https://analytics.dvc.tecniplast.it`
- **Endpoints used by backend client:**
  - `POST /tasks/api/1/integration/test-api-key/`
  - `GET /tasks/api/1/integration/metrics/`
  - `POST /tasks/api/1/integration/cages/search`
  - `POST /tasks/api/1/integration/animals/search`
  - `POST /tasks/api/1/integration/submit/`
  - `GET /tasks/api/1/integration/{taskId}/state`
  - `GET /tasks/api/1/integration/{taskId}/download`

## Request example (curl)

```bash
curl -X POST "https://analytics.dvc.tecniplast.it/tasks/api/1/integration/test-api-key/" \
  -H "x-api-key: <YOUR_DVC_API_KEY>" \
  -H "Accept: application/json"
```

## Successful authentication response example

```json
"f1234"
```

## Raw API response example (metrics)

```json
[
  {
    "id": "ACTIVATION",
    "description": "Electrodes average",
    "type": "METRIC"
  }
]
```

> Note: response examples above are from project OpenAPI + integration mock definitions, used as reference output format.

## API layer verdict

- Endpoint set and header contract in backend client match the OpenAPI contract.
- No evidence that the external API contract itself is the primary breakage point in the observed failure.
- The observed failure occurred earlier in the stack (backend route mismatch with frontend proxy pathing).

---

## Agent mandatory output

### Files inspected
- Archived Tecniplast DVC Analytics OpenAPI contract (removed from repository after investigation)
- `api/src/ConnectedApps/Apps/Tecniplast/Client/TecniplastHttpClient.php`

### Findings
- API contract and backend HTTP client endpoint construction are aligned.
- Auth uses `x-api-key` as expected.

### Reproduction steps
1. Issue `test-api-key` curl request with an API key.
2. Verify 200 + string payload for valid key.
3. Verify 401 for invalid key.

### Fix proposal
- No API-contract change required for the identified root cause.
