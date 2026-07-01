# Auth Conception (Keycloak)

This document captures the current authentication design, its gaps, and the practical paths forward depending on whether this repo stays standalone or is merged into another repo that already runs Keycloak via Docker Compose.

## Current Design (as implemented)

Flow: SPA uses Authorization Code + PKCE against Keycloak, then stores tokens locally and adds `Authorization: Bearer <access_token>` to API calls.

Main pieces:
- `src/config/keycloak.ts`: Keycloak endpoints + client id
- `src/lib/auth.ts`: PKCE flow, token exchange, refresh, local storage
- `src/app/auth-context.tsx`: React auth state
- `src/features/auth/AuthCallbackPage.tsx`: `/auth/callback` handler
- `src/lib/api.ts`: injects `Authorization` header
- `src/app/layout/Topbar.tsx`: Login/Logout UI

Mock mode:
- Enabled by default unless `localStorage.getItem('use_msw') === 'false'`
- This bypasses real auth and provides a mock user
- Dev UI toggle exists in `src/components/dev/DevModeToggle.tsx`

## Known Gaps / Risks

Severity: Critical
- Mock mode is the default, so real Keycloak auth is bypassed unless manually disabled in localStorage.
- Token exchange uses `OIDC_SERVER_URL_INTERNAL` which is not reachable from a browser in most setups.

Severity: High
- No proactive token refresh or 401 refresh/retry logic (tokens expire during sessions).
- Tokens are stored in localStorage (XSS risk).

Severity: Medium
- Missing `nonce` parameter and no ID token validation.
- Base64url decoding of JWT payload is not handled robustly.
- Logout does not include `id_token_hint` or `client_id` (may fail in stricter Keycloak setups).
- No route protection (all routes are accessible regardless of auth state).

## Decision: Keep Repo Standalone vs Merge with Existing Keycloak Compose

### Option A: Keep this repo standalone
Best if:
- You want this UI to be independently deployable.
- You already have a stable Keycloak URL exposed (dev, pre-prod, prod).

What to do:
- Point `VITE_OIDC_SERVER_URL` to the Keycloak URL reachable from browsers.
- Remove or gate mock mode so it is never default in pre-prod/prod.
- Ensure token exchange happens against the browser-reachable Keycloak host.

Tradeoffs:
- You must configure Keycloak client and CORS per environment.
- You manage Keycloak separately.

### Option B: Merge with repo that already runs Keycloak
Best if:
- You want a single dev stack and one compose file.
- You plan to deploy the frontend together with the API + Keycloak.

What to do:
- Add this app to the existing compose, or serve the built assets from the existing app stack.
- Use a single Keycloak realm and client config that is owned by that repo.
- Set the frontend env vars from that repo’s config.

Tradeoffs:
- Less autonomy for this UI.
- Simpler local setup and fewer environment mismatches.

## Minimum Changes to Make Auth "Production-Ready"

1. Disable mock mode by default outside dev.
2. Use only browser-reachable Keycloak URLs for auth and token exchange.
3. Add refresh scheduling or 401 refresh/retry.
4. Add `nonce` + base64url decode for JWT, and basic ID token claims checks.
5. Implement route protection for sensitive pages.
6. Consider moving tokens to httpOnly cookies (requires a backend session or BFF).

## Concrete Implementation Plan: Account/Keycloak Organization Rights

This is the next implementation plan for moving from generic authentication to organization-aware access control.

### 1. Keep `Account` as the application organization primitive

Use the existing backend model as the source of truth for organization membership:

- `api/src/Entity/User.php` already links each user to one `Account`
- `api/src/Entity/Account.php` already represents the organization boundary
- `api/src/Doctrine/CurrentAccountExtension.php` already scopes account-aware resources

This avoids introducing a second organization concept next to `Account`.

### 2. Define the Keycloak contract around organization + organization role

The Keycloak token must carry enough information to reconstruct application access:

- one stable organization/account identifier
- one organization role

Target role set:

- `admin`
- `editor` (read + write)
- `viewer` (read only)

The demo realm configuration entry point is:

- `infrastructure/docker/services/keycloak/config/realm-demo.json`

The important design rule is that the frontend must not invent these rights locally; they must come from Keycloak claims and be enforced again by the backend.

### 3. Extend Symfony login hydration to resolve account membership

The current login path only hydrates identity fields. The next backend step should be to extend:

- `api/src/Security/Core/UserProvider.php`

Responsibilities of that login-time mapping:

- resolve the authenticated user from Keycloak `sub` / email
- read the organization/account identifier from token attributes
- resolve the matching `Account`
- attach the `User` to that `Account`
- translate the Keycloak organization role into application roles stored on `User`

Because `User` already stores roles and account membership, this is the cleanest place to centralize the mapping instead of scattering token parsing across controllers and voters.

