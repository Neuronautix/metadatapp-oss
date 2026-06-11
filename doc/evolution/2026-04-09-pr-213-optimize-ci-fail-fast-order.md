# Evolution Report - PR #213

## Merge metadata

- Date: 2026-04-09
- PR: #213
- Title: Optimize CI fail-fast order and remove redundant image builds
- Branch: copilot/optimize-ci-cd-process
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Reordered CI workflow steps so frontend checks (DVC proxy types, Osoma build, integration tests) run before infrastructure startup.
- Moved PHPUnit ahead of PHPStan and PHP CS in the backend quality gate sequence.
- Removed standalone `castor docker:build` steps that duplicated work already performed by `castor start`.
- Deferred repository caching to the end of the workflow to reduce wasted work on early failures.

## What it brings

- CI pipelines fail faster on the most likely failure categories (frontend regressions, backend test failures) before spending time on Docker infrastructure startup.
- Eliminates duplicate Docker image build steps, reducing total workflow runtime and GitHub Actions minutes consumed.
- The ordering now matches the natural risk/cost tradeoff: cheap static checks and unit tests before expensive infrastructure orchestration.

## Benefits

- User benefit: Faster feedback cycles when pushing changes, with regressions surfacing earlier in the CI run.
- Product benefit: Reduced CI wall-clock time improves contributor experience and decreases time-to-merge for non-infrastructure changes.
- Engineering benefit: Removes redundant infrastructure setup work; the workflow now reflects actual dependencies rather than historical ordering.
- Operational benefit: Lower GitHub Actions consumption for failing PRs; cost per CI run is reduced.

## Long-term vision

- Strategic theme: Lean, high-signal CI that gives fast feedback without wasting compute.
- Horizon impact: Short to medium term — these savings compound across every PR in the project lifetime.
- Future opportunities unlocked: Easy foundation for adding parallel jobs or matrix strategies if test time grows further.

## Risks and tradeoffs

- Reordering steps means a previously passing run order no longer applies; any step ordering assumptions in external scripts or documentation may need updating.
- The removal of explicit `docker:build` steps relies on `castor start` always building before starting services, which is a verified behavior but creates an implicit dependency.

## Follow-up actions

- [ ] Verify CI run times before and after merge to confirm reduction (owner: CI maintainers, target: 2026-04-16)
- [ ] Update AGENTS.md or CI documentation if any step-ordering notes exist elsewhere (owner: repo maintainers, target: 2026-04-16)

## References

- Merge commit: `0119f158a4a206b9473f098693bc54750503e38c`
- Changed files: `.github/workflows/ci.yml` (+19 / -26 lines)
- Related memory: `castor start` already builds the infrastructure before bringing services up (confirmed in `.castor/docker.php`)
