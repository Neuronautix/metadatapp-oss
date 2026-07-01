# Evolution Report - PR #252

## Merge metadata

- Date: 2026-05-29
- PR: #252
- Title: feat: add new routes and links for creating entities in the application
- Branch: dax/frontend-new-routes
- Contributors: dhuzard
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Added new frontend routes and navigation links for creating entities (investigations, studies, assays, subjects, datasets, and related resources) across 18 changed files (+440 / -24 lines).
- Introduced dedicated create-form routes with proper navigation integration so users can reach creation forms from list views.

## What it brings

- Users can now navigate directly to creation forms for all major entity types from the UI without relying on workarounds or knowing the direct URL.
- List views include "Create" links/buttons that route to the appropriate form.
- Entity creation flows are consistent across all supported resource types.

## Benefits

- User benefit: Creating investigations, studies, assays, subjects, and datasets is now accessible from the navigation UI, reducing friction for new data entry.
- Product benefit: Completes the CRUD surface in the frontend for the core entity types; the application is more usable as a standalone data entry tool.
- Engineering benefit: The route addition follows existing conventions; new create routes are consistent with list/detail/edit patterns.
- Operational benefit: Reduces support requests about "how to create" entities in the UI.

## Long-term vision

- Strategic theme: Complete, intuitive CRUD navigation for all core metadata entity types.
- Horizon impact: Short to medium term — addresses an immediate UX gap; enables more complete E2E test coverage.
- Future opportunities unlocked: Workflow-guided entity creation (e.g. create study from investigation detail page with pre-filled context).

## Risks and tradeoffs

- New routes require matching form components to be complete; any gaps in form functionality will be exposed by the new navigation paths.
- No automated tests for the new routes were explicitly mentioned; existing router tests should be updated.

## Follow-up actions

- [ ] Add router tests for all new create routes (owner: frontend, target: TBD)
- [ ] Verify form completion for all newly accessible create routes (owner: QA, target: TBD)

## References

- Merge commit: caf4717386d77d57ea53536c3292aaadec7c7c2d
