# Evolution Report - PR #129

## Merge metadata

- Date: 2026-03-23
- PR: #129
- Title: Resolve merge conflicts between copilot/sub-pr-90 and origin/main
- Branch: copilot/sub-pr-90
- Contributors: Copilot
- Reviewer(s): not recorded in available PR metadata
- Merged by: dhuzard

## What was merged

- Resolved a large integration conflict between the `copilot/sub-pr-90` work and upstream changes, preserving MCP-oriented work while incorporating mainline API Platform 4.3 adjustments.
- Kept `Project.php` serving both legacy `Project` and newer `Investigation` API surfaces, while retaining MCP tooling and the full CRUD setup that came from `main`.
- Preserved the advanced `Experiment.php` query behaviour and the renamed frontend structure, including the move from `osoma-demo/` to `osoma/`, while removing tracked build artefacts like `tsconfig.tsbuildinfo`.

## What it brings

- Unblocked the feature branch by reconciling two major streams of work instead of forcing one side to be dropped.
- Kept MCP capabilities and richer API query behaviour available alongside the newer resource naming and frontend layout.
- Reduced the risk that later merges would need to re-solve the same large structural conflicts.

## Benefits

- User benefit: The resulting branch could keep exposing both `/projects` and `/investigations` style access patterns instead of regressing one of them.
- Product benefit: Important platform work from both branches survived the merge instead of being partially overwritten.
- Engineering benefit: The conflict resolution documented how the repo should reconcile API Platform 4.3 resource naming with MCP tooling and the frontend rename.
- Operational benefit: Removing tracked build artefacts lowered future merge noise.

## Long-term vision

- Strategic theme: Keep architectural migration work compatible with automation and assistant-facing APIs.
- Horizon impact: Short to medium term — the value was immediate because it let the combined branch continue shipping.
- Future opportunities unlocked: Later PRs could keep building on the dual-surface `Project`/`Investigation` model and the renamed `osoma/` frontend without reopening this conflict.

## Risks and tradeoffs

- Conflict-resolution PRs carry a high risk of subtle regressions because correctness depends on what was kept or discarded during reconciliation.
- Maintaining dual resource names (`Project` and `Investigation`) increases compatibility but also creates more surfaces that later changes must keep aligned.

## Follow-up actions

- [ ] Monitor `Project` / `Investigation` route and serialization behaviour so future resource changes do not drift across the dual API surfaces (owner: backend team, target: backlog)
- [ ] Continue removing merge-only residue such as stale generated artefacts whenever structural branches are reconciled (owner: maintainers, target: backlog)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/129
- Changed areas: `api/src/Entity/Project.php`, `api/src/Entity/Experiment.php`, `osoma/`, `reference.php`
- Validation evidence (tests, checks, metrics): PR description documents the concrete conflict resolutions but does not list standalone validation commands.
