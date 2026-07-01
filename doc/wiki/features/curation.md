---
title: "Feature: Data Import & Curation Workflow"
type: feature
updated: 2026-04-09
source_prs: [89, 93, 114, 116, 151, 159, 197, 210]
related: [features/fair-checking.md, features/ai-mcp.md, decisions/domain-driven-workflow-entities.md, areas/backend.md, areas/frontend.md]
---

# Feature: Data Import & Curation Workflow

## Status
Active — full workflow backend landed (PR #210); frontend UI screens in progress

## Summary
The curation workflow allows researchers to import experimental metadata (CSV, XLSX, spreadsheet grids),
map it to the Metadatapp data model, resolve inconsistencies, and review/apply structured patches.
LLM assistance (via CurateGPT) accelerates the mapping and resolution steps.

## Key PRs (chronological)

| PR | Date | What changed |
|----|------|--------------|
| #89 | 2026-03-16 | Import and map entities (early foundation) |
| #93 | 2026-03-19 | Read and parse Excel files server-side |
| #114 | 2026-03-19 | CSV/XLSX mouse data import with curation workflow integration |
| #116 | 2026-03-19 | Experimental data import — spreadsheet grid with subject ID validation |
| #151 | 2026-03-24 | Unify LLM subject curation; integrate CurateGPT as a provider |
| #159 | 2026-04-02 | Auto-fill fields API (re-added) |
| #197 | 2026-04-03 | AI foundation vertical slice integration |
| #210 | 2026-04-09 | Full curation workflow backend: import sessions, proposals, patches, patch review; frontend workflow screens |

## Architecture

### Backend entities (as of PR #210)

```
SessionImport entity
  └── represents a single import operation (CSV/XLSX + mapping config)
  └── persisted, auditable, restorable

Proposal entity
  └── structured suggestion for a metadata change (from LLM or manual)
  └── lifecycle: draft → reviewed → accepted / rejected

PatchReview entity
  └── records a human reviewer's decision on a proposal
  └── supports partial acceptance

WorkflowProcessor
  └── orchestrates state transitions across SessionImport → Proposal → PatchReview
```

### Frontend workflow screens (as of PR #210)

```
Import screen → Mapping screen → Resolution screen → Patch Review screen
```

### LLM integration

```
CurateGPT provider (PR #151)
  └── abstracted behind curation provider interface
  └── generates Proposals from import sessions
  └── provider interface allows future additional LLM backends

Auto-fill API (PR #159)
  └── suggests field values based on existing data patterns
```

## Current capabilities

- Server-side Excel/CSV parsing
- Subject ID validation on spreadsheet import
- Full import session lifecycle (entity-backed, auditable)
- Proposal generation and lifecycle management
- Patch review workflow
- LLM-assisted curation via CurateGPT
- Auto-fill field API
- Frontend workflow screens for import → mapping → resolution → patch review

## Known limitations & tech debt

- **Negative-path coverage:** Import session tests cover happy path; authorization-denied and invalid-payload scenarios not tested. See [tech-debt.md](../tech-debt.md).
- **Multi-provider orchestration:** CurateGPT provider interface exists but logic for running multiple providers simultaneously is not implemented.
- **Large-scope regression risk:** PR #210 combined this workflow with FAIR assessment and AI integration; cross-boundary test coverage is incomplete.

## Future opportunities

- Scheduled background re-assessment of import sessions against updated ontologies
- Batch import from connected lab systems (elabFTW, Zefix)
- Proposal confidence scores from LLM to guide reviewer attention
- Undo/replay support via the persisted patch review chain

## Related

- [features/fair-checking.md](fair-checking.md) — FAIR assessment runs on curated data
- [features/ai-mcp.md](ai-mcp.md) — AI assistant can trigger curation steps via MCP tools
- [features/elabftw.md](elabftw.md) — elabFTW sync feeds into import workflow
- [decisions/domain-driven-workflow-entities.md](../decisions/domain-driven-workflow-entities.md) — why import/proposals/patches are explicit entities
