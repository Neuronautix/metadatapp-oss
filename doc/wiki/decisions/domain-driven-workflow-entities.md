---
title: "Decision: Domain-Driven Workflow Entities"
type: decision
updated: 2026-04-09
source_prs: [177, 178, 181, 182, 183, 184, 210]
related: [features/zefix.md, features/curation.md, areas/backend.md]
---

# Decision: Domain-Driven Workflow Entities

## Status
Active

## Context
Complex workflows (Zefix animal lifecycle, metadata curation) could be implemented as stateless
service calls or as explicit entity-backed state machines. A decision was needed.

## Decision
**Major workflows are backed by explicit Doctrine entities with state machines and processors.**
Workflows are not ad-hoc service calls; they are domain objects with persisted state.

Examples:
- Zefix: `Batch`, `MortalityRecord`, `EnvironmentalObservation`, `Alert` are all entities
- Curation: `SessionImport`, `Proposal`, `PatchReview` are all entities
- Processors handle state transitions (e.g., `Proposal` → `accepted`)

## Established by
- PRs #177–184: Zefix domain model built entirely as explicit entities with factories (PR #178)
- PR #210: Curation workflow as `SessionImport` → `Proposal` → `PatchReview` entity chain with `WorkflowProcessor`

## Consequences

**Enables:**
- Auditability: every workflow step is a persisted database record
- Role-based access control at the entity level (Zefix roles, PR #189)
- Undo/replay: entity history provides recovery path
- Testability: Foundry factories allow deterministic test setup (PR #178)
- API exposure: entities become API Platform resources automatically

**Constrains:**
- More upfront entity design work compared to ad-hoc service calls
- Schema migrations required for workflow changes
- Test setup requires Foundry factories (never instantiate entities manually in tests)
