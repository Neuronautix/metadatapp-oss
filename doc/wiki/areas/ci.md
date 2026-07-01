---
  title: "Area: CI/CD"
  type: area
  updated: 2026-06-08
  source_prs: [55, 56, 57, 107, 134, 141, 213, 244, 284]
  related: [tech-debt.md]
  ---

  # Area: CI/CD

  ## Stack

  | Component           | Tool                   |
  |---------------------|------------------------|
  | CI platform         | GitHub Actions         |
  | Task runner         | Castor (wraps Docker Compose commands) |
  | Container builds    | Docker Buildx          |
  | PHP quality         | PHPUnit, PHPStan, PHP CS Fixer |
  | Frontend quality    | TypeScript check, Vite build, Vitest integration tests, ESLint |
  | E2E                | Playwright             |

  ## Recent enhancements

  - **Evolution Report Workflow Optimization (PR #284):**
    - Adjusted GitHub Actions to grant `pull-requests: write` permissions.
    - Evolution reports are routed through dedicated branches and pull requests, enhancing transparency and enabling proper reviews.
    - A fallback mechanism is included for cases where PR creation fails automatically.

  ## Current workflow order (as of PR #284)

  Optimized for fail-fast: cheap checks before expensive infrastructure.

  1. **Frontend checks** (DVC proxy types, Osoma build, integration tests) — most likely failure category, cheap.
  2. **Backend unit tests** (PHPUnit) — cheap, high signal.
  3. **Backend static checks** (PHPStan, PHP CS) — moderate cost.
  4. **Infrastructure startup** (Docker builds via `castor start`, services) — expensive.
  5. **E2E tests** (Playwright) — requires full stack.
  6. **Repository caching** — distributed with shared runners and dedicated caches for dependency reuse.
