## [2026-06-29] lint | wiki quality check

- Operation: lint
- Source: scheduled
- Pages touched: INDEX.md, LOG.md
- Issues found: 119
- Notes: boilerplate: 2; coverage-drift: 106; missing-frontmatter: 2; orphan: 1; stale: 8

---
### Wiki Log

#### [2026-06-09] Ingested PR #287

- Automated weekly wiki lint run (PR #287) with 108 issues flagged.
  - Major issues identified:
    - Coverage Drift: Missing `source_prs` in frontmatter on certain pages.
    - Stale Pages: Pages marked `updated` more than 60 days ago.
    - Minor issues such as boilerplate documentation.

- Follow-up Actions:
  - Update `wiki-lint.yml` workflow to fail explicitly if `gh pr create` encounters errors (owner: @dhuzard, target: 2026-06-16).
  - Address 108 identified issues documented in `doc/wiki/lint-report.json` (owner: Documentation team, target: 2026-07-01).
  - Investigate options for automating recurring wiki lint fixes (owner: @dhuzard, target: 2026-07-10).

- Impact:
  - Highlights areas requiring attention to maintain wiki alignment with repository changes.
  - Incrementally improves cross-reference coverage and documentation accuracy.

- No new feature pages created or removed.