### 4. Make account lookup stable and explicit

`Account` currently exposes name and relationships, but no dedicated external identity field for Keycloak mapping. The implementation should add a stable lookup key on:

- `api/src/Entity/Account.php`

That lookup key should be populated from fixtures/admin flows and then used by `UserProvider` during login.

This is safer than matching Keycloak organizations by display name.

### 5. Keep backend authorization authoritative

The backend should remain the source of truth for what a logged-in user can do:

- collection/item filtering continues through `api/src/Doctrine/CurrentAccountExtension.php`
- per-user restrictions continue through `api/src/Doctrine/CurrentUserExtension.php`
- resource writes continue to inherit account context through `api/src/State/Processor/SetAccountProcessor.php`

The role mapping should line up with the requested business model:

- organization admin → administrative actions inside the organization
- organization editor → read + write within the organization
- organization viewer → read only within the organization

### 6. Replace frontend-local RBAC with auth-derived rights

The current frontend role model is still local UI state:

- `osoma/src/app/role-context.tsx`
- `osoma/src/lib/rbac.ts`
- `osoma/src/app/layout/Topbar.tsx`

That should be replaced by organization rights derived from the authenticated session in:

- `osoma/src/lib/auth.ts`
- `osoma/src/app/auth-context.tsx`

The frontend session should expose at least:

- current account/organization identifier
- current organization role
- derived booleans such as `canRead`, `canWrite`, `canAdmin`

The dev-only role switch in `Topbar.tsx` can remain as a mock/testing affordance, but it must not be the real authorization source when Keycloak auth is enabled.

### 7. Apply organization rights to global UI structure first

Once the frontend session carries real organization rights, wire those rights into the global shell before touching deep feature logic:

- route gating in `osoma/src/app/router.tsx`
- navigation/menu visibility in `osoma/src/app/layout/Sidebar.tsx`
- global user display/state in `osoma/src/app/layout/Topbar.tsx`

Then apply the same rights model to page-level actions that are currently using local role helpers.

### 8. Keep organization rights separate from domain/species profile work

The requested organization-based access model should be treated as a separate axis from the earlier rodent/zebrafish profile concept:

- organization rights answer **who can see or edit**
- domain/profile capabilities answer **which product shape is active**

Both can eventually meet in frontend capability checks, but the implementation order should be:

1. Keycloak → Account → role mapping
2. backend enforcement of account + role rights
3. frontend consumption of authenticated organization rights
4. only then profile/domain capability layering

### 9. Recommended implementation order

1. Update Keycloak realm contract in `infrastructure/docker/services/keycloak/config/realm-demo.json`
2. Add stable account lookup support in `api/src/Entity/Account.php`
3. Extend `api/src/Security/Core/UserProvider.php` to map account + role on login
4. Verify existing backend account/user extensions still enforce the expected boundaries
5. Extend `osoma/src/lib/auth.ts` and `osoma/src/app/auth-context.tsx` to expose account + effective rights
6. Replace `osoma/src/app/role-context.tsx` as the source for real authorization decisions
7. Gate `osoma/src/app/router.tsx`, `osoma/src/app/layout/Sidebar.tsx`, and edit/admin actions from authenticated organization rights

This sequence gives a concrete path from Keycloak to backend enforcement to frontend behavior without introducing a parallel organization system.

## Pre-Prod Readiness (Beyond Auth)

### Build and Hosting
- Ensure `npm run build` produces static assets.
- Decide hosting: static hosting, Nginx, or serve via existing backend.
- Configure base paths and routing fallback for SPA (serve `index.html` on unknown paths).

### Environment Configuration
- Define a per-environment `.env` or deployment config.
- Set `VITE_API_URL`, `VITE_OIDC_*` for pre-prod domains.
- Ensure `import.meta.env.DEV` logic does not leak dev-only behavior.

### API Connectivity
- Confirm API endpoints are reachable from pre-prod network.
- Remove dev-only Vite proxy usage and set absolute API URL in pre-prod.
- Validate CORS and allowed origins in Keycloak and API backend.

### Security and Ops
- HTTPS everywhere.
- Content Security Policy if possible.
- Remove dev toggles and mock data in pre-prod.
- Add error monitoring (Sentry or equivalent).
- Add access logs and basic metrics.

### Release and QA
- CI pipeline for build + lint + tests (if any).
- Smoke tests for core routes and auth flow.
- Versioning and release notes.

### Observability
- Frontend error reporting.
- Backend request logs correlated with user id (Keycloak sub).

### Deployment Checklist Summary
- Build artifacts ready
- SPA fallback routing configured
- Env vars set for pre-prod domains
- HTTPS and CORS configured
- Monitoring enabled
- Mock mode disabled
- Auth flow validated end-to-end
