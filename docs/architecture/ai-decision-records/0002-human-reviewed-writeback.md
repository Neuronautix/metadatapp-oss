# ADR 0002: Require human-reviewed write-back for AI curation

- **Status:** Accepted
- **Date:** 2026-03-23

## Context

AI-assisted metadata curation can accelerate review, but direct writes from raw model output create audit, safety, and reproducibility risks.

## Decision

Metadatapp will only persist AI-generated curation changes after structured validation, provenance checks, and human approval.

## Consequences

- AI workflows must emit patch proposals instead of direct entity mutations
- Approval state becomes part of the trace record
- Write-enabled MCP tools remain opt-in and disabled by default
