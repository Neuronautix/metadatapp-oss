# Osoma Frontend

Osoma is the active React/Vite frontend for Metadatapp.

## Setup

```bash
pnpm install
pnpm run dev
```

The full repository stack is normally started from the repository root:

```bash
castor start
```

## Environment

Use [`.env.example`](.env.example) as the public-safe starting point:

```bash
cp .env.example .env.local
```

Common variables:

- `VITE_API_URL`: API base URL
- `VITE_DATA_MODE`: `real` calls the API backend; `mock` uses local MSW demo data
- `VITE_AUTH_MODE`: `real` or `bypass`; when unset, mock data mode defaults to bypass auth
- `VITE_OIDC_CLIENT_ID`: OIDC client ID
- `VITE_OIDC_SERVER_URL`: browser-reachable OIDC issuer URL

## Scripts

```bash
pnpm build
pnpm run test:integration
pnpm run openapi:types:dvc-proxy
```

## Structure

- `src/app/`: application shell, providers, routing, and guards
- `src/features/`: feature-specific screens, hooks, and API bindings
- `src/components/`: shared UI components
- `src/lib/`: shared API, auth, and mode helpers
- `src/domain/`: domain types and generated OpenAPI types
- `src/mocks/`: MSW handlers for local mock data mode

## Boundaries

Osoma should talk to the Metadatapp backend only. External Connected Apps must remain server-side integrations.

E2E tests disable MSW and run against live services.
