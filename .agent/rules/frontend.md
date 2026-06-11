---
activation: glob
glob_pattern: "osoma/**/*.{ts,tsx,js,jsx}"
description: Frontend development rules for Osoma
---

# Frontend Development Rules

**Activation:** Glob pattern `osoma/**/*.{ts,tsx,js,jsx}`

Read `AGENTS.md` first.
These rules apply only when working inside the active frontend at `osoma/`.

## Structure

- Keep feature-specific screens, hooks, tests, and API bindings inside `osoma/src/features/`.
- Keep app bootstrapping, providers, and routing in `osoma/src/app/`.
- Keep shared UI primitives in `osoma/src/components/ui/`.
- Keep reusable API, auth, and mode helpers in `osoma/src/lib/` and `osoma/src/domain/` where the existing code already does so.

## Data and Auth

- Prefer `@tanstack/react-query` for data fetching and mutations.
- Reuse helpers from `osoma/src/lib/api.ts` instead of introducing ad hoc fetch wrappers.
- Preserve existing auth boundaries such as `AuthGuard` and the app-level providers.
- Use Zod-based validation where the surrounding feature already models forms or contracts with Zod.

## Modes and Testability

- Keep development-mode behavior compatible with the existing MSW toggle in `osoma/src/lib/mode.ts`.
- If you touch browser tests or flows consumed by `e2e/`, remember that E2E disables mocks via `localStorage.setItem('use_msw', 'false')`.

## Boundaries

- Do not add direct frontend calls to external Connected Apps.
- Do not invent backend truth in client state or UI-only models.
- If you need repo-wide command or ownership guidance, go back to `AGENTS.md`.
