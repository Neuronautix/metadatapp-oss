---
title: "Feature: Metadata Standards Export"
type: feature
updated: 2026-05-05
source_prs: [110, 113, 244]
related: [features/fair-checking.md, decisions/api-platform-conventions.md]
---

# Feature: Metadata Standards Export

## Status
Active — RO-Crate and FAIR² are established and now carry richer linked-resource provenance

## Summary
Metadatapp can export study and dataset metadata in multiple interoperability standards.
This enables downstream consumption by data repositories, ML pipelines, and FAIR assessment tools.

## Key PRs (chronological)

| PR | Date | What changed |
|----|------|--------------|
| #110 | 2026-03-20 | RO-Crate export API and UI trigger |
| #113 | 2026-03-19 | FAIR²/Croissant ML JSON-LD metadata endpoints |
| #244 | 2026-05-05 | Connected resource links embedded in FAIR² JSON-LD and RO-Crate exports for external-study provenance |

## Supported formats

| Format | Standard | Use case | PR |
|--------|----------|----------|----|
| RO-Crate | Research Object Crate (RO-Crate) | Package study data + metadata for archival/sharing | #110 |
| FAIR² | FAIR Digital Object Framework | Machine-readable FAIR metadata | #113 |
| Croissant ML | ML Croissant (Google) JSON-LD | Dataset cards for ML training datasets | #113 |

## Architecture

All export endpoints are built on API Platform 4.3, leveraging the serializer layer for format-specific output.
Frontend provides UI export triggers; the actual export logic lives in backend services.

As of PR #244, exports also carry first-class `ConnectedResourceLink` data:

- FAIR² / Croissant root study nodes emit `sameAs` references for linked external resources.
- FAIR² `@graph` can include dedicated connected-resource nodes with provider, external ID, and sync metadata.
- RO-Crate payloads embed `connectedResourceLinks` so downstream consumers can reconstruct cross-system provenance.

## Known limitations

- Export format completeness is not validated against the full standard specifications (e.g., required RO-Crate fields may be missing).
- No scheduled or bulk export capability.
- No export history or audit trail.
- Connected resource links improve provenance, but there is not yet a generalized export-history model or validation against every downstream consumer.

## Future opportunities

- Schema.org metadata export for search engine indexing
- Dataset landing page with embedded JSON-LD for FAIR discoverability
- Export as part of automated publication workflow (trigger on FAIR score threshold)

## Related

- [features/fair-checking.md](fair-checking.md) — FAIR assessment uses these standards as inputs
