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

### Workflow control flow
- Replaced the `SKIP` variable with `REPORT_EXISTS` so that existing reports no longer suppress wiki ingestion (only INDEX.md update is skipped when a report already exists).
- Wiki ingest and documentation PR creation now always run, even when the evolution report pre-existed.

### Operational robustness
- Branch naming now includes `${GITHUB_RUN_ID}` to prevent collision on reruns.
- Switched to `git checkout -B` for deterministic branch handling.
- Added a staged-diff guard: commit and PR creation are skipped when no doc/wiki changes are produced, avoiding empty commits.

### LLM write-path hardening for wiki ingest
- Added strict validation for the model-returned file map shape (`files` must be a `dict`).
- Enforced writes to `doc/wiki/**` only: absolute paths, non-wiki paths, and path-escape attempts are rejected.
- Enforced `str` content type per returned file before writing.

### Documentation
- Updated `doc/README.md` workflow section to reflect current automation: report generation/reuse, wiki ingest via schema, and review PR creation.

## What it brings

- Wiki ingest is now consistent: it runs regardless of whether the evolution report was pre-existing, closing a gap where wiki knowledge updates were silently skipped.
- The write-path safety gates prevent malformed or malicious LLM output from writing outside `doc/wiki/` or writing non-string content.
- Rerun-safe branch naming avoids "branch already exists" failures on workflow retries.

## Benefits

- User benefit: Not directly user-facing.
- Product benefit: The evolution report and wiki update pipeline is more reliable; wiki knowledge accumulates correctly on every merge.
- Engineering benefit: Path validation and content-type enforcement protect the repository from unexpected LLM output side effects.
- Operational benefit: Fewer manual interventions needed when the evolution-report workflow fails or needs a rerun.

## Long-term vision

- Strategic theme: Robust, safe LLM-driven documentation automation that requires minimal human oversight.
- Horizon impact: Medium term — reliability improvements compound as the volume of automated merges grows.
- Future opportunities unlocked: The hardened ingest pipeline can be extended with selective context retrieval to improve wiki quality at scale.

## Risks and tradeoffs

- The documentation PR pattern still creates a review loop (evolution-report PR merge triggers another evolution-report run); this is addressed in PR #263.
- Path validation adds a strict allow-list; future wiki restructuring (moving files outside `doc/wiki/`) requires updating the validation logic.

## Follow-up actions

- [ ] Switch from documentation PR to direct main commit to eliminate the merge loop (see PR #263)
- [ ] Add selective context retrieval for wiki ingest to replace fixed truncation caps (owner: automation, target: TBD)

## References

- Merge commit: 1cef6614d4daac5cbf1baa9e4607509a8c15a910
