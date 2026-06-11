---
title: Project Overview
type: overview
updated: 2026-05-05
source_prs: [92, 96, 109, 110, 111, 113, 114, 116, 120, 123, 128, 139, 151, 186, 196, 197, 199, 202, 203, 204, 207, 209, 210, 213, 244]
related: [areas/backend.md, areas/frontend.md, areas/ci.md, tech-debt.md]
---

# Project Overview

## What Metadatapp is

Metadatapp is a **metadata curation and FAIR assessment platform** for life science research data.
It enables research teams to capture, structure, validate, and export experimental metadata
in compliance with FAIR principles (Findable, Accessible, Interoperable, Reusable).

**Core capabilities (as of 2026-05-05):**
- Metadata capture and structured editing for experiments, subjects, and datasets
- FAIR scoring, assessment reporting, and downloadable PDF reports
- Multi-standard metadata export: RO-Crate, Croissant ML, FAIR²/JSON-LD
- LLM-assisted metadata curation (CurateGPT integration)
- User-scoped encrypted AI provider credential management with masked-hint responses
- Connected-resource provenance links on studies, surfaced in UI and metadata exports
- DVC circadian metrics, cage analytics, and experiment summary endpoints for welfare dashboards
- Zefix animal care integration (locations, lines, batches, mortality, systems, alerts, CRYO tracking)
- elabFTW synchronization for lab notebook data
- Live sensor agent demo integration
- AI assistant with read-only MCP tool dispatch
- Role-based access control via Keycloak

## Stack

| Layer | Technology |
|-------|-----------|
| Backend API | PHP 8.4 + Symfony 7.4 + API Platform 4.3 + Doctrine ORM 3.x |
| Frontend | TypeScript + React 18 + Vite 5 (Osoma) |
| Auth | Keycloak OIDC |
| Task runner | Castor |
| Infrastructure | Docker Compose + Traefik + FrankenPHP |

**Single frontend:** Osoma is the sole frontend since PR #96 (March 2026, replaced the legacy PWA).

## Architecture principles

1. **Backend as source of truth** — all external API access (Zefix, elabFTW, sensors, Connected Apps) goes through backend service layers, never directly from the frontend. See [decisions/backend-as-source-of-truth.md](decisions/backend-as-source-of-truth.md).
2. **API Platform 4.3 as uniform REST layer** — resources, state processors, and serializers define the API surface. Controllers are rare and justified. See [decisions/api-platform-conventions.md](decisions/api-platform-conventions.md).
3. **Domain-driven workflow entities** — major workflows (curation, Zefix operations) are backed by explicit Doctrine entities with state machines and processors, not ad-hoc service calls.
4. **AI integration is read-only** — the MCP bridge enforces read-only tool dispatch; no state mutations from AI-driven flows. See [decisions/ai-mcp-read-only.md](decisions/ai-mcp-read-only.md).
5. **RBAC via Keycloak** — organization-scoped permissions enforced at the controller level.

## Feature areas

| Feature | Status | Wiki page |
|---------|--------|-----------|
| FAIR checking & assessment | Active | [features/fair-checking.md](features/fair-checking.md) |
| Data import & curation workflow | Active | [features/curation.md](features/curation.md) |
| Zefix integration | Active | [features/zefix.md](features/zefix.md) |
| elabFTW synchronization | Active | [features/elabftw.md](features/elabftw.md) |
| AI assistant & MCP bridge | Active | [features/ai-mcp.md](features/ai-mcp.md) |
| Metadata standards export | Active | [features/metadata-standards.md](features/metadata-standards.md) |
| Sensor integration | Demo | [features/sensors.md](features/sensors.md) |
| Osoma frontend | Active | [areas/frontend.md](areas/frontend.md) |

## Current state (2026-05-05)

The project has undergone a significant acceleration since March 2026. Key milestones:
- **Osoma promoted as sole frontend** (PR #96), removing PWA technical debt
- **API Platform 4.3 + MCP integration** (PR #123/139) established the modern API foundation
- **Zefix ecosystem built out** systematically across ~12 PRs (PRs #177–205), covering the full domain model
- **FAIR assessment productized** from frontend display (PR #111) through full backend reporting and AI integration (PRs #209, #210)
- **Full curation workflow** landed in PR #210: import sessions → proposals → patch review → resolution
- **CI optimized** for fail-fast ordering (PR #213)
- **Open-source readiness hardening** accelerated in PR #244: public-safe README framing, credential handling guidance, fork-safe CI behavior, and clean-clone smoke validation
- **Interoperability became more explicit** in PR #244 through connected resource links embedded in Study UI, FAIR² JSON-LD, and RO-Crate exports
- **AI configuration became safer** in PR #244 via encrypted user-scoped provider credentials behind `SecretStoreInterface`
- **DVC analytics/reporting expanded** in PR #244 with circadian metrics, experiment summaries, and ARRIVE helper reports

## Strategic direction

- Tighten FAIR quality gates and integrate assessment into the experiment lifecycle
- Expand AI assistant capabilities (currently read-only MCP tools) toward guided curation suggestions
- Complete managed-secret-store support for production deployments and public-release readiness
- Harden Zefix integration from demo-quality to production (especially CRYO tracking and exports)
- Multi-standard metadata export as a competitive differentiator
