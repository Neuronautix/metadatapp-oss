# Evolution Report - PR #156

## Merge metadata

- Date: 2026-04-09
- PR: #156
- Title: [WIP] Make FAIR-checker usable for investigation, study, and dataset assessment
- Branch: copilot/check-fair-checker-usability
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Reworked FAIR assessment computation for investigations, studies, and datasets in the Osoma frontend.
- Computed FAIR criteria and aggregate FAIR scores from fetched metadata instead of defaulting to zero values.
- Wired computed FAIR scores into metadata adapters so API-backed resources display accurate FAIR assessments.
- Displayed the FAIR assessment panel on dataset and investigation detail pages (previously only shown for studies).
- Kept the existing study FAIR panel working with the shared scoring logic.
- Added focused frontend integration tests for FAIR scoring and adapter mapping.

## What it brings

- Investigations, studies, and datasets now display a computed FAIR score with a full criteria breakdown instead of empty zeroed values.
- The FAIR assessment panel is visible on investigation and dataset detail pages, matching the study panel behaviour.
- Shared scoring logic reduces duplication and makes future FAIR criteria changes consistent across all entity types.

## Benefits

- User benefit: Researchers and data managers can now assess the FAIRness of their investigations and datasets directly from the UI, enabling targeted data quality improvements.
- Product benefit: FAIR assessment is now a functional, cross-entity feature rather than a partial stub limited to studies.
- Engineering benefit: Shared adapter mapping code reduces the risk of drift between entity-type FAIR panels; integration tests enforce correctness.
- Operational benefit: Fewer support requests around missing or blank FAIR scores in the UI.

## Long-term vision

- Strategic theme: Full FAIR compliance coverage across all metadata entity types.
- Horizon impact: Medium term — unlocks more comprehensive FAIRness reporting and badge workflows.
- Future opportunities unlocked: Aggregate FAIR scores by project, dataset suite, or organisation for compliance dashboards.

## Risks and tradeoffs

- FAIR scoring logic is now duplicated across frontend adapters; backend consolidation may be preferable at scale.
- Integration test coverage uses mock data; edge cases with partial or malformed API responses may not be fully covered.

## Follow-up actions

- [ ] Evaluate moving FAIR score computation to the backend API to avoid frontend duplication (owner: backend team, target: TBD)
- [ ] Add live smoke test for FAIR score panel on investigation and dataset pages (owner: QA, target: TBD)

## References

- Fixes: #155
- Merge commit: 2f26a123a1aa0f22e3757b5257c08006c1a4c5e6
