# For developers

This section orients developers who want to build on or extend Metadatapp. The
**canonical** developer documentation lives in the repository (and is kept in one
place by design); this page is a map into it, with hands-on recipes of its own.

```{toctree}
:maxdepth: 1

extending
api-reference
publishing-docs
```

```{admonition} Canonical sources
:class: important
- [`AGENTS.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/AGENTS.md) —
  the authoritative playbook: repo map, commands, conventions, ownership.
- [`ARCHITECTURE.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/ARCHITECTURE.md) —
  a 10-minute mental model of the system.
- [`api/README.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/api/README.md)
  and [`osoma/README.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/osoma/README.md) —
  backend and frontend setup and conventions.
- [`CONTRIBUTING.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/CONTRIBUTING.md) —
  how to contribute.
```

## Architecture at a glance

```{mermaid}
flowchart LR
  UI["Osoma (React + TanStack Query)"] -->|Bearer JWT| API["API Platform (Symfony, FrankenPHP)"]
  UI --> KC["Keycloak (OIDC)"]
  API --> DB[("PostgreSQL")]
  API --> EXP["FAIR² + RO-Crate exporters"]
  API --> CUR["Curation + LLM gateway"]
  API --> CA["Connected Apps factory"]
  CA --> EXT["eLabFTW · SoftMouse · DVC · FAIR3R · ..."]
```

- **Backend** (`api/`) — Symfony 7.4 / API Platform 4.3, PHP 8.4, Doctrine ORM 3.
  Entities are `#[ApiResource]`s; custom behavior lives in State Providers/Processors;
  tenant scoping via account/user-aware interfaces and voters.
- **Frontend** (`osoma/`) — Vite + React + TypeScript. Features are self-contained
  under `src/features/`; API access goes through `apiFetch`; mock mode uses MSW.
- **Identity** — Keycloak OIDC; see [Identity & Keycloak](../self-hosting/identity).

## Setting up a dev environment

Use [Local Setup in the README](https://github.com/Neuronautix/metadatapp-oss/blob/main/README.md#local-setup)
(install Castor → `castor start` → `castor fixture`). Common checks:

```bash
castor phpunit          # backend tests
castor qa:phpstan       # static analysis
castor qa:cs --dry-run  # coding standards
castor qa:osoma:build   # frontend build
castor qa:all           # everything (incl. docs coverage)
```

The complete command catalogue is in
[AGENTS.md → Commands That Exist Today](https://github.com/Neuronautix/metadatapp-oss/blob/main/AGENTS.md#commands-that-exist-today).

## Extending the platform

Step-by-step recipes for the two most common tasks live on their own page:

- [Add an API resource (entity) end-to-end](extending.md#recipe-add-an-api-resource-entity-end-to-end)
- [Add a Connected App](extending.md#recipe-add-a-connected-app)

For **AI features**, work lives under `api/src/AI/` (`Mcp/`, `Gateway/`,
`Curation/`, `Governance/`, `Evals/`, `Runtime/`); new workflows must keep writes
human-approved and meet the evaluation baselines. Canonical guidance:
[`docs/architecture/ai-foundation.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/docs/architecture/ai-foundation.md)
and [`ai-pipeline.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/docs/architecture/ai-pipeline.md).

## Working on this guide

See [Building & publishing this guide](publishing-docs) for local builds, the CI
gate, and how the site is hosted.
