# Metadatapp Architecture

> Audience: contributors and reviewers who want a mental model of the system in <10 minutes.
> Status: `v0.1.0-public-preview` — descriptions reflect the current code, not aspirational design.
> See `AGENTS.md` for working rules and `.github/KNOWN_LIMITATIONS.md` for public-preview caveats.

## 1. One-Paragraph Summary

Metadatapp is a Symfony 7.4 / API Platform 4.3 backend (PHP 8.4, Doctrine ORM 3) with a Vite + React + TypeScript frontend (`osoma`). The backend models preclinical research entities (investigations, studies, subjects, cages, strains, procedures, home-cage measurements), exposes them as API Platform resources with tenant-scoped (`AccountAware` / `UserAware`) state providers and processors, and ships server-side adapters to external lab systems (eLabFTW, private integration, Tecniplast DVC, Fair3r). Two FAIR-oriented exporters (`Fair2JsonLdBuilder`, `RoCrateExporter`) emit JSON-LD and RO-Crate ZIPs. Authentication is OIDC via Keycloak. A pluggable LLM-curation framework (`LLMCurationProvider` registry with mock / OpenAI / Anthropic / CurateGPT adapters) backs an in-app AI assistant.

## 2. High-Level Diagram

```mermaid
flowchart LR
  subgraph Client["Browser (osoma)"]
    UI["React + TanStack Query"]
    MSW["MSW handlers (mock mode)"]
  end

  subgraph Edge
    Traefik["Traefik (.test domains)"]
    Keycloak["Keycloak (OIDC)"]
  end

  subgraph Backend["api/ (FrankenPHP + Symfony 7.4)"]
    APIP["API Platform"]
    State["State Providers/Processors"]
    Entity["Doctrine Entities"]
    DB[(PostgreSQL)]
    Curation["Curation framework"]
    Export["FAIR² + RO-Crate"]
    Connected["ConnectedApps factory"]
  end

  subgraph External["External lab systems"]
    Elabftw["eLabFTW"]
    DVC["Tecniplast DVC"]
    private integration["private integration"]
    Fair3r["Fair3r"]
    LLM["OpenAI / Anthropic / CurateGPT"]
  end

  UI -->|apiFetch + Bearer JWT| Traefik
  UI -.mock mode.-> MSW
  Traefik --> APIP
  UI --> Keycloak
  Keycloak -->|JWT| UI
  APIP --> State --> Entity --> DB
  APIP --> Export
  APIP --> Curation
  Curation --> LLM
  APIP --> Connected
  Connected --> Elabftw
  Connected --> DVC
  Connected --> private integration
  Connected --> Fair3r
```

## 3. Backend (`api/`)

### 3.1 Layering

- **`api/src/Entity/`** — Doctrine entities annotated with `#[ApiResource]`. UUIDs via `UuidGenerator`. Strict types everywhere.
- **`api/src/State/Provider/`** — read paths for resources that need custom hydration (`FairAssessmentProvider`, `ZefixBatchProvider`, etc.).
- **`api/src/State/Processor/`** — write paths and decorators. Notably `SetAccountProcessor` and `SetUserProcessor` decorate the persist processor to assign tenant on create.
- **`api/src/Security/`** — Voters, OIDC wiring, `AccountAwareInterface` / `UserAwareInterface` enforcement.
- **`api/src/ConnectedApps/`** — Factory + tagged-iterator pattern. Each adapter owns Client, Service, State, Synchronizer.
- **`api/src/Curation/`** — `LLMCurationProvider` registry + Mock/OpenAI/Anthropic/CurateGPT adapters + validation framework.
- **`api/src/Service/Fair2JsonLdBuilder.php`**, **`api/src/Service/RoCrate/RoCrateExporter.php`** — FAIR exporters.
- **`api/src/Controller/`** — *should be thin*. Today `TecniplastProxyController`, `DvcLegacyController`, and `ProjectWorkspaceController` are heavier than the target architecture and should be refactored toward services/providers over time.
- **`api/migrations/`** — Doctrine migrations; auto-generated and reviewed.
- **`api/tests/`** — `ApiTestCase` + Foundry factories from `api/src/DataFixtures/Factory/` + `ResetDatabase` discipline.

### 3.2 Tenant Isolation Model

Every tenant-scoped entity implements `AccountAwareInterface` (and often `UserAwareInterface`). On creation, `SetAccountProcessor` injects the current user's account before persist. On read, providers and voters check `account === currentUser.account`. **Caveat:** updates rely on voters; an integration test for cross-tenant write attempts is on the P1 list (§7 of the audit).

### 3.3 Connected Apps

Each external lab system has a uniform shape under `api/src/ConnectedApps/Apps/<App>/`:

```
Apps/<App>/
  Client/         HTTP client (real + optional Mock/)
  Service/        Domain-level orchestration
  State/          Provider/Processor for sync resources
  Synchronizer/   Per-entity sync logic (Subject, Cage, Experiment, ...)
  DataTransformer/  DTO ↔ entity mapping
```

A factory (`ConnectedAppServiceFactory`) resolves the right service per app code. **Reality check (May 2026):** eLabFTW is the only adapter with plausibly working bidirectional sync. private integration / Tecniplast / Fair3r are partial-or-stub (see audit §4 and the Capability Matrix in README).

### 3.4 FAIR Exporters

