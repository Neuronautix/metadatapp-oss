# Wiki Index

_Last updated: 2026-06-16_

This is the entry point for the compiled knowledge base. Read this first when answering questions about the project.
The raw sources are in `doc/evolution/` (one file per merged PR). This wiki synthesizes them.

---

## Overview & Cross-Cutting

- [overview.md](overview.md) — Project synthesis: what Metadatapp is, stack, feature areas, strategic direction
- [tech-debt.md](tech-debt.md) — All known tech debt, limitations, and deferred work (with resolution paths)

## Features

- [features/fair-checking.md](features/fair-checking.md) — FAIR assessment, FAIR/ARRIVE reporting, AI assistant integration (PRs #111, #113, #209, #210, #244)
- [features/curation.md](features/curation.md) — Data import, LLM-assisted curation, SessionImport/Proposal/PatchReview (PRs #89, #93, #114, #116, #151, #159, #197, #210)
- [features/metadata-standards.md](features/metadata-standards.md) — Metadata format exports (RO-Crate, FAIR²/JSON-LD) (PRs #110, #113, #244)
- [features/ai-mcp.md](features/ai-mcp.md) — Read-only MCP bridge with user-scoped AI services (PRs #151, #197, #199, #209, #210, #244)
- [features/connected-apps.md](features/connected-apps.md) — Pre-integrated third-party services like `PreclinicalTrials`, `elabFTW`, `Zefix` with enhanced protocol metadata (PRs #180, #303) 
- [features/elabftw.md](features/elabftw.md) — elabFTW integration and synchronization (PRs #115, #202)
- [features/zefix.md](features/zefix.md) — Zefix animal care and lifecycle metadata integration (PRs #177–#205, inclusive)
- [Reference Hub](features/reference-hub.md) — Federated search across phenotype databases and metadata schemas/templates/guidelines

## Areas

- [areas/backend.md](areas/backend.md) — Symfony + API Platform backend with Doctrine entities and `Backend as Source of Truth` architecture
- [areas/frontend.md](areas/frontend.md) — TypeScript + React frontend interface (Osoma)
- [areas/ci.md](areas/ci.md) — CI/CD pipelines, automated workflows, and quality checks

## Decisions

- [decisions/backend-as-source-of-truth.md](decisions/backend-as-source-of-truth.md) — All external APIs accessed through backend services
- [decisions/api-platform-conventions.md](decisions/api-platform-conventions.md) — Uniform patterns for backend resources under API Platform
- [decisions/ai-mcp-read-only.md](decisions/ai-mcp-read-only.md) — AI-tool dispatch constrained to read-only operations
- [decisions/domain-driven-workflow-entities.md](decisions/domain-driven-workflow-entities.md) — Use entities for domain workflows (Zefix, curation)
