# Draft: Playwright Test Suite Generation from Test Plan

## User Request Summary
Generate comprehensive Playwright test suite covering ALL 39 test scenarios from `specs/dashboard-test-plan.md`.

## Requirements (confirmed)
- Generate working `.spec.ts` files for all 39 scenarios
- Follow existing test patterns (MSW disabled, proper structure)
- Handle Keycloak authentication properly
- Tests must pass or be marked with `.fixme()` if issues exist
- NO test deletion to make builds pass
- Full coverage as specified in test plan

## Test Scenarios (39 total)

### 1. Authentication and Access (5 scenarios)
1. 1.1 Successful Admin Login and Dashboard Access
2. 1.2 Successful User Login and Dashboard Access
3. 1.3 Failed Login - Invalid Credentials
4. 1.4 Failed Login - Empty Fields
5. 1.5 Unauthenticated Access Redirect

### 2. Dashboard Content and Layout (4 scenarios)
6. 2.1 Dashboard Initial Load - Admin User
7. 2.2 Dashboard Initial Load - Regular User
8. 2.3 Loading States
9. 2.4 Empty State Handling

### 3. Navigation and Interaction (4 scenarios)
10. 3.1 Dashboard Navigation Menu
11. 3.2 Dashboard Interactive Elements - Buttons
12. 3.3 Dashboard Interactive Elements - Forms
13. 3.4 Dashboard Interactive Elements - Tables/Lists

### 4. Error Handling and Edge Cases (5 scenarios)
14. 4.1 Network Error - API Unavailable
15. 4.2 Slow Network Conditions
16. 4.3 Session Expiration
17. 4.4 Browser Back Button
18. 4.5 Browser Refresh

### 5. Accessibility and Usability (4 scenarios)
19. 5.1 Keyboard Navigation
20. 5.2 Screen Reader Compatibility
21. 5.3 Responsive Design - Mobile
22. 5.4 Responsive Design - Tablet

### 6. Performance and Data Handling (2 scenarios)
23. 6.1 Large Dataset Handling
24. 6.2 Concurrent User Actions

### 7. Security (3 scenarios)
25. 7.1 Authorization - Admin vs User
26. 7.2 HTTPS and Secure Connection
27. 7.3 XSS Prevention

### 8. Integration Points (3 scenarios)
28. 8.1 Keycloak Integration - Login Flow
29. 8.2 Keycloak Integration - Logout Flow
30. 8.3 API Communication

**Total: 30 scenarios numbered above**

Wait - let me recount from the test plan to ensure accuracy.

## Test Accounts
- **Admin**: doctor.yiolyo@mdta.net / Pa55w0rd
- **User**: njuke.oaye@mdta.net / Pa55w0rd

## Technical Decisions
- **MSW Disabled**: Tests use live API, MSW disabled via localStorage
- **HTTPS**: Self-signed cert, ignoreHTTPSErrors: true in config
- **Timeout**: 45s test timeout, 10s expect timeout
- **Workers**: Sequential (workers: 1) for data isolation
- **Base URL**: https://localhost

## Existing Patterns Observed
```typescript
// beforeEach pattern
test.beforeEach(async ({ context, page }) => {
  await context.addInitScript(() => {
    localStorage.setItem("use_msw", "false");
  });
});

// Loading state handling
await expect(page.getByTestId("loading")).toBeVisible({ timeout: 15000 }).catch(() => {});
await page.waitForLoadState("networkidle");

// Selector preference: data-testid, then role-based
page.getByTestId("loading")
page.locator("h1, h2, h3, h4, h5, h6")
page.locator("main, [role='main'], #__next, body")
```

## Open Questions
1. Test file organization: One file per category vs functional grouping?
2. Authentication strategy: Global setup vs per-test login?
3. Parallelization approach for generation?
4. Test execution order for generation→run→fix cycle?

## Research Findings

### Application Structure Discovered

**Admin Page Architecture:**
- Entry: `/pwa/app/admin/page.tsx` → dynamically imports `Admin` component
- Main Component: `/pwa/components/admin/Admin.tsx`
  - Uses React-Admin with HydraAdmin/OpenApiAdmin
  - Authentication via NextAuth.js with Keycloak provider
  - Session loading shows `SyncLoader` spinner
  - Auto-redirects to Keycloak if no session or token expired
  
**Dashboard Component:**
- Location: `/pwa/components/admin/dashboard/Dashboard.tsx`
- Uses `@tanstack/react-query` for data fetching
- Shows: CircularProgress while loading, error message on failure
- Displays: User info header, stat boxes (Projects/Experiments/Procedures), project cards
- Interactive: "Show Details" buttons, procedure timeline with clickable items, modal dialogs

**Navigation Menu Items:**
- Dashboard (home)
- Projects (/projects)
- Experiments (/experiments)
- Procedures (/procedures)
- Cages (/cages)
- Subjects (/subjects)
- Connected Apps (/connected_apps)

**Available Test Selectors:**
- `data-testid="loading"` - Loading component
- `data-testid="pagination"` - Pagination component
- MUI components use standard role attributes (buttons, links, dialogs)
- React-Admin provides semantic structure

**Authentication Flow:**
1. User visits `/admin`
2. NextAuth checks session via `useSession()`
3. If no session → `signIn("keycloak")` redirects to Keycloak
4. Keycloak login page: `#username`, `#password`, submit button
5. After auth → redirect back to `/admin` with session
6. Session includes `accessToken`, `idToken` for API calls

**Logout Flow:**
1. Click user menu → Logout button
2. Calls `signOut()` with Keycloak logout URL
3. Redirects to Keycloak logout endpoint
4. Returns to origin after logout

### Keycloak Selectors (standard)
```typescript
// Username field
page.locator('#username')
// Password field  
page.locator('#password')
// Submit button
page.locator('#kc-login')
// OR
page.getByRole('button', { name: /sign in/i })
```

### Authentication Strategy Options
1. **Global Setup** - Login once, save storageState, reuse across tests
2. **Per-Test Login** - Each test logs in fresh (slow but isolated)
3. **Fixture-based** - Create auth fixtures for different user types
4. **Hybrid** - Global setup for most tests, per-test for auth-specific tests

## Scope Boundaries
- INCLUDE: All 39 scenarios from test plan
- INCLUDE: Proper Keycloak login flow handling
- INCLUDE: Test verification and fixing
- EXCLUDE: New feature testing beyond plan scope
- EXCLUDE: Performance benchmarking beyond plan scope
