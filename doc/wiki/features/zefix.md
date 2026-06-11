---
title: "Feature: Zefix Integration"
type: feature
updated: 2026-04-09
source_prs: [177, 178, 179, 180, 181, 182, 183, 184, 185, 188, 189, 190, 204, 205]
related: [decisions/backend-as-source-of-truth.md, areas/backend.md]
---

# Feature: Zefix Integration

## Status
Active — core domain model complete; hardening and edge-case coverage ongoing

## Summary
Zefix is an animal care and lifecycle management system. Metadatapp integrates with Zefix to capture
and manage animal research data: housing locations, mouse lines, breeding batches, mortality records,
environmental observations, alerts, and CRYO (cryogenic) preservation tracking. The integration was
built systematically across ~12 PRs in a domain-model-first approach.

## Key PRs (chronological)

| PR | Date | What changed |
|----|------|--------------|
| #177 | 2026-04-01 | Zefix backend resources and persistence model (entity foundation) |
| #178 | 2026-04-01 | Deterministic Zefix fixtures and factories for testing |
| #179 | 2026-04-01 | Zefix Location Explorer UI with real backend data |
| #180 | 2026-04-01 | Zefix lines backed with real API |
| #181 | 2026-04-01 | Zefix batches with persisted workflow |
| #182 | 2026-04-01 | Zefix mortality records and batch history persistence |
| #183 | 2026-04-01 | Zefix systems and environmental observations |
| #184 | 2026-04-01 | Zefix alerts and workflow |
| #185 | 2026-04-02 | Zefix dashboard with real aggregates |
| #188 | 2026-04-01 | Zefix exports and line report |
| #189 | 2026-04-02 | Zefix roles and permissions enforcement |
| #190 | 2026-04-02 | (issue #174 — details in report) |
| #204 | 2026-04-09 | CRYO line tracking UI aligned with backend API |
| #205 | 2026-04-09 | Zefix E2E coverage and role-based test hardening |

## Architecture

The Zefix domain is entirely backend-driven. All data flows through Metadatapp's backend API;
the frontend never calls Zefix APIs directly.

```
Frontend (Osoma)
  └── Location Explorer (PR #179)
  └── Lines, Batches, Mortality, Systems, Alerts pages (PRs #180–184)
  └── Dashboard with aggregates (PR #185)
  └── Exports and line reports (PR #188)
  └── CRYO tracking (PR #204)

Backend
  └── Zefix domain entities (locations, lines, batches, mortality, systems, alerts, CRYO)
  └── Factories and fixtures for deterministic test data (PR #178)
  └── Role-scoped API access (PR #189)
  └── Aggregate queries for dashboard (PR #185)
  └── Export services (PR #188)
```

**Build approach:** Domain model first (entities + factories) → UI data binding → core persistence workflows →
backend aggregations → authorization enforcement → specialized tracking → export/reporting.

## Current capabilities

- Location Explorer with real backend data
- Mouse lines with lifecycle data
- Breeding batch creation, history, and mortality records
- Environmental system observations
- Alert workflow
- Dashboard with real aggregates
- Line exports and reports
- CRYO line tracking
- Role-based access control enforced at API level
- E2E test coverage for core flows

## Known limitations & tech debt

- **Edge-case E2E coverage:** Tests cover core flows; partial data, concurrent updates, and permission-denied scenarios not tested. See [tech-debt.md](../tech-debt.md).
- **Mortality data completeness:** Persistence model added but unknown if all Zefix mortality fields are covered per the Zefix data specification.
- **CRYO edge cases:** Cold-chain edge cases (partial thaw, multiple straws per line) not validated.
- **Export formats:** Line reports exist; unknown if all Zefix-mandated export formats are implemented.

## Future opportunities

- Real-time alert notifications (WebSocket/Mercure)
- Automated FAIR assessment triggered on Zefix data import
- Zefix data contributing to FAIR score for animal studies
- Bulk import from existing Zefix installations

## Related

- [decisions/backend-as-source-of-truth.md](../decisions/backend-as-source-of-truth.md) — why Zefix API is never called from frontend
- [areas/backend.md](../areas/backend.md) — backend entity and API patterns used
