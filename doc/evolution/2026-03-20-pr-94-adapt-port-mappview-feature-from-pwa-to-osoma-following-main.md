# Evolution Report - PR #94

## Merge metadata

- Date: 2026-03-20
- PR: #94
- Title: adapt: port MappView feature from pwa/ to osoma/ following main branch restructure
- Branch: copilot/add-mappview-page
- Contributors: Copilot
- Reviewer(s): not recorded in available PR metadata

## What was merged

- Re-homed the MappView schema explorer from the removed `pwa/` frontend into `osoma/`, matching the repository's new single-frontend layout.
- Added `osoma/src/features/curation/MappViewPage.tsx` plus `MappViewPage.test.tsx`, carrying over the schema-tree explorer and JSON-LD export workflow onto the current data-fetching and routing stack.
- Wired the feature into `osoma/src/app/router.tsx` and `osoma/src/app/layout/Sidebar.tsx` so the page became reachable from the active workspace navigation.

## What it brings

- Preserves the MappView work instead of losing it when the older `pwa/` application disappeared from `main`.
- Gives users a live schema-tree visualisation entry point in Osoma, including a JSON-LD export path tied to the active frontend stack.
- Keeps future schema-exploration work on the maintained frontend rather than on a retired application.

## Benefits

- User benefit: Researchers can inspect metadata structure and export JSON-LD from the interface that is actually deployed and maintained.
- Product benefit: The schema-visualisation feature survived the frontend restructure instead of remaining stranded on a dead branch layout.
- Engineering benefit: The port followed existing Osoma patterns (`resourceRegistry`, `useQuery`, current router/sidebar wiring) rather than carrying old `pwa/` assumptions forward.
- Operational benefit: Team effort stays focused on one frontend codebase.

## Long-term vision

- Strategic theme: Consolidate advanced metadata tooling into the active Osoma experience.
- Horizon impact: Medium term — this established the maintained home for later MappView and JSON-LD work.
- Future opportunities unlocked: The same route can evolve into richer schema browsing, metadata authoring, and import / mapping flows without reviving the old frontend.

## Risks and tradeoffs

- The port depended on the current `resourceRegistry` and API helpers, so any schema gaps in those layers could still limit what MappView can display.
- Large surrounding frontend restructure in the same period meant route or navigation regressions needed monitoring even after the page landed.

## Follow-up actions

- [ ] Extend the MappView route as schema coverage grows so more resources and export cases remain first-class in Osoma (owner: frontend team, target: backlog)
- [ ] Revisit whether the page should evolve from a viewer into a broader metadata authoring or import surface, as described in the originating issue (owner: product/frontend, target: backlog)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/94
- Changed areas: `osoma/src/features/curation/MappViewPage.tsx`, `osoma/src/features/curation/MappViewPage.test.tsx`, `osoma/src/app/router.tsx`, `osoma/src/app/layout/Sidebar.tsx`
- Validation evidence (tests, checks, metrics): PR description states 8 Vitest / RTL checks were added in `MappViewPage.test.tsx`.
