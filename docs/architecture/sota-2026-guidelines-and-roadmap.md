# Metadatapp → SOTA 2026: Guidelines & Roadmap

> Status: **opinionated proposal.** Not committed. Companion to
> `ARCHITECTURE.md`, `architecture-explorations.md`, and the existing
> `ROADMAP.md` (which is intentionally conservative; this one isn't).
>
> Audience: maintainers and senior contributors deciding what to invest in
> over the next 12 months.

---

## Part 1 — Guidelines (the durable rules)

These are principles, not tasks. They survive across phases and should be
the lens used when arguing about any non-trivial change.

### G1. Provenance is the product, not a feature

This is a scientific-metadata app. Reproducibility *is* the value
proposition. That means:

- Every persisted change records *who*, *when*, *via which agent (human or
  LLM)*, and *what evidence* (input ID, prompt hash, source row).
- ARRIVE Essential 10 is validated against the data model, not a checklist
  rendered in Markdown.
- FAIR² JSON-LD must *use* the namespaces it declares. If a procedure ends
  up as `sc:Thing`, the export has lied about being interoperable.
- RO-Crate manifests must list every payload file in `hasPart`.
- PROV-O linkage between subjects ↔ procedures ↔ measurements ↔ exports
  is mandatory, not aspirational.

**Practical rule:** any new entity must declare, before merge, which
ontology IRIs back its categorical fields and which QUDT units back its
numeric fields. "Add it later" is the failure mode the current FAIR²
implementation already shows.

### G2. AI-native, not AI-bolted-on

In 2026 the differentiator is not "we call OpenAI from a controller." It's:

- **Structured outputs everywhere.** No free-text LLM responses parsed by
  regex. JSON Schema → validated → typed.
- **Tool-use over prompt-stuffing.** Curation, lookup, ontology resolution,
  FAIR validation are *tools* the model calls, not paragraphs in a system
  prompt.
- **Evals are CI.** Every curation provider ships with a regression suite.
  Merging a prompt change without eval delta is rejected.
- **MCP server is the public agentic surface.** External agents (Claude
  Desktop, Cursor, Code) talk to Metadatapp via MCP, not via reverse-
  engineered REST. The existing `api/src/Mcp/` and `api/src/AI/Mcp/`
  scaffolding is the seed.
- **Mock providers are first-class** and used in CI by default. Real
  provider calls are opt-in, rate-limited, and budgeted.

### G3. Trust by default

Users and contributors must never wonder whether what they see is real.

- Mock mode is **off by default** in dev, **never** shipped to production
  bundles. When on, a permanent banner says so.
- Demo data lives in `tests/` or a separate package. Not `src/`.
- "Connected" UI states reflect actual successful syncs, not the existence
  of credentials.
- Empty states say "no data yet" — never fake data.
- API responses for partial integrations return `501 Not Implemented` with
  a link to the capability matrix, not silent stubs.

### G4. Boundaries enforced by tooling, not by README

Architectural rules that depend on contributor memory rot. Therefore:

- **Deptrac (or PHPArchTest) in CI**, with violations as build failures —
  not warnings. Bounded contexts: `Investigation`, `Husbandry`, `Curation`,
  `Integration`, `Export`, `Identity`.
- **PHPStan level max** on the whole tree. No `excludePaths` for
  `ConnectedApps/State/*` or anywhere else.
- **ESLint with `--max-warnings 0`** (already done — keep it).
- **No new controller may exceed 80 LOC.** Past that, it's a service.
- **No entity may exceed 250 LOC.** Past that, split into entity + DTO +
  domain service.

### G5. Type safety end-to-end

The OpenAPI → typed frontend pipeline already exists. Extend it:

- One pnpm command regenerates *all* domain types, not just `dvc-proxy`.
- Frontend `domain/` types are derived, never hand-written.
- API Platform resources publish `JSON Schema` for every payload; the LLM
  curation pipeline consumes those same schemas for structured output.
- E2E tests use the generated types — drift becomes a compile error.

### G6. Observability is not optional

You cannot run a scientific platform without knowing what it did.

- OpenTelemetry traces from browser → API → ConnectedApp → LLM, with
  `traceparent` propagated across the LLM call (yes, that's a thing now).
- Structured logs (JSON, one event per line) with `tenant_id`,
  `actor_id`, `correlation_id`, `provider_id` on every record.
- Per-LLM-call cost and token counters exposed as Prometheus metrics.
- Sync runs emit `SyncStarted` / `SyncCompleted` / `SyncFailed` events
  with idempotency keys and retry counts.
- A `/__ops` panel exists in dev *and* a redacted version in prod for
  admins.

### G7. Contributor experience is the OSS strategy

If a new contributor can't go from `git clone` to "I changed something and
saw it" in under 15 minutes, the project loses them.

- Devcontainer that boots the full stack with one click in VS Code /
  Cursor / Codespaces.
- `castor start` produces a working stack with seeded data, idempotently,
  on a fresh machine. The clean-clone smoke workflow already proves part
  of this; extend to "first PR" simulation.
- Every Connected App has a `README.md` *and* a `CONTRIBUTING.md` snippet
  describing how to add a new one.
- Plugin contract (Phase 3) lets third parties ship integrations without
  forking core.
- Every public endpoint has a curl example in its docblock.

### G8. Security defaults that age well

- Tokens out of `localStorage`. Use HttpOnly + Secure + SameSite=Strict
  cookies, with CSRF protection on state-changing requests.
- Passkeys (WebAuthn) as a first-class auth method via Keycloak.
- Per-tenant encryption keys for credentials in `LlmProviderCredential`
  and Connected App settings. Envelope encryption, not just "stored
  encrypted at rest" hand-waving.
- Cross-tenant write attempt = integration test, not just a voter.
- SBOM published per release. `cargo-audit`-equivalent (Composer Audit,
  pnpm audit) blocking in CI.
- Secret-scanning and `gitleaks` in pre-commit + CI.

### G9. Performance budgets, enforced

- Frontend: LCP < 2.0s, INP < 200ms, JS payload < 250KB gzipped on the
  shell. Lighthouse CI on every PR.
- API: P95 < 200ms for read endpoints, < 500ms for writes, measured in CI
  against a seeded DB.
- Bundle-size diff comment on every frontend PR (Vite + size-limit).
- Regressions block merge.

### G10. Decisions are durable

- ADRs (Architecture Decision Records) under `docs/architecture/adr/`,
  numbered, never deleted. The `ai-decision-records/` folder shows the
  shape — extend it to all architectural choices.
- Every "why" question that came up twice gets an ADR.

---

## Part 2 — Roadmap (the sequence)

Phases are sized by *theme*, not calendar weeks — the dates are
illustrative anchoring, not commitments. Each phase has a definition of
done that can be checked from `git status` + CI, not vibes.

### Phase 0 — Trust & Truth (≈ 2 weeks)

The premise: no architectural work is worth doing while the code lies
about what it does. Fix that first.

| # | Action | File / area |
|---|---|---|
| 0.1 | Mock mode default `false` in dev; permanent dev banner when on | `osoma/src/lib/mode.ts`, `main.tsx` |
| 0.2 | Move demo/fixture code out of `src/` | `api/src/DataFixtures/AppFixtures.php` (1,295 LOC), `api/src/Demo/Sensors/SensorDemoService.php` (1,092 LOC), SoftMouse `Mock/HttpClientMock.php` (859 LOC) |
| 0.3 | Move MSW handlers + data (~7,600 LOC) into a dev-only chunk excluded from prod bundle | `osoma/src/mocks/` |
| 0.4 | Remove `phpstan.dist.neon` exclusion for `ConnectedApps/State/*` | `phpstan.dist.neon` |
| 0.5 | Capability matrix in README marked authoritative; partial adapters return `501` until backed by sync runs | `README.md`, `ConnectedApps/Apps/*` |
| 0.6 | Cross-tenant write attempt integration test | `api/tests/Security/` |
| 0.7 | Honest `ARCHITECTURE.md` numbers (mocks are ~7.6k LOC, not 3k) | `ARCHITECTURE.md` |

**Done when:** `castor qa:all` passes with zero exclusions, a fresh clone
in dev shows the live API by default, and the release backlog's P0 list is
empty.

### Phase 1 — Slim the Core (≈ 4–6 weeks)

Peel logic out of controllers and entities. This is Architecture A's
opening move and the precondition for everything that follows.

| # | Action | Target |
|---|---|---|
| 1.1 | `TecniplastProxyController` (528) → service + State Provider/Processor | < 80 LOC controller |
| 1.2 | `DvcLegacyController` (501) → service | < 80 LOC controller |
| 1.3 | `ProjectWorkspaceController` (422) → service | < 80 LOC controller |
| 1.4 | `Experiment` (588), `Subject` (538) → entity + DTO + domain service | < 250 LOC entity |
| 1.5 | Sync idempotence base class for Connected Apps: `SyncOperation` with idempotency key, retry policy, conflict resolution | `ConnectedApps/Shared/` |
| 1.6 | Deptrac config + CI gate on bounded contexts | `deptrac.yaml`, CI |
| 1.7 | PHPStan level max | `phpstan.dist.neon` |

**Done when:** no controller > 80 LOC, no entity > 250 LOC, Deptrac passes
in CI, sync idempotence is provable by integration test on at least
eLabFTW + one other adapter.

### Phase 2 — FAIR² as a Domain Model (≈ 6–8 weeks)

The export is honest only when the entities are.

| # | Action |
|---|---|
| 2.1 | Add ontology-IRI fields to `Procedure`, `Genotype`, `Sex`, `Strain`, plus QUDT unit + value pairs on numeric measurements |
| 2.2 | Migrate existing string-typed categorical fields to coded values via lookup service |
| 2.3 | `Fair2JsonLdBuilder` payloads use the actual ontology IRIs — no `sc:Thing` fallbacks |
| 2.4 | `RoCrateExporter` `hasPart` lists every payload file; validated against RO-Crate v1.1 spec in CI |
| 2.5 | ARRIVE Essential 10 validator over the data model (not the rendered checklist) |
| 2.6 | PROV-O graph: subject → procedure → measurement → export, queryable |
| 2.7 | `castor fair:validate` task — round-trips every export through a SHACL or similar validator |

**Done when:** a published export passes external FAIR² / RO-Crate
validators with zero `sc:Thing` and zero unitless numerics, and ARRIVE
compliance is computed, not asserted.

### Phase 3 — AI-Native Layer (≈ 8–10 weeks)

The MCP scaffolding exists (`api/src/Mcp/`, `api/src/AI/Mcp/`). Make it
the canonical agentic surface.

| # | Action |
|---|---|
| 3.1 | MCP server v1: read tools (`list_subjects`, `get_experiment`, `fair_validate`, `arrive_check`), write tools behind explicit consent prompts |
| 3.2 | All curation providers use structured outputs (JSON Schema-validated), not free-text |
| 3.3 | Eval suite per provider in `evals/`, run in CI on every prompt or model change |
| 3.4 | Per-call cost & token metrics; per-tenant LLM budget + circuit breaker |
| 3.5 | Tool-use rewrite of the curation flow: ontology lookup, unit resolution, ARRIVE check are tools the model calls |
| 3.6 | Browser-side AI assistant uses the same MCP server via a thin BFF, not a separate API surface |
| 3.7 | Prompt + provider versioning recorded in `ModelRunTrace` (the entity exists — wire it up everywhere) |

**Done when:** an external Claude/Cursor session can connect to a
Metadatapp instance via MCP and curate a study end-to-end, with every
step recorded in `ModelRunTrace`.

### Phase 4 — Observability & DX (≈ 6–8 weeks, parallelizable with Phase 3)

| # | Action |
|---|---|
| 4.1 | OpenTelemetry SDK in API + Osoma; `traceparent` through to LLM and ConnectedApp calls |
| 4.2 | Structured JSON logs everywhere; Loki/Grafana stack in `infrastructure/docker/` profile `observability` |
| 4.3 | Devcontainer (`.devcontainer/`) boots full stack; works in Codespaces and Cursor |
| 4.4 | `castor first-pr` task: clones a sample feature branch, runs through change → test → PR locally |
| 4.5 | Lighthouse CI + size-limit on every frontend PR |
| 4.6 | Contract tests between Osoma and the API via the OpenAPI schema |
| 4.7 | Per-Connected-App `README.md` + `CONTRIBUTING.md` snippet |

**Done when:** time-to-first-meaningful-change for a new contributor is
< 15 minutes on a clean machine, measured by a recorded walkthrough.

### Phase 5 — Plugin Ecosystem v1 (≈ 10–12 weeks)

Architecture C's most useful pieces, without the operational explosion.

| # | Action |
|---|---|
| 5.1 | Plugin contract spec: manifest, capability declaration, lifecycle hooks, auth handshake |
| 5.2 | First-party adapters refactored to consume the same plugin contract internally |
| 5.3 | Out-of-tree plugin example repo (`metadatapp-plugin-example`) with full E2E |
| 5.4 | Plugin marketplace page (static, generated from manifests) |
| 5.5 | Same contract for LLM providers — Anthropic, OpenAI, local Ollama, Mistral all behind one shape |

**Done when:** a third party can publish a Connected App in their own
GitHub repo and a Metadatapp instance can install it via config, no fork
needed.

### Phase 6 — Optional: Provenance Spine (≈ 12+ weeks)

Architecture B, but scoped: introduce an event log *only* for the
provenance-critical entities (Subject, Procedure, Measurement, Export,
CurationDecision). The rest stays CRUD. Read models are projections;
existing API Platform resources stay as the read API.

| # | Action |
|---|---|
| 6.1 | Pick an event store (EventSauce or a Postgres-backed table — start simple) |
| 6.2 | Define provenance events; emit alongside existing persistence (dual-write, then cut over) |
| 6.3 | Projections rebuilt from the event log; FAIR² and RO-Crate exporters consume them |
| 6.4 | Time-travel debugging endpoint for admins: "what did we know about subject X at time T?" |

**Done when:** a published export's provenance graph is reconstructable
from events alone, and the dual-write phase is cut.

### Phase 7 — Production Posture (≈ 4–6 weeks, can run in parallel)

| # | Action |
|---|---|
| 7.1 | Tokens in HttpOnly cookies; CSRF middleware; remove `localStorage` token storage |
| 7.2 | Passkeys via Keycloak |
| 7.3 | Envelope encryption for credentials |
| 7.4 | SBOM (CycloneDX) per release; signed releases (sigstore/cosign) |
| 7.5 | Performance budgets enforced in CI |
| 7.6 | Multi-tenant SaaS deployment doc + reference Helm chart (or Clever Cloud equivalent) |

---

## Sequencing logic

```
Phase 0 (Trust)         ── precondition for everything; do first
   ↓
Phase 1 (Slim core)     ── precondition for Phase 2 entity changes
   ↓
Phase 2 (FAIR²)  ──┐
                   ├── Phase 3 (AI-native) and Phase 4 (Observability/DX) run in parallel
Phase 3 (AI)     ──┤
Phase 4 (Obs/DX) ──┘
   ↓
Phase 5 (Plugins)       ── needs slim core + observability
   ↓
Phase 6 (Provenance)    ── optional; only if regulatory/grant pressure justifies it
Phase 7 (Prod posture)  ── parallelizable from Phase 3 onward
```

---

## What "SOTA 2026" means concretely

A reader landing on the repo in late 2026 should see:

1. A fresh clone runs in < 5 minutes with no surprise mock data.
2. The MCP endpoint is the headline feature on the README.
3. FAIR² and RO-Crate exports validate against external tools without
   asterisks.
4. Every LLM call has a trace, a cost, and an eval covering the prompt.
5. Bounded contexts enforced in CI; no controller > 80 LOC; PHPStan max,
   ESLint zero warnings, Deptrac green.
6. A third-party Connected App lives in a separate repo and works.
7. Passkeys; no tokens in localStorage; SBOMs on every release.
8. ADRs document every non-obvious choice; `ROADMAP.md` matches reality.

If 6 of those 8 are true, the repo is in the top decile for OSS scientific
infrastructure projects in 2026. Pursuing all 8 is the difference between
"a good open-source project" and "the reference implementation in its
domain."

---

## What this roadmap deliberately does *not* include

- A rewrite. The current architecture is sound; bloat is local, not
  structural.
- Microservices for the core. Phase 5's plugin contract gives 80% of the
  benefit with 20% of the operational cost.
- Event sourcing for everything. Phase 6 is scoped to provenance only,
  and is optional.
- A new frontend framework. React + Vite + TanStack Query is fine; the
  problem is mock-mode trust, not the stack.
- Kubernetes by default. Docker Compose + Castor is a feature for
  self-hosters; reference Helm chart in Phase 7 is for those who want it.

The cheapest path to SOTA is to finish what's already well-shaped, delete
what lies, and refuse to add capabilities the architecture can't yet
honestly support.
