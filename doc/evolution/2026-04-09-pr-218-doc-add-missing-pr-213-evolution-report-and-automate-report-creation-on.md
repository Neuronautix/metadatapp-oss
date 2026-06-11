# Evolution Report - PR #218

## Merge metadata

- Date: 2026-04-09
- PR: #218
- Title: doc: add missing PR #213 evolution report and automate report creation on merge
- Branch: copilot/update-doc-report-creation
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Added the missing evolution report for PR #213 (`doc/evolution/2026-04-09-pr-213-optimize-ci-fail-fast-order.md`).
- Updated `doc/evolution/INDEX.md` with the PR #213 entry.
- Introduced `.github/workflows/evolution-report.yml`: a workflow triggered on `pull_request closed` + `merged == true` to `main` that auto-generates evolution reports on every PR merge, idempotently skipping if the report already exists.

## What it brings

- Evolution reports are now generated automatically after every PR merge, eliminating the manual documentation step.
- The workflow is idempotent: running it on a PR that already has a report skips generation without error.
- Workflow failures surface as GitHub Actions `::warning::` annotations with a manual fallback link, rather than silently swallowing errors.
- Standardised file-naming convention (`YYYY-MM-DD-pr-<number>-<short-slug>.md`) is enforced by the automation.

## Benefits

- User benefit: No direct user-facing impact; supports the team's ability to track product evolution accurately.
- Product benefit: Every merged PR now produces a documented evolution record automatically, enabling cleaner release notes and product-decision history.
- Engineering benefit: Removes the manual effort of writing evolution reports; agents and reviewers can focus on content quality over logistics.
- Operational benefit: The evolution report corpus grows consistently with every merge, becoming an authoritative and searchable history.

## Long-term vision

- Strategic theme: Continuous, automated documentation that keeps pace with the development cycle.
- Horizon impact: Long term — the value compounds as the evolution report corpus grows and becomes the source of truth for wiki ingestion.
- Future opportunities unlocked: Automated changelog generation, sprint reviews, and LLM-based project-status queries driven by the corpus.

## Risks and tradeoffs

- The generated report opens a separate documentation PR, which itself triggers the workflow on merge; this creates a potential documentation loop (addressed in subsequent PRs).
- Report quality depends on the LLM having access to an accurate PR diff and body; truncation at 12 KB may miss context for large PRs.

## Follow-up actions

- [ ] Address the documentation-PR merge loop by committing directly to main instead of opening a PR (owner: automation team, target: see PR #263)
- [ ] Add LLM-quality review for generated reports to catch generic placeholder content (owner: TBD, target: TBD)

## References

- Merge commit: 532179e7c3e2eb6f627b161a8ce4ec3e5a191fd4
