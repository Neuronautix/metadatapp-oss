# Architecture Explorations

> Status: **learning document, not a roadmap.** None of the three architectures
> below are committed plans. They exist to make tradeoffs concrete by contrasting
> the current design against three plausible alternatives.
>
> Companion to `ARCHITECTURE.md` (which describes what *is*) and
> `.github/KNOWN_LIMITATIONS.md` (which summarizes public-preview caveats).

---

## 0. Where we are today (one-paragraph recap)

Symfony 7.4 / API Platform 4.3 monolith with a Vite + React SPA. Tenancy via
`AccountAware` / `UserAware` decorator processors. Connected Apps follow a
factory + tagged-iterator pattern under `api/src/ConnectedApps/Apps/<App>/`.
Curation goes through an `LLMCurationProvider` registry. FAIR² JSON-LD and
RO-Crate exporters live as services. Frontend defaults to MSW mock mode in
dev. One Postgres, one FrankenPHP runtime, Castor as task runner.

The shape is **a layered monolith with adapter-style integration points** —
already partway toward hexagonal in the spots that needed it
(`ConnectedApps`, `Curation`), still classically Symfony-shaped elsewhere
(controllers, entities, providers/processors).

---

## A. Hexagonal modular monolith (Ports & Adapters / DDD-lite)

**Idea.** Keep one deployable; slice `api/src/` into bounded contexts —
`Investigation`, `Husbandry`, `Curation`, `Integration`, `Export`,
`Identity` — each with explicit *ports* (PHP interfaces) and *adapters*
(Doctrine repositories, HTTP clients, LLM providers). Symfony and API
Platform become adapters too: domain code stops importing them. Cross-context
calls go through a public application-service surface, not into entities.

**Maps onto today's code.** `ConnectedApps/Apps/*` already has the right
silhouette (`Client/`, `Service/`, `Synchronizer/`, `DataTransformer/`).
`LLMCurationProvider` is already a port with multiple adapters. The bulk of
the work is drawing real boundaries between *backend modules*, not inventing
new abstractions.

**Pros**
- Lowest delta from the current code; same ops, same Castor, same Postgres.
- Vendor swap (eLabFTW client, Anthropic) becomes a one-adapter change.
- Domain tests run without booting Symfony or hitting Doctrine.
- Plays well with FAIR² — domain model can stay ontology-shaped without ORM
  compromises.
- OSS contributors clone one repo and `castor start`. No infra surprises.

**Cons**
- Discipline tax: nothing in PHP enforces context boundaries. Drift is
  constant. Tools like Deptrac / PHPArchTest help but cost CI time and
  goodwill.
- Heavier abstractions for small features ("where does this go?" debates).
- Doesn't solve scaling per-context — LLM bursts and DVC ingest still share
  one runtime.
- Doesn't address the *frontend* coupling problem (MSW size, hardcoded RBAC).

**Best fit when:** small team, moderate domain complexity, OSS-friendly clone-and-run is a priority.

---

## B. Event-sourced core + CQRS read models

**Idea.** The write side records immutable domain events —
`SubjectAcquired`, `ProcedurePerformed`, `MeasurementRecorded`,
`CurationSuggested`, `FairExportRequested`. Read models (today's API
Platform resources) become projections rebuilt from the event log.
Connected-App syncs become event consumers/producers. FAIR² and RO-Crate
exporters read from the event log directly to emit PROV-O-grade provenance.

**Pros**
- **Provenance becomes the architecture, not a bolt-on.** ARRIVE / PROV-O /
  FAIR² requirements fall out almost for free.
- Idempotent sync becomes natural (events keyed by external ID + version).
- Audit, replay, time-travel debugging, "what did the LLM suggest at T?" —
  all trivial.
- Strong narrative for regulated science / pharma / grants where
  reproducibility is itself the product.

**Cons**
- High conceptual cost: event versioning, upcasters, projection rebuilds,
  eventual consistency in the UI.
