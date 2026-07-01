---
title: "Feature: elabFTW Synchronization"
type: feature
updated: 2026-04-09
source_prs: [115, 202]
related: [decisions/backend-as-source-of-truth.md, features/curation.md]
---

# Feature: elabFTW Synchronization

## Status
Active — sync workflow implemented; production hardening needed

## Summary
elabFTW is an open-source electronic lab notebook. Metadatapp synchronizes experiment metadata
between elabFTW and its own data model, enabling researchers to work in either system while
maintaining consistent metadata.

## Key PRs (chronological)

| PR | Date | What changed |
|----|------|--------------|
| #115 | 2026-03-19 | elabFTW HTTP client with response parsing; local mock for dev/test |
| #202 | 2026-04-09 | elabFTW ↔ Metadatapp synchronization workflow |

## Architecture

```
Backend
  └── elabFTW HTTP client (PR #115)
        └── response parsing abstraction
        └── local mock implementation for dev/test (dev environment only)
  └── Sync service (PR #202)
        └── bidirectional data flow between elabFTW and Metadatapp entities
```

**Test approach:** HTTP client has a mock implementation; sync tests run against the mock.
Real elabFTW API changes are a risk (see tech debt).

## Current capabilities

- HTTP client with structured response parsing
- Local mock for test and development (no real elabFTW needed in dev)
- Bidirectional sync workflow

## Known limitations & tech debt

- **API coupling risk:** HTTP client response parsing is sensitive to elabFTW API evolution.
  Mock-driven tests won't catch real API changes. See [tech-debt.md](../tech-debt.md).
- **Conflict resolution:** Unknown if the sync handles concurrent edits (same experiment edited in both systems).
- **Partial sync failure recovery:** Unknown if sync is transactional; partial failures may leave data inconsistent.

## Future opportunities

- Webhook-driven incremental sync (instead of polling)
- Sync audit log for conflict detection and manual resolution
- Sync as a trigger for FAIR re-assessment

## Related

- [decisions/backend-as-source-of-truth.md](../decisions/backend-as-source-of-truth.md) — sync logic is entirely backend-side
- [features/curation.md](curation.md) — synced elabFTW data can feed the curation workflow
