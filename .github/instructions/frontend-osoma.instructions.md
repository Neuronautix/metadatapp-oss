---
applyTo: osoma/**/*
---

# Frontend (Osoma React/Vite) Instructions

Read `AGENTS.md` first, then use this file as an `osoma/` supplement.

## Keep

- Feature code inside `osoma/src/features/`.
- App shell, providers, and routing in `osoma/src/app/`.
- Shared UI primitives in `osoma/src/components/ui/`.
- `@tanstack/react-query` plus `apiFetch()` from `osoma/src/lib/api.ts` for API access.
- Existing auth, mode, and mock wiring, including `AuthGuard` and the MSW toggle behavior.

## Avoid

- Reintroducing `osomapp` or `pwa` paths.
- Direct frontend calls to external Connected Apps.
- Duplicating repo-wide setup or command docs already covered by `AGENTS.md`.
