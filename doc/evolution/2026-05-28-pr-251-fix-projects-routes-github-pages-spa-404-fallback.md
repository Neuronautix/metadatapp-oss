# Evolution Report - PR #251

## Merge metadata

- Date: 2026-05-28
- PR: #251
- Title: fix: /projects routes + GitHub Pages SPA 404 fallback
- Branch: copilot/main
- Contributors: Copilot
- Reviewer(s): not recorded in available PR metadata
- Merged by: dhuzard

## What was merged

- Added `osoma/src/features/core/investigations/ProjectsAliasRoutes.tsx` and router wiring so `/projects/*` now redirects to the canonical `/investigations/*` route family.
- Added `osoma/public/404.html` plus a matching `sessionStorage` restore script in `osoma/index.html` so direct deep links on GitHub Pages bounce back into the SPA instead of staying on a static 404 page.
- Expanded `osoma/src/app/router.test.tsx` to cover the new alias routes.

## What it brings

- Restores support for `/projects` URLs even though the frontend now prefers investigation terminology internally.
- Makes direct links to deep SPA routes work on GitHub Pages, which lacks rewrite rules like the primary deployment environment.
- Keeps routing compatibility while letting the application continue moving toward the newer investigation naming.

## Benefits

- User benefit: Old or documented `/projects` links keep working, and GitHub Pages users can open deep links without seeing a dead-end 404.
- Product benefit: Public-preview hosting behaves more like the main deployment instead of exposing a platform-specific routing break.
- Engineering benefit: The alias route module keeps compatibility logic explicit and testable.
- Operational benefit: The GitHub Pages fallback reduces hosting-specific support noise for route access issues.

## Long-term vision

- Strategic theme: Preserve compatibility while the app transitions from legacy route names to the newer metadata terminology.
- Horizon impact: Short term — the fix immediately improved navigation reliability on GitHub Pages.
- Future opportunities unlocked: The same redirect-and-restore approach can be reused if other legacy aliases or static-hosted deep links need support.

## Risks and tradeoffs

- Alias routes create another compatibility layer that must be kept in sync whenever the canonical investigation routes change.
- The GitHub Pages fallback depends on JavaScript and `sessionStorage`, so it is not a substitute for real server rewrites on more advanced hosts.

## Follow-up actions

- [ ] Keep `/projects` aliases aligned with any future changes to the investigation route family (owner: frontend team, target: backlog)
- [ ] Revisit whether other documented legacy route families need the same redirect treatment or can now be retired entirely (owner: frontend/product, target: backlog)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/251
- Changed areas: `osoma/src/features/core/investigations/ProjectsAliasRoutes.tsx`, `osoma/src/app/router.tsx`, `osoma/src/app/router.test.tsx`, `osoma/public/404.html`, `osoma/index.html`
- Validation evidence (tests, checks, metrics): router tests were extended to assert each new `/projects` alias route.
