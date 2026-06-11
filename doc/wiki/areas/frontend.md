---
title: "Area: Frontend (Osoma)"
type: area
updated: 2026-05-05
source_prs: [92, 96, 120, 128, 203, 207, 209, 210, 244]
related: [decisions/backend-as-source-of-truth.md, features/zefix.md, features/curation.md, features/fair-checking.md]
---

# Area: Frontend (Osoma)

## Stack

| Component | Version / Tool |
|-----------|---------------|
| Language | TypeScript |
| Framework | React 18 |
| Build tool | Vite 5 |
| Package manager | pnpm |
| Data fetching | @tanstack/react-query + `apiFetch()` from `lib/api.ts` |
| API types | Generated from `api/public/docs.json` via `pnpm run openapi:types:dvc-proxy` |
| Mocking | MSW (disable with `localStorage.setItem('use_msw', 'false')`) |
| E2E tests | Playwright |

## Key conventions

1. **Osoma is the sole frontend** — the legacy PWA and `osomapp`/`pwa` paths are removed (PR #96). Do not reintroduce them.
2. **Feature code in `osoma/src/features/`** — app bootstrapping in `osoma/src/app/`.
3. **Data fetching via `@tanstack/react-query` + `apiFetch()`** — no direct fetch/axios calls.
4. **Never call Connected Apps APIs directly** — always through the backend.
5. **MSW for development mocking** — toggle off with localStorage flag when you need real API.
6. **E2E tests: never use `page.waitForNetworkIdle()`** — use explicit selectors and assertions.
7. **Terminology:** Studies (not Experiments) since PR #203.

## Directory structure

| Directory | Purpose |
|-----------|---------|
| `features/` | Self-contained product features |
| `app/` | App shell, routing, auth providers |
| `components/` | Shared UI primitives |
| `lib/api.ts` | `apiFetch()` + react-query patterns |
| `domain/` | Domain types and generated OpenAPI types |
| `mocks/` | MSW handlers |

## Recent significant changes

| PR | Date | Change |
|----|------|--------|
| #244 | 2026-05-05 | Study connected-resource panel, AI Providers settings hardening with masked credential hints, and release-oriented UI/documentation adjustments |
| #210 | 2026-04-09 | Curation workflow UI screens (import → mapping → resolution → patch review) |
| #207 | 2026-04-09 | Routing fixes, metadata catalog UX, label readability, dark-mode contrast |
| #205 | 2026-04-09 | Zefix E2E hardening; role-persistence storage key fix; stable selectors |
| #204 | 2026-04-09 | CRYO tracking UI aligned with backend |
| #203 | 2026-04-07 | Terminology refactor: Experiments → Studies across UI, tests, registry |
| #128 | 2026-03-19 | Cmd+K / Ctrl+K quick-jump search bar |
| #120 | 2026-03-19 | Dark/light mode toggle in Topbar |
| #96 | 2026-03-17 | PWA frontend removed; Osoma as sole frontend |
| #92 | 2026-03-17 | Rename: osoma-demo → osoma |

## Active feature areas

- Zefix UI: location explorer, lines, batches, mortality, systems, alerts, CRYO — [features/zefix.md](../features/zefix.md)
- Curation workflow screens — [features/curation.md](../features/curation.md)
- FAIR score panel and PDF download — [features/fair-checking.md](../features/fair-checking.md)
- AI provider settings and assistant configuration — [features/ai-mcp.md](../features/ai-mcp.md)
- Sensor panels — [features/sensors.md](../features/sensors.md)

## Known debt

- Dark mode WCAG contrast pass not done — see [tech-debt.md](../tech-debt.md)
- E2E selector brittleness (copy/layout changes break tests)
- Route encoding edge cases with non-ASCII identifiers
