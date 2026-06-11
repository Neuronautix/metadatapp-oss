# Evolution Report - PR #220

## Merge metadata

- Date: 2026-04-12
- PR: #220
- Title: Restore dedicated research routes and FAIR navigation in Osoma
- Branch: copilot/fix-frontend-issues
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Restored `/investigations` routing to the existing projects-backed investigation screens instead of redirecting into generic metadata lists.
- Restored `/studies` and `/samples` to their dedicated feature pages and detail/edit screens.
- Kept `/assays` on direct feature routes for path consistency across research entities.
- Renamed the primary investigation sidebar/header entry to **Projects** while preserving the existing route structure.
- Added a dedicated **FAIR** section in the left navigation, making FAIR-related tools directly discoverable instead of buried in mixed sections.
- Restored FAIR score/checker panel visibility on project detail pages by routing investigations back to the dedicated project view.
- Added a synthetic primary-label column to metadata tables when the resource's configured list fields contain no human-readable name/title field, preventing action-only rows.
- Updated router tests to assert the restored direct routes and extended metadata list tests to cover the fallback primary-label column.

## What it brings

- Research workflows (Investigations → Projects, Studies, Samples, Assays) are navigable through semantically correct routes again.
- The FAIR checker/score panel is visible on project detail pages without requiring workarounds.
- Generic metadata tables always display at least one human-readable column regardless of schema configuration.
- Router and component tests lock in the restored route/navigation semantics against future regressions.

## Benefits

- User benefit: Researchers can navigate to Projects, Studies, Samples, and Assays without hitting dead-end redirects; FAIR information is prominently accessible in the left nav.
- Product benefit: Navigation now matches domain language (ISA hierarchy: Investigations/Projects → Studies → Samples → Assays), improving product coherence.
- Engineering benefit: Route tests provide regression guards for the restored paths; the primary-label fallback eliminates a class of blank-table edge cases.
- Operational benefit: Reduces support load caused by users encountering broken navigation flows.

## Long-term vision

- Strategic theme: Consistent, ISA-aligned navigation as the backbone of the research data management UX.
- Horizon impact: Short to medium term — this restoration unblocks adoption of the FAIR checking workflows.
- Future opportunities unlocked: A correctly structured navigation hierarchy makes it easier to add ISA-compliant export, filtering, and cross-entity analytics in future sprints.

## Risks and tradeoffs

- Renaming the sidebar label from "Investigations" to "Projects" may confuse users familiar with the previous label if no changelog or tooltip is provided.
- The synthetic primary-label column is a fallback heuristic; schemas that intentionally have no display field may produce unexpected column titles.

## Follow-up actions

- [ ] Verify FAIR checker panel is visible in production on a real project detail page after deploy (owner: dhuzard, target: 2026-04-20)
- [ ] Add tooltip or migration note for the "Projects" label rename to help existing users (owner: osoma-worker, target: 2026-04-25)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/220
- Changed files: 9 files (+229 / -74 lines, 4 commits)
- Visual evidence: project page and FAIR checker screenshots linked in PR description