- **`Fair2JsonLdBuilder`** emits a JSON-LD `@graph` per study, with nodes for project, experiment, protocol, subjects, cages, measurements, files. The `@context` declares `schema.org`, `bioschemas`, `OBI`, `MLCommons/Croissant`, `FAIR²`, `Dublin Terms`, `QUDT`. Strengths: real namespaces, ORCID, license, `sameAs` for external IDs. Weaknesses: procedures fall back to `sc:Thing`, no QUDT units on numeric measurements, genotype/sex are strings rather than ontology IRIs.
- **`RoCrateExporter`** packages a study as an RO-Crate ZIP with a valid `ro-crate-metadata.json` root descriptor and per-resource payloads. The manifest's `hasPart` does not yet declare every payload file — improvement on the backlog.

### 3.5 Curation / LLM

`LLMCurationProvider` is an interface with concrete `MockLLMCurationProvider`, `CurateGptOntologyProvider`, `OpenAiProvider`, `AnthropicProvider`. Configuration in `api/config/services.yaml` selects the active provider; default is mock. Keys come from `Connected Apps` settings (server-stored, masked in responses) or environment variables. The framework is real; the user-visible flow is partial.

## 4. Frontend (`osoma/`)

### 4.1 Layout

```
osoma/src/
  app/         Boot, router, AuthGuard, Keycloak wiring
  components/  Shared UI primitives (Tailwind + Radix)
  domain/      Generated OpenAPI types + domain types
  features/    Self-contained features (core/, integrations/, curation/, dvc/, export/, ai-assistant/, system/, zefix/)
  lib/         apiFetch, auth, rbac, mode
  mocks/       MSW handlers (3086 lines as of audit)
```

### 4.2 Data Flow

`apiFetch` (in `osoma/src/lib/api.ts`) wraps `fetch`, attaches the Bearer JWT (or mock token), handles Hydra collections, and toggles `X-Bypass-Mock` based on `getDataMode()`. TanStack Query owns caching/staleness.

### 4.3 Auth

`AuthService` is a singleton handling Keycloak PKCE (state, code_verifier, code_challenge), token refresh, and a fallback "mock-access-token" path. `AuthGuard` blocks rendering until the session is established. RBAC has three hardcoded roles (viewer / editor / admin).

### 4.4 Mock Mode (important)

Mock mode is **on by default** in development. `main.tsx` boots an MSW worker before the app mounts. `apiFetch` sends `X-Bypass-Mock` only when the data mode is explicitly `real`. To run against the live API, set `localStorage.use_msw = 'false'` and either select the data source from the `/__ops` panel or set `VITE_API_URL`. This is the single most-confusing aspect of first-time setup; the README will gain an explicit callout.

## 5. Authentication & Authorisation

- **Identity:** Keycloak realm; OIDC PKCE.
- **Tokens:** JWT bearer; stored in localStorage today (audit §8 medium risk; trade-off documented).
- **Authorisation:** API Platform `security:` attribute on resources; voters for fine-grained checks; `AccountAware` / `UserAware` for tenant scoping.

## 6. Data Persistence

- PostgreSQL (Docker Compose).
- Doctrine migrations under `api/migrations/`.
- Schema is validated by `castor schema-validate`.
- UUID v7 / v4 IDs (`UuidGenerator`).

## 7. Infrastructure

- **Docker Compose** stack defined in `infrastructure/docker/`.
- **FrankenPHP** runs the Symfony app.
- **Traefik** routes `metadatapp.test`, `osoma.metadatapp.test`, `auth.metadatapp.test` (HTTPS, self-signed for local).
- **Keycloak** at `auth.metadatapp.test`.
- **Mercure** for real-time updates (key hard-coded for local; production must override).
- Castor (`castor.php` + `.castor/`) is the canonical task runner.

## 8. CI / QA

- `.github/workflows/ci.yml` runs backend QA, frontend build, and selected E2E.
- `.github/workflows/clean-clone-smoke.yml` runs without secrets/services/DB to prove a fresh clone is buildable.
- Deployment workflows are intentionally not included in the public preview snapshot.

## 9. Repo Conventions (canonical statements)

- **Backend code lives in `api/src/`.** Demo / fixture / mock code being moved to `api/tests/` (audit P0).
- **Frontend code lives in `osoma/src/`.** Do not reintroduce `osomapp/` or `pwa/`.
- **Castor is the entry point** for stack lifecycle and routine QA.
- **`AGENTS.md` is the canonical agent playbook.** Companion files must be thin pointers.

## 10. Known Architectural Gaps

- Controllers `TecniplastProxyController`, `DvcLegacyController`, `ProjectWorkspaceController` carry too much logic. Refactor to services.
- `phpstan.dist.neon` excludes `ConnectedApps/State/*` from analysis. Remove the exclusion.
- `api/src/DataFixtures/AppFixtures.php` (1295 LOC), `api/src/Demo/Sensors/SensorDemoService.php` (1092 LOC), and private integration `Mock/HttpClientMock.php` (859 LOC) live in `src/`. Move to `tests/`.
- Frontend MSW handlers (3086 LOC) ship with the app. Either decouple into a separate package or document as dev-only.
- ARRIVE template generates a checklist but does not validate the data model against ARRIVE Essential 10.
- FAIR² JSON-LD declares ontology namespaces but does not always *use* them in payload (procedures, units, genotypes).

## 11. Glossary

- **FAIR²:** internal label for the JSON-LD export; aligns with FAIR (Findable / Accessible / Interoperable / Reusable) + an additional emphasis on machine-actionable interoperability.
- **HCM:** Home-Cage Monitoring (continuous behavioural / environmental data from cages).
- **Connected App:** server-side adapter to an external lab system.
- **Castor:** the task runner used across the repo (`castor start`, `castor qa:all`, etc.).
- **MSW:** Mock Service Worker — frontend-side mock layer used by Osoma's default dev mode.
