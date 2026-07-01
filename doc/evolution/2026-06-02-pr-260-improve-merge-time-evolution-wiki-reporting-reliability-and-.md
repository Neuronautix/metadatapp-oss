# Evolution Report - PR #260

## Merge metadata

- Date: 2026-06-02
- PR: #260
- Title: Improve merge-time evolution/wiki reporting reliability and safety
- Branch: copilot/improve-dic-evolution-reporting
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Reworked `.github/workflows/evolution-report.yml` so existing reports no longer suppress wiki ingest, using `REPORT_EXISTS` instead of the earlier skip logic.
- Hardened the wiki-ingest write path by validating that model-returned files stay within `doc/wiki/**`, reject absolute or escaping paths, and contain string content before writing.
- Improved workflow robustness with per-run branch names, `git checkout -B`, a staged-diff guard for empty commits, and aligned documentation in `doc/README.md`.

## What it brings

- Keeps wiki ingestion running even when an evolution report already exists, so later reruns can still refresh derived docs.
- Reduces the chance that malformed model output or branch-reuse collisions break the merge-time documentation pipeline.
- Makes the workflow safer by constraining where model-generated files may be written.

## Benefits

- User benefit: Maintainers get a more reliable merge-documentation loop with fewer silent failures and fewer manual cleanups.
- Product benefit: The evolution-report + wiki pipeline becomes a sturdier part of the repository knowledge system.
- Engineering benefit: Path validation and empty-commit guards make the automation easier to trust and debug.
- Operational benefit: Reruns no longer need bespoke intervention just because a report file already exists.

## Long-term vision

- Strategic theme: Turn merge-time documentation into a reliable, bounded automation rather than a fragile best-effort workflow.
- Horizon impact: Short to medium term — the safety improvements pay off on every subsequent merged PR.
- Future opportunities unlocked: More advanced wiki ingest or derived reporting can now build on a workflow that better handles reruns and untrusted model output.

## Risks and tradeoffs

- The workflow still depends on GitHub Models responses and on the quality of the source evolution report, so automation cannot fully replace review.
- Additional path and type validation adds safety but also means future wiki-ingest changes must respect the stricter contract.

## Follow-up actions

- [ ] Monitor the next evolution-report workflow reruns to confirm the new `REPORT_EXISTS` flow and safe write checks behave as intended (owner: maintainers, target: backlog)
- [ ] Keep `doc/README.md` and workflow behaviour aligned whenever merge-time report or wiki automation changes again (owner: maintainers, target: backlog)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/260
- Changed areas: `.github/workflows/evolution-report.yml`, `doc/README.md`
- Validation evidence (tests, checks, metrics): the workflow now includes safe path checks for `doc/wiki/**`, rerun-safe branch naming, and a no-op guard when nothing is staged.
