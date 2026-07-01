# Evolution Report - PR #203

## Merge metadata

- Date: 2026-04-07
- PR: #203
- Title: refactor: update terminology from 'Experiments' to 'Studies' across multiple components
- Branch: Neuronautix:feat/metadatapp-admin-ui-global-improvement
- Contributors: feat branch maintainer
- Reviewer(s): TBD

## What was merged

- Updated core terminology in metadatapp resource pages and registry mappings from "Experiments" to "Studies".
- Updated end-to-end test coverage in resource flows to reflect the new domain wording.
- Aligned user-facing language across listing and detail views to reduce conceptual mismatch.

## What it brings

- Improves domain language consistency between UI, tests, and resource metadata.
- Reduces onboarding and interpretation friction caused by mixed terms for the same concept.
- Sets the base for future features to use a single canonical term.

## Benefits

- User benefit: More understandable vocabulary in everyday navigation and detail pages.
- Product benefit: Strengthens domain narrative consistency across product surfaces.
- Engineering benefit: Fewer term-mapping ambiguities in UI registry code and tests.
- Operational benefit: Better consistency in documentation, support communication, and QA scripts.

## Long-term vision

- Strategic theme: Domain model clarity and shared language across teams.
- Horizon impact: Medium term, because terminology consistency is foundational for later workflow and analytics work.
- Future opportunities unlocked: Cleaner documentation, simpler training material, and lower semantic drift in future PRs.

## Risks and tradeoffs

- Remaining references to legacy terms may still exist outside updated components and tests.
- Terminology migration can introduce temporary confusion if external docs are not updated in sync.

## Follow-up actions

- [ ] Run a repository-wide terminology audit for lingering "experiment" labels in user-facing contexts (owner: frontend maintainers, target: 2026-04-18)
- [ ] Update product docs/screenshots to match "Studies" wording (owner: product documentation, target: 2026-04-18)

## References

- Merge commit: 08e443c01faeeec2abf94b25fa07929384a8acf1
- Key files: osoma/src/features/system/metadatapp/MetaDatResourceListPage.tsx, osoma/src/features/system/metadatapp/MetaDatResourceDetailPage.tsx, osoma/src/metadatapp/registry.ts, e2e/tests/resources/experiments.spec.ts
