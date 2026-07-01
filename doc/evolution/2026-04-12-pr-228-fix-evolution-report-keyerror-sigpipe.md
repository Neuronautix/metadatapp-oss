# Evolution Report - PR #228

## Merge metadata

- Date: 2026-04-12
- PR: #228
- Title: fix(evolution-report): REPORT_FILE KeyError and SIGPIPE on large diffs
- Branch: copilot/fix-automatic-reporting-issue
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Fixed a `KeyError: 'REPORT_FILE'` crash that affected all evolution-report workflow runs #3–#7: `REPORT_FILE` was written to `$GITHUB_ENV` (which only applies to *subsequent* steps) and then immediately read via `os.environ` in the *same* step. The fix adds an `export REPORT_FILE="$FILENAME"` line so the variable is available to the inline Python block within the same step.
- Fixed a SIGPIPE / exit 141 error on diffs larger than 12 KB: `gh pr diff "$PR_NUMBER" | head -c 12000` triggered SIGPIPE when `head` exited early, and `set -o pipefail` converted that into a hard failure. The fix writes the full diff to a temporary file first, then truncates with a separate `head -c 12000` invocation, avoiding the broken pipe entirely.

## What it brings

- The evolution-report workflow now runs to completion on every PR merge without crashing at the diff-fetching or filename-resolution step.
- Diffs of any size are handled gracefully; only the first 12 KB is passed to the generation prompt.
- The two silent failure modes that had been preventing report generation since the workflow was introduced are eliminated.

## Benefits

- User benefit: No direct user-facing impact.
- Product benefit: Automated evolution reports are now reliably generated for each PR merge, ensuring the documentation corpus stays current.
- Engineering benefit: Eliminates two silent bugs that were masking workflow failures; future failures will surface with meaningful error messages.
- Operational benefit: Removes the manual intervention previously needed to diagnose and backfill missing evolution reports.

## Long-term vision

- Strategic theme: Robust, zero-maintenance automated documentation pipeline.
- Horizon impact: Short term — immediately restores the intended workflow behaviour.
- Future opportunities unlocked: With the core workflow stable, further enhancements (richer prompts, wiki ingestion, changelog generation) can be built on a reliable foundation.

## Risks and tradeoffs

- PRs #221–#226, which failed to generate reports due to these bugs, cannot be regenerated automatically by the workflow; they require manual backfill (addressed in the task that produced this report).
- The 12 KB diff truncation may omit context from large refactoring PRs; a future improvement could increase or make this limit configurable.

## Follow-up actions

- [ ] Manually backfill evolution reports for PRs #216–#233 that missed automated generation (owner: dhuzard/agent, target: 2026-04-13) ← this report is part of that backfill
- [ ] Monitor the next 3 PR-merge workflow runs to confirm the fix holds (owner: dhuzard, target: 2026-04-18)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/228
- Changed files: `.github/workflows/` (+9 / -1 lines, 2 commits)
- Related: PR #221 (workflow introduction), PR #233 (follow-up 413 fix)
