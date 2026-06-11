---
title: "Feature: FAIR Checking & Assessment"
type: feature
updated: 2026-05-05
source_prs: [111, 113, 209, 210, 244]
related: [features/curation.md, features/ai-mcp.md, features/metadata-standards.md, decisions/api-platform-conventions.md]
---

# Feature: FAIR Checking & Assessment

## Status
Active — core capability, expanding into richer reporting and DVC-backed summaries

## Summary
FAIR checking evaluates research metadata against FAIR principles (Findable, Accessible, Interoperable, Reusable).
The platform generates per-criterion assessments, downloadable PDF reports, and exposes the capability through
the AI assistant. This is a primary differentiator for the platform.

## Key PRs (chronological)

| PR | Date | What changed |
|----|------|--------------|
| #111 | 2026-03-19 | Frontend FAIR score panel with per-criterion checklist on Study detail page |
| #113 | 2026-03-19 | FAIR²/Croissant ML JSON-LD metadata endpoints; backend standards compliance |
| #209 | 2026-04-09 | `FairReportController`, `FairReportPdfService`, FAIR report endpoint, MCP tool integration, API + E2E tests |
| #210 | 2026-04-09 | `FairAssessment` domain entity + API resource/provider/service/controller; PDF download endpoint; AI assistant integration path |
| #244 | 2026-05-05 | Structured text-based FAIR/ARRIVE report rendering, investigation/study report hardening, account-boundary tests, experiment summary and circadian support for richer analytics |

## Architecture

```
Frontend (Osoma)
  └── Study detail page → FAIR score panel (PR #111)
  └── Download FAIR report → PDF endpoint

Backend (API Platform)
  └── FairAssessment entity + resource (PR #210)
  └── FairReportController → FairReportPdfService (PR #209)
  └── ArriveReportPdfService + `TextPdfRenderer` (PR #244)
  └── FAIR² / Croissant ML JSON-LD endpoints (PR #113)
  └── DVC experiment summary + cage circadian endpoints (PR #244)
  └── MCP bridge → FAIR checker tool (PR #209)

AI Assistant
  └── MCP bridge dispatches FAIR check as read-only tool
```

## Current capabilities

- FAIR score displayed per criterion on Study detail page
- Backend FAIR assessment stored as a domain entity
- FAIR PDF report downloadable per study and investigation
- ARRIVE helper PDF report downloadable per study and investigation
- FAIR check available as MCP tool from the AI assistant
- JSON-LD metadata export in FAIR²/Croissant ML format (PR #113)
- DVC experiment summary payloads can now include circadian-derived metrics that support downstream assessment and welfare-oriented reporting

## Known limitations & tech debt

- **Scoring calibration:** Domain experts need to validate what each criterion's threshold means; no guidance in the UI. See [tech-debt.md](../tech-debt.md).
- **No policy layer:** FAIR score does not gate any workflow (e.g., cannot block publication below threshold).
- **Scheduled exports:** Not yet implemented; assessments are on-demand only.
- **Assistant authorization:** Rate limiting and access control for assistant-triggered FAIR checks not yet enforced.

## Future opportunities

- Policy layer: block dataset publication until FAIR score meets minimum threshold
- Scheduled background FAIR re-assessment when metadata changes
- FAIR score trend view over time per study
- Export FAIR assessment as part of RO-Crate bundle
- Tie ARRIVE helper output and DVC-derived circadian evidence more explicitly into FAIR explanations

## Related

- [features/curation.md](curation.md) — curation workflow feeds data quality that FAIR assesses
- [features/metadata-standards.md](metadata-standards.md) — RO-Crate, Croissant ML, FAIR² export formats
- [features/ai-mcp.md](ai-mcp.md) — MCP bridge that exposes FAIR check to AI assistant
- [decisions/api-platform-conventions.md](../decisions/api-platform-conventions.md) — how `FairAssessment` is modelled as an API Platform resource