- Schema migration is replaced by event-schema migration — harder, not easier.
- Most contributors have never touched event sourcing. OSS onboarding gets
  *worse*, not better.
- PHP tooling (Prooph, EventSauce) is alive but thinner than JVM/.NET. Real
  risk of building bespoke infra that nobody else maintains.
- Today's Foundry factories, `ApiTestCase` idioms, fixtures — most of it
  gets thrown away or reshaped.

**Best fit when:** provenance and auditability are *features users pay for*,
not just hygiene.

---

## C. Plugin kernel + microservices for integrations & AI

**Idea.** Three layers:
1. A small **core platform** (identity, tenants, entities, FAIR export) —
   still Symfony.
2. **Connected Apps as independent services**, talking to core over an
   internal API + message queue. Each can be in any language: PHP for
   eLabFTW, Python for DVC ingest (where the scientific stack lives),
   whatever fits.
3. **Curation/LLM as its own service** with its own rate limits, secrets,
   scaling, observability.

A documented **plugin contract** (manifest + container image + auth
handshake) lets OSS contributors ship a new Connected App or LLM provider as
an external repo, no core fork needed.

**Pros**
- Best fit for an OSS *ecosystem*: contributors add lab integrations
  without forking the monolith.
- Independent scaling — LLM bursts don't starve the API; DVC sync workers
  scale horizontally.
- Polyglot — Python for DVC/Tecniplast/curation pipelines is natural and
  matches the scientific community's tooling.
- Failure isolation: a broken private integration adapter doesn't take down
  `/experiments`.
- Maps cleanly onto a multi-cluster SaaS deployment later.

**Cons**
- Operational explosion: queues, service discovery, distributed tracing,
  inter-service auth, secret distribution. `castor start` stops being enough.
- Distributed transactions and sync idempotence are *genuinely* hard. The
  sync correctness problem moves rather than disappears.
- Local dev story for casual contributors gets much worse — the exact
  friction the OSS readiness audit already flagged.
- Plugin contract versioning becomes a forever job.
- Premature for current scale (single tenant per install, modest load).

**Best fit when:** multiple deployments, dedicated SRE, real third-party
ecosystem.

---

## Quick comparison

| Axis                          | A. Hexagonal monolith | B. Event-sourced/CQRS         | C. Plugin + microservices |
|-------------------------------|-----------------------|--------------------------------|---------------------------|
| Migration cost from today     | Low                   | High                           | High                      |
| OSS onboarding                | Best                  | Worst                          | Mixed (core easy, plugins hard) |
| Provenance / FAIR fit         | Good                  | Excellent                      | Good                      |
| Independent scaling           | No                    | Partial                        | Yes                       |
| Team size required            | Small                 | Medium + experienced           | Medium + ops              |
| Worst-case failure            | Big ball of mud with extra interfaces | Stuck mid-migration, two models live forever | Distributed monolith |

A useful exercise: take **one slice** — say, Connected Apps + sync
idempotence — and sketch how each architecture solves it. That's where the
tradeoffs stop being abstract.

---

## Honest assessment of the current design

Strengths and rough edges, after reading the code rather than the docs.

### What's genuinely good

- **Adapter pattern in `ConnectedApps/`.** The
  `Client / Service / Synchronizer / DataTransformer` shape is consistent
  across `Elabftw`, `private integration`, `Tecniplast`, `Fair3r`. That
  uniformity is exactly the affordance Architecture A or C would extend, not
  invent — it's already the most architecturally mature part of the
  codebase.
- **Curation as a registry of providers** (`LLMCurationProvider` +
  `LLMCurationProviderRegistry` + Mock/OpenAI/Anthropic/CurateGPT). Clean
  port-and-adapter shape, mockable by default. Right call.
- **Tenant isolation as a decorator processor** (`SetAccountProcessor`,
  `SetUserProcessor`). Compact, hard to forget, works with API Platform's
  grain instead of fighting it.
