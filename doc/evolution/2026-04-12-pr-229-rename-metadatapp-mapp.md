# Evolution Report - PR #229

## Merge metadata

- Date: 2026-04-12
- PR: #229
- Title: Rename MetaDatApp/MetaDatAPI to Metadatapp/MAPP throughout codebase
- Branch: copilot/replace-meta-dat-app-text
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Renamed all occurrences of `MetaDatApp` → `Metadatapp` and `MetaDatAPI` → `MAPP API` across config files, generated artifacts, feature flags, UI strings, component names, and test files.
- **Config and generated artifacts:** `api_platform.yaml`, `docs.json`, `metadatapp.json`, `DvcProxyApi.openapi.yaml`.
- **Feature flags:** label and description strings in `flags.ts` (`MetaDatAPI Features` → `MAPP API Features`, etc.).
- **UI strings:** error messages, subtitles, and fallback descriptions in resource pages, `LegacyMetaAliases.tsx`, and `OrganizationsAliasRoutes.tsx`.
- **Component and file renames:**
  - `MetaDatAlias` → `MappAlias`
  - `MetaDatAppIndexPage` → `MappIndexPage`
  - `MetaDatResourceListPage` → `MappResourceListPage`
  - `MetaDatResourceDetailPage` → `MappResourceDetailPage`
  - `MetaDatResourceEditPage` → `MappResourceEditPage`
  - `MetaDatResourceCreatePage` → `MappResourceCreatePage`
- `router.tsx` and all test files updated to reflect new names and paths.
- Domain-internal types (`MetaDataset`, `MetaInvestigation`, `MetaStudy`, etc.) intentionally left unchanged — the `Meta` prefix there is a data-model convention, not the app name.

## What it brings

- A consistent, canonical brand identity (`Metadatapp` / `MAPP`) across the entire codebase, replacing the previous inconsistent mix of `MetaDatApp` and `MetaDatAPI`.
- Cleaner component names that follow standard PascalCase conventions and are easier to search and reference.
- No behavior changes; the rename is purely textual/naming.

## Benefits

- User benefit: UI-facing strings now display the canonical app name consistently.
- Product benefit: A single, unambiguous brand name reduces confusion in documentation, demos, and communications.
- Engineering benefit: Consistent naming reduces cognitive load when navigating the codebase and searching for components.
- Operational benefit: Generated API docs and OpenAPI specs now advertise the correct product name to external consumers.

## Long-term vision

- Strategic theme: Brand and naming consistency as a foundation for public-facing documentation and external integrations.
- Horizon impact: Short term — a one-time cleanup that prevents ongoing confusion.
- Future opportunities unlocked: Stable, canonical naming makes it easier to generate marketing materials, external docs, and SDK documentation automatically.

## Risks and tradeoffs

- Any external integrations, bookmarks, or documentation that referenced the old `MetaDatApp` / `MetaDatAPI` names will need to be updated separately.
- Domain-internal `Meta*` types are deliberately excluded from the rename; contributors must be aware of the distinction to avoid accidental renames in future PRs.

## Follow-up actions

- [ ] Search for any remaining `MetaDatApp` or `MetaDatAPI` references in external docs, wikis, or READMEs and update them (owner: dhuzard, target: 2026-04-25)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/229
- Changed files: 18 files (+62 / -62 lines, 2 commits)
