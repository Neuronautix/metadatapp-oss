# Evolution Report - PR #233

## Merge metadata

- Date: 2026-04-13
- PR: #233
- Title: fix(evolution-report): truncate wiki payload to fix persistent 413 errors
- Branch: copilot/test-ci-pr-merge-report
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Replaced bare file reads in the "Ingest report into wiki" workflow step with a `read(path, max_chars)` helper that truncates content before it enters the GitHub Models API prompt.
- Applied per-file caps: `SCHEMA.md` → 4000 chars, `overview.md` / `tech-debt.md` → 2000 chars each, `INDEX.md` → 800 chars, all feature/decision/area pages → 1000 chars each via a `read_pages()` helper.
- Total wiki context sent to the API reduced from ~59 KB to ~18 KB.
- Added a minimal `test.md` file in the repo root to trigger the workflow end-to-end and confirm the fix holds in production.

## What it brings

- The "Ingest report into wiki" step no longer fails with `413 Payload Too Large` on the GitHub Models API.
- Wiki ingestion runs successfully on every PR merge regardless of how large the wiki has grown.
- The payload cap is proportional: the most structurally important files (SCHEMA, overview, tech-debt) get more characters than individual feature/area pages.

## Benefits

- User benefit: No direct user-facing impact.
- Product benefit: The project knowledge wiki stays up-to-date automatically after each merge, ensuring the AI-queryable project history remains current.
- Engineering benefit: Eliminates the persistent 413 failure mode that had been affecting all 9 wiki-ingest runs since the wiki grew past ~59 KB.
- Operational benefit: No manual intervention needed to keep the wiki ingestion pipeline functional as the wiki grows.

## Long-term vision

- Strategic theme: A self-maintaining, LLM-queryable project knowledge base that grows with the codebase.
- Horizon impact: Short to medium term — removes a hard blocker on the wiki ingestion pipeline; the fix is proportional so it degrades gracefully as the wiki grows further.
- Future opportunities unlocked: With wiki ingestion stable, future enhancements could include incremental ingestion (only changed pages), structured summaries, and automated tech-debt tracking updates.

## Risks and tradeoffs

- Truncation at 1000 chars per feature page is aggressive; nuanced details in longer pages will be omitted from the LLM context during ingestion, potentially producing lower-quality wiki updates.
- The `test.md` file added to the repo root is an artifact of the end-to-end test and has no functional value; it should be cleaned up in a follow-up.
- If the wiki grows significantly further, even the capped payload may eventually approach the API's context limit, requiring a more structural solution (e.g. selective page loading).

## Follow-up actions

- [ ] Remove or repurpose the `test.md` file from the repo root (owner: dhuzard, target: 2026-04-18)
- [ ] Monitor wiki ingest steps on the next 5 merges to confirm 413 errors are fully resolved (owner: dhuzard, target: 2026-04-20)
- [ ] Evaluate increasing per-page char limits or switching to selective page loading if wiki quality degrades (owner: repo maintainers, target: 2026-05-15)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/233
- Changed files: 2 files (+22 / -9 lines, 1 commit)
- Related: PR #228 (SIGPIPE and KeyError fixes), PR #221 (workflow introduction)