- **`AccountAwareInterface` / `UserAwareInterface`.** Marker interfaces +
  voters is the right level of abstraction here — not over-engineered, not
  hand-wavy.
- **API Platform as the contract layer.** Generated OpenAPI flowing into
  typed frontend code (`pnpm openapi:types:dvc-proxy`) is an unsung win.

### What the code says, not the docs

- **`api/src/Controller/` is heavier than ARCHITECTURE.md admits.**
  `TecniplastProxyController` (528 LOC), `DvcLegacyController` (501),
  `ProjectWorkspaceController` (422), `CalendarController` (238),
  `FeatureFlagController` (199). This is the layer most likely to rot:
  controllers that should be either State Providers/Processors or domain
  services. They're the first thing I'd peel off.
- **Entity bloat.** `Experiment.php` is 588 LOC, `Subject.php` 538 LOC.
  These are doing too many jobs — persistence, API contract, validation,
  serialization groups, business logic. Splitting into entity + DTO +
  domain service would pay for itself fast, and is a precondition for
  Architecture A.
- **State layer is thin where it should be thick.** Two providers, eight
  processors. The codebase says "API Platform first," but most non-trivial
  reads/writes have leaked into controllers. The processor pattern is
  underused exactly where it would help most.
- **Mock layer is much bigger than reported.** Frontend `mocks/` totals
  ~7,600 LOC (ARCHITECTURE.md cites 3,086). It ships with the production
  bundle unless someone remembers to flip a flag. This is the single
  biggest "looks done, isn't" risk in the repo for an OSS reader.
- **`AppFixtures.php` (1,295 LOC) and `SensorDemoService.php` (1,092 LOC)
  in `src/`.** Demo code masquerading as production code. The audit flagged
  this; until it moves to `tests/` or a separate package, every honest
  architecture diagram has to caveat it.

### What's genuinely concerning

- **Sync idempotence isn't really solved.** The Synchronizer pattern looks
  uniform but the underlying guarantees vary per app. eLabFTW is the only
  adapter the audit considers credible; the rest are partial. A
  `SyncOperation` + idempotency-key abstraction at the Connected Apps
  *base* level (rather than per-app) would harden this without changing
  architecture.
- **FAIR² is cosmetic in places.** The JSON-LD `@context` declares OBI,
  QUDT, schema.org — and then payloads fall back to `sc:Thing` for
  procedures, plain strings for genotypes/sex, no QUDT units on numeric
  measurements. This is a domain-modelling problem masquerading as a
  serialization problem; fixing it requires entities to carry ontology IRIs,
  not strings. That's exactly the kind of change Architecture A enables and
  the current entity layout makes hard.
- **Mock mode default-on in dev** is a *trust* bug, not a code bug. A new
  contributor sees the UI work without the API and concludes things work
  that don't. ARCHITECTURE.md acknowledges this; the fix is a one-line
  default flip plus a visible banner, and it's worth more than any
  refactor on this list.
- **`phpstan.dist.neon` excludes `ConnectedApps/State/*`.** That's exactly
  the layer where adapter bugs hide. Removing the exclusion is a small
  change with outsized payoff.

### Overall read

This is a **well-shaped layered monolith** with clear adapter seams in the
two places that needed them (Connected Apps, Curation), and accumulated
controller/entity bloat in the places where Symfony's defaults made it
easy to keep adding. It is *not* a candidate for rewrite. It *is* a strong
candidate for:

1. Aggressively peeling logic out of controllers into State + services
   (Architecture A's first move).
2. Hardening the adapter base classes so per-app code shrinks, not grows.
3. Treating FAIR² as a domain-modelling problem, not an exporter problem.
4. Fixing the mock-mode trust issue before any architectural work.

If those four happen, the codebase ends up roughly at Architecture A
without anyone calling it a migration — which is usually how the
healthy versions of these stories go.
