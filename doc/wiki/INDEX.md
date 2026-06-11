# Wiki Index

_Last updated: 2026-05-05_

This is the entry point for the compiled knowledge base. Read this first when answering questions about the project.
The raw sources are in `doc/evolution/` (one file per merged PR). This wiki synthesizes them.

---

## Overview & Cross-Cutting

- [overview.md](overview.md) — Project synthesis: what Metadatapp is, stack, feature areas, strategic direction
- [tech-debt.md](tech-debt.md) — All known tech debt, limitations, and deferred work (with resolution paths)

## Features

- [features/fair-checking.md](features/fair-checking.md) — FAIR assessment, FAIR/ARRIVE reporting, AI assistant integration (PRs #111, #113, #209, #210, #244)
- [features/curation.md](features/curation.md) — Data import, LLM-assisted curation, SessionImport/Proposal/PatchReview workflow (PRs #89, #114, #116, #151, #210)
- [features/zefix.md](features/zefix.md) — Zefix animal care integration: locations, lines, batches, mortality, alerts, CRYO (PRs #177–#205)
- [features/elabftw.md](features/elabftw.md) — elabFTW lab notebook synchronization (PRs #115, #202)
- [features/ai-mcp.md](features/ai-mcp.md) — AI assistant, McpBridgeService, encrypted user-scoped provider config, read-only tool dispatch (PRs #151, #197, #199, #209, #210, #244)
- [features/metadata-standards.md](features/metadata-standards.md) — RO-Crate, Croissant ML, FAIR² JSON-LD export and connected-resource provenance (PRs #110, #113, #244)
- [features/sensors.md](features/sensors.md) — Live sensor agent demo, alarm model, Osoma panels (PRs #186, #196, #199)

## Architectural Decisions

- [decisions/backend-as-source-of-truth.md](decisions/backend-as-source-of-truth.md) — All external API access via backend proxy; frontend never calls external APIs directly
- [decisions/api-platform-conventions.md](decisions/api-platform-conventions.md) — State processors/providers, not controllers; API Platform 4.3 as uniform REST layer
- [decisions/ai-mcp-read-only.md](decisions/ai-mcp-read-only.md) — AI tool dispatch is read-only; McpBridgeService enforces this by convention
- [decisions/domain-driven-workflow-entities.md](decisions/domain-driven-workflow-entities.md) — Major workflows are explicit Doctrine entities with state machines, not ad-hoc service calls

## Stack Areas

- [areas/backend.md](areas/backend.md) — PHP/Symfony/API Platform layer: conventions, directory structure, recent changes
- [areas/frontend.md](areas/frontend.md) — Osoma/React/TypeScript layer: conventions, directory structure, recent changes
- [areas/ci.md](areas/ci.md) — GitHub Actions CI/CD: current workflow order, dependency management, known debt

## Analyses

- [llm-wiki-karpathy-alignment.md](llm-wiki-karpathy-alignment.md) — Assessment of wiki implementation and usage against Karpathy's model, with an actionable gap checklist

---

## Coverage

| Reports ingested | Substantive | Boilerplate |
|-----------------|-------------|-------------|
| 68 | ~18 | ~50 |

Boilerplate reports contain only template text ("Delivers the capability described in the PR title") with no implementation detail. The wiki reflects knowledge from the substantive reports only.
