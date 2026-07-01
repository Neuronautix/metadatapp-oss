---
title: LLM Wiki Alignment with Karpathy Model
type: analysis
updated: 2026-05-05
source_prs: [233, 244]
related: [SCHEMA.md, INDEX.md, LOG.md, overview.md, tech-debt.md, areas/ci.md]
---

# Analysis: LLM Wiki Alignment with Karpathy Model

## Scope

This page evaluates how well Metadatapp implements and uses the LLM wiki approach described in Karpathy's original model:
https://gist.github.com/karpathy/442a6bf555914893e9891c11519de94f

## Summary Verdict

- Implementation quality: strong (about 8/10)
- Usage quality: good but not yet maximal (about 7/10)

Metadatapp matches the core architecture and workflow pattern very closely, including a canonical schema, index, operation log, and an automated ingest pipeline. The main gap is reliability of ingest quality at scale because automation currently uses payload truncation safeguards.

## Alignment Matrix

| Karpathy model principle | Metadatapp status | Evidence | Assessment |
|---|---|---|---|
| Raw source layer separated from compiled wiki | Implemented | `doc/evolution/` is immutable source set; `doc/wiki/` is compiled knowledge | Strong |
| Explicit ingest/query/lint operations | Implemented | Operation contract defined in `SCHEMA.md` and referenced in `AGENTS.md` | Strong |
| Canonical index for retrieval | Implemented | `INDEX.md` maintained and updated on ingest | Strong |
| Operation logging to preserve memory of edits | Implemented | `LOG.md` with dated operation entries | Strong |
| Query outputs can be filed back into wiki | Partially implemented | Rule exists in `SCHEMA.md`; fewer explicit examples than ingest entries | Moderate |
| Periodic linting and consistency checks | Partially implemented | Lint rules are defined; recurring lint evidence is limited in `LOG.md` | Moderate |
| Scalable context management as wiki grows | Risk present | CI ingest pipeline truncates prompt payload to avoid 413 failures | Moderate risk |

## What Is Working Well

1. Structure follows the model directly.
2. Instructions are explicit and centralized (`SCHEMA.md`, `AGENTS.md`).
3. Wiki maintenance is integrated into CI via evolution-report automation.
4. Coverage and traceability are visible through `INDEX.md` and `LOG.md`.

## Current Gaps

1. Ingest fidelity can drift when context is truncated.
2. Lint is policy-defined but appears less operationalized than ingest.
3. Query-to-page feedback loop is present in rules but less visible in practice.
4. Provenance to exact source lines is not consistently captured for synthesized claims.

## Concrete Improvement Checklist

- [ ] Add retrieval-based ingest context selection instead of fixed truncation windows.
- [ ] Add scheduled wiki lint workflow and append each lint run to `LOG.md`.
- [ ] Add a lint check for orphan pages and stale `updated` dates.
- [ ] Add a lint check that every substantive `doc/evolution/` report is represented in at least one wiki page.
- [ ] Add a convention for claim-level provenance notes when a section is synthesis-heavy.
- [ ] Add a recurring query-ingest task: convert high-value analyses into durable wiki pages.

## Suggested Success Metrics

- Ingest completeness: percentage of substantive reports represented in wiki pages.
- Lint cadence: number of lint runs per month logged in `LOG.md`.
- Drift detection: count of stale pages not updated after related substantive reports.
- Retrieval pressure: average prompt payload size and truncation frequency during automated ingest.

## Re-evaluation Trigger

Re-run this assessment after the next 5 substantive reports or after any change to the wiki automation workflow, whichever comes first.
