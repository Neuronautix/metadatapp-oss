# Playwright Test Suite Generation from Dashboard Test Plan

## TL;DR

> **Quick Summary**: Generate comprehensive Playwright E2E test suite covering all 39 scenarios from `specs/dashboard-test-plan.md`, with proper Keycloak authentication infrastructure using Playwright's global setup pattern.
> 
> **Deliverables**:
> - `global-setup.ts` - Authenticates admin & user, saves storageState
> - `storageState/admin.json` and `storageState/user.json` - Reusable auth states
> - Updated `playwright.config.ts` - Projects using storageState
> - 8 test files covering all 39 scenarios
> - All tests passing (or marked `.fixme()` with documented reasoning)
> 
> **Estimated Effort**: Large (39 scenarios + infrastructure)
> **Parallel Execution**: YES - 3 waves after Wave 1 completes
> **Critical Path**: Task 1 → Task 2 → Task 3 → Waves 2/3/4 (parallel) → Task 12

---

## Context

### Original Request
User command: `ultrawork specs/dashboard-test-plan.md`
Goal: Generate actual, working Playwright test code (.spec.ts files) from the 39 test scenarios documented in the test plan.

### Interview Summary
**Key Discussions**:
- Authentication is CRITICAL: All dashboard tests require Keycloak login
- MSW must be disabled for E2E via `localStorage.setItem("use_msw", "false")`
- Test accounts: Admin (doctor.yiolyo@mdta.net) and User (njuke.oaye@mdta.net), both with password Pa55w0rd
- Auth strategy: Playwright global setup with storageState (recommended and accepted)
- Test organization: 8 files by category (auth, content, navigation, errors, accessibility, performance, security, integration)

**Research Findings**:
- Keycloak login form selectors: `#username`, `#password`, `#kc-login` (or button "Sign In")
- NextAuth manages session with httpOnly cookies - storageState captures these
- Dashboard component uses React-Query, shows CircularProgress while loading
- Navigation menu has 7 items: Dashboard, Projects, Experiments, Procedures, Cages, Subjects, Connected Apps
- Available test selectors: `data-testid="loading"`, `data-testid="pagination"`, MUI role-based selectors

### Metis Review
**Identified Gaps** (addressed):
1. **Auth infrastructure missing** → Task 1-2 create global-setup.ts and update playwright.config.ts
2. **No shared MSW disable pattern** → Move to global setup and test fixtures
3. **Credentials security** → Document use of .env for local, CI secrets for pipeline
4. **Test data seeding unclear** → Note: Tests use existing data; seeding out of scope
5. **Edge cases for Keycloak (2FA, consent)** → Assumption: test accounts have no 2FA/consent configured
6. **Session expiration handling** → globalSetup runs fresh each test run
7. **Machine-checkable acceptance criteria** → Added explicit assertions to each task

---

## Work Objectives

### Core Objective
Generate a complete, passing Playwright test suite covering all 39 scenarios from the dashboard test plan, with reusable authentication infrastructure.

### Concrete Deliverables
1. `/home/taenia/Code/metadatapp/e2e/global-setup.ts` - Global setup for authentication
2. `/home/taenia/Code/metadatapp/e2e/storageState/` directory with `admin.json` and `user.json`
3. `/home/taenia/Code/metadatapp/e2e/playwright.config.ts` - Updated with globalSetup and projects
4. `/home/taenia/Code/metadatapp/e2e/tests/auth.spec.ts` - Authentication scenarios (5)
5. `/home/taenia/Code/metadatapp/e2e/tests/dashboard-content.spec.ts` - Content/layout scenarios (4)
6. `/home/taenia/Code/metadatapp/e2e/tests/navigation.spec.ts` - Navigation scenarios (4)
7. `/home/taenia/Code/metadatapp/e2e/tests/error-handling.spec.ts` - Error scenarios (5)
8. `/home/taenia/Code/metadatapp/e2e/tests/accessibility.spec.ts` - Accessibility scenarios (4)
9. `/home/taenia/Code/metadatapp/e2e/tests/performance.spec.ts` - Performance scenarios (2)
10. `/home/taenia/Code/metadatapp/e2e/tests/security.spec.ts` - Security scenarios (3)
11. `/home/taenia/Code/metadatapp/e2e/tests/integration.spec.ts` - Integration scenarios (3)
12. Clean up: Remove/refactor existing `tests/dashboard.spec.ts` after migration

### Definition of Done
- [ ] `npx playwright test --project=chromium-admin` → All authenticated tests pass
- [ ] `npx playwright test --project=chromium-unauthenticated` → Auth tests pass
- [ ] All 39 scenarios have corresponding test implementations
- [ ] No tests marked `.skip()` without documented reasoning
- [ ] Tests run successfully in local Docker Compose environment

### Must Have
- Global setup that performs Keycloak login and saves storageState
- Separate storageState files for admin and user roles
- MSW disabled consistently across all tests
- All 39 scenarios from test plan implemented
- Explicit, machine-verifiable assertions for each test
- Tests follow existing patterns (ignoreHTTPSErrors, proper timeouts)

### Must NOT Have (Guardrails)
- **NO hardcoded credentials in committed code** - Use process.env or .env files (gitignored)
- **NO skipping tests to make builds pass** - Use `.fixme()` with documented reason if unfixable
- **NO implementing features not in test plan** - Scope is exactly 39 scenarios
- **NO modifying application code** - E2E tests only, no PWA changes
- **NO text-only selectors for critical paths** - Prefer data-testid or role-based
- **NO manual verification acceptance criteria** - All assertions must be automated

---

## Verification Strategy (MANDATORY)

### Test Decision
- **Infrastructure exists**: YES (Playwright configured)
- **User wants tests**: YES - the deliverable IS tests
- **Framework**: Playwright Test (already configured)

### Verification Approach
Since we're generating tests, verification means:
1. Tests compile without TypeScript errors
2. Tests execute against running services
3. Tests pass or have documented `.fixme()` reasons

Each task includes automated verification via:
- `npx playwright test [file]` - Execute tests
- Assertion results in Playwright report
- Screenshots on failure (already configured)

---

## Task Dependency Graph

| Task | Depends On | Reason |
|------|------------|--------|
| Task 1: Global Setup Infrastructure | None | Foundation - must exist before any authenticated tests |
| Task 2: Update playwright.config.ts | Task 1 | Config references global-setup.ts and storageState paths |
| Task 3: Auth Test File | Task 2 | Auth tests validate the login flow that global-setup uses |
| Task 4: Dashboard Content Tests | Task 2 | Requires storageState for authenticated access |
| Task 5: Navigation Tests | Task 2 | Requires storageState for authenticated access |
| Task 6: Error Handling Tests | Task 2 | Requires storageState for most scenarios |
| Task 7: Accessibility Tests | Task 2 | Requires storageState for authenticated access |
| Task 8: Performance Tests | Task 2 | Requires storageState for authenticated access |
| Task 9: Security Tests | Task 2 | Requires both admin and user storageState |
| Task 10: Integration Tests | Task 2 | Tests auth integration flows |
| Task 11: Run Full Suite & Heal | Tasks 3-10 | Can only heal after all tests exist |
| Task 12: Cleanup & Documentation | Task 11 | Final cleanup after verification |

---

## Parallel Execution Graph

```
Wave 1 (Sequential - Foundation):
└── Task 1: Global Setup Infrastructure (no dependencies)
    └── Task 2: Update playwright.config.ts (depends: 1)
        └── Task 3: Auth Test File (depends: 2) - validates auth works

Wave 2 (Parallel - Core Tests, after Wave 1):
├── Task 4: Dashboard Content Tests (depends: 2)
├── Task 5: Navigation Tests (depends: 2)
└── Task 6: Error Handling Tests (depends: 2)

Wave 3 (Parallel - Additional Tests, after Wave 1):
├── Task 7: Accessibility Tests (depends: 2)
├── Task 8: Performance Tests (depends: 2)
└── Task 9: Security Tests (depends: 2)

Wave 4 (After Wave 2 & 3):
└── Task 10: Integration Tests (depends: 2)

Wave 5 (Sequential - Finalization):
└── Task 11: Run Full Suite & Heal (depends: 3-10)
    └── Task 12: Cleanup & Documentation (depends: 11)

Critical Path: Task 1 → Task 2 → Task 3 → Task 11 → Task 12
Parallel Speedup: ~60% faster than sequential (Waves 2-4 run in parallel)
```

---

## Agent Dispatch Summary

| Wave | Tasks | Recommended Dispatch |
|------|-------|---------------------|
| 1 | 1, 2, 3 | Sequential: category="quick" for 1-2, then category="unspecified-low" with playwright skill for 3 |
| 2 | 4, 5, 6 | Parallel: 3x category="unspecified-low" with playwright skill, run_in_background=true |
| 3 | 7, 8, 9 | Parallel: 3x category="unspecified-low" with playwright skill, run_in_background=true |
| 4 | 10 | Sequential: category="unspecified-low" with playwright skill |
| 5 | 11, 12 | Sequential: playwright-test-healer for 11, then category="quick" for 12 |

---

## TODOs

### Task 1: Create Global Setup Infrastructure

**What to do**:
1. Create `/home/taenia/Code/metadatapp/e2e/global-setup.ts`:
   - Export async function that launches Chromium browser
   - Navigate to `https://localhost/admin` (triggers Keycloak redirect)
   - Wait for Keycloak login page (`heading "Sign in to your account"`)
   - Fill `#username` with admin credentials (from process.env.E2E_ADMIN_USER or fallback)
   - Fill `#password` with admin password (from process.env.E2E_ADMIN_PASS or fallback)
   - Click `#kc-login` or button with name "Sign In"
   - Wait for redirect to `/admin` and page to load
   - Save storageState to `storageState/admin.json`
   - Repeat for user account → `storageState/user.json`
   - Close browser
2. Create `/home/taenia/Code/metadatapp/e2e/storageState/` directory
3. Create `/home/taenia/Code/metadatapp/e2e/.gitignore` entry for `storageState/*.json`
4. Add MSW disable script in global setup context

**Must NOT do**:
- Commit actual credentials in the file (use process.env with documented fallbacks for local dev)
- Use text-only selectors that might break with Keycloak theme changes
- Skip error handling for login failures

**Recommended Agent Profile**:
- **Category**: `quick`
  - Reason: Infrastructure file creation with known patterns; no complex logic
- **Skills**: [`typescript-programmer`]
  - `typescript-programmer`: Need clean TypeScript for Playwright global setup

**Skills Evaluated but Omitted**:
- `playwright`: Not needed for file creation; skill better for interactive browser testing
- `git-master`: No git operations in this task

**Parallelization**:
- **Can Run In Parallel**: NO
- **Parallel Group**: Wave 1 (sequential with Task 2)
- **Blocks**: Task 2
- **Blocked By**: None (can start immediately)

**References** (CRITICAL - Be Exhaustive):

**Pattern References** (existing code to follow):
- `tests/dashboard.spec.ts:4-8` - MSW disable pattern via `context.addInitScript`
- `playwright.config.ts:34-39` - Base URL and ignoreHTTPSErrors settings

**API/Type References** (contracts to implement against):
- Playwright globalSetup signature: `async function globalSetup(config: FullConfig)`
- storageState API: `context.storageState({ path: 'path/to/file.json' })`

**Documentation References** (specs and requirements):
- `specs/dashboard-test-plan.md:13-14` - Test account credentials
- `specs/dashboard-test-plan.md:23-31` - Keycloak login flow expected behavior

**External References** (libraries and frameworks):
- Playwright globalSetup: https://playwright.dev/docs/auth#basic-shared-account-in-all-tests
- Playwright storageState: https://playwright.dev/docs/api/class-browsercontext#browser-context-storage-state

**WHY Each Reference Matters**:
- `dashboard.spec.ts` MSW pattern ensures new tests follow established convention
- Playwright globalSetup docs show exact signature and usage pattern
- Test plan credentials section provides the actual login values to use

**Acceptance Criteria**:

**Automated Verification:**
```bash
# File exists and has correct structure
test -f /home/taenia/Code/metadatapp/e2e/global-setup.ts && echo "PASS: global-setup.ts exists"

# TypeScript compiles
cd /home/taenia/Code/metadatapp/e2e && npx tsc --noEmit global-setup.ts 2>&1 | grep -q "error" && echo "FAIL: TypeScript errors" || echo "PASS: TypeScript compiles"

# Directory exists
test -d /home/taenia/Code/metadatapp/e2e/storageState && echo "PASS: storageState directory exists"
```

**Commit**: YES
- Message: `test(e2e): add global setup for Keycloak authentication`
- Files: `global-setup.ts`, `storageState/.gitkeep`, `.gitignore` (if modified)
- Pre-commit: `npx tsc --noEmit`

---

### Task 2: Update playwright.config.ts with Auth Projects

**What to do**:
1. Edit `/home/taenia/Code/metadatapp/e2e/playwright.config.ts`:
   - Add `globalSetup: require.resolve('./global-setup')`
   - Create three projects:
     - `chromium-unauthenticated`: No storageState (for auth tests that test login itself)
     - `chromium-admin`: Uses `storageState: 'storageState/admin.json'`
     - `chromium-user`: Uses `storageState: 'storageState/user.json'`
   - Ensure MSW is disabled via storageState or project-level init script
   - Add dependency: admin/user projects depend on globalSetup completing

**Must NOT do**:
- Break existing configuration
- Remove ignoreHTTPSErrors or other necessary settings
- Create circular dependencies between projects

**Recommended Agent Profile**:
- **Category**: `quick`
  - Reason: Configuration file edit with known Playwright patterns
- **Skills**: [`typescript-programmer`]
  - `typescript-programmer`: Config uses TypeScript with defineConfig

**Skills Evaluated but Omitted**:
- `playwright`: Not interactive browser work
- `git-master`: No git operations

**Parallelization**:
- **Can Run In Parallel**: NO
- **Parallel Group**: Wave 1 (sequential)
- **Blocks**: Tasks 3-10 (all test files)
- **Blocked By**: Task 1

**References** (CRITICAL - Be Exhaustive):

**Pattern References** (existing code to follow):
- `playwright.config.ts:1-68` - Current full config structure to preserve

**API/Type References** (contracts to implement against):
- Playwright defineConfig: https://playwright.dev/docs/api/class-testconfig
- Project configuration: https://playwright.dev/docs/test-projects

**External References**:
- Playwright auth docs: https://playwright.dev/docs/auth#basic-shared-account-in-all-tests
- Multiple roles example: https://playwright.dev/docs/auth#multiple-signed-in-roles

**WHY Each Reference Matters**:
- Current config must be preserved and extended, not replaced
- Playwright project configuration determines how tests are run with different auth states

**Acceptance Criteria**:

**Automated Verification:**
```bash
# Config is valid TypeScript
cd /home/taenia/Code/metadatapp/e2e && npx tsc --noEmit playwright.config.ts 2>&1 | grep -q "error" && echo "FAIL" || echo "PASS: Config compiles"

# Config has globalSetup
grep -q "globalSetup" playwright.config.ts && echo "PASS: globalSetup configured"

# Config has projects
grep -q "chromium-admin" playwright.config.ts && echo "PASS: Admin project exists"
grep -q "chromium-user" playwright.config.ts && echo "PASS: User project exists"
```

**Commit**: YES
- Message: `test(e2e): configure playwright projects for authenticated testing`
- Files: `playwright.config.ts`
- Pre-commit: `npx tsc --noEmit playwright.config.ts`

---

### Task 3: Generate Authentication Test File (5 scenarios)

**What to do**:
Generate `/home/taenia/Code/metadatapp/e2e/tests/auth.spec.ts` covering:

1. **1.1 Successful Admin Login and Dashboard Access**
   - Start without auth (unauthenticated project)
   - Navigate to `/admin`
   - Verify redirect to Keycloak
   - Fill credentials, submit
   - Verify redirect to dashboard with content visible

2. **1.2 Successful User Login and Dashboard Access**
   - Same flow with user credentials
   - Verify appropriate access (may differ from admin)

3. **1.3 Failed Login - Invalid Credentials**
   - Navigate to `/admin` (triggers Keycloak)
   - Enter invalid credentials
   - Verify error message shown
   - Verify remains on login page

4. **1.4 Failed Login - Empty Fields**
   - Navigate to `/admin`
   - Click Sign In with empty fields
   - Verify validation error or blocked submission

5. **1.5 Unauthenticated Access Redirect**
   - Navigate directly to `/admin` without session
   - Verify immediate redirect to Keycloak
   - Verify no dashboard content briefly visible

**Must NOT do**:
- Use storageState for these tests (they test the login flow itself)
- Hardcode credentials (use constants at file top or env vars)
- Skip proper error handling assertions

**Recommended Agent Profile**:
- **Category**: `unspecified-low`
  - Reason: Test generation requires understanding auth flow and proper assertions
- **Skills**: [`playwright`]
  - `playwright`: REQUIRED - need browser automation expertise for auth flow testing

**Skills Evaluated but Omitted**:
- `typescript-programmer`: Playwright skill already covers TypeScript test writing
- `frontend-ui-ux`: Not designing UI, just testing it

**Parallelization**:
- **Can Run In Parallel**: NO (must validate before other tests)
- **Parallel Group**: Wave 1 (end of sequential chain)
- **Blocks**: Task 11
- **Blocked By**: Task 2

**References** (CRITICAL - Be Exhaustive):

**Pattern References** (existing code to follow):
- `tests/dashboard.spec.ts:1-34` - Test file structure, imports, describe blocks
- `tests/dashboard.spec.ts:4-8` - beforeEach with MSW disable

**API/Type References** (contracts to implement against):
- Keycloak selectors: `#username`, `#password`, `#kc-login`
- Keycloak error: Look for `.alert-error` or error text

**Documentation References** (specs and requirements):
- `specs/dashboard-test-plan.md:19-132` - Full auth scenario specifications
- `specs/dashboard-test-plan.md:13-14` - Test credentials

**WHY Each Reference Matters**:
- dashboard.spec.ts shows correct test structure to follow
- Test plan sections give exact steps and expected outcomes to implement
- Keycloak selectors ensure reliable form interaction

**Acceptance Criteria**:

**Automated Verification:**
```bash
# File exists and compiles
test -f tests/auth.spec.ts && echo "PASS: File exists"
npx tsc --noEmit tests/auth.spec.ts 2>&1 | grep -q "error" && echo "FAIL" || echo "PASS: Compiles"

# Has all 5 test cases
grep -c "test(" tests/auth.spec.ts | xargs -I{} test {} -ge 5 && echo "PASS: Has 5+ tests"

# Run tests (after services running)
npx playwright test tests/auth.spec.ts --project=chromium-unauthenticated 2>&1 | tee test-output.txt
grep -q "passed" test-output.txt && echo "PASS: Some tests passed"
```

**Evidence to Capture:**
- [ ] Playwright test report showing pass/fail per scenario
- [ ] Screenshots of any failures in test-results/

**Commit**: YES
- Message: `test(e2e): add authentication test scenarios (1.1-1.5)`
- Files: `tests/auth.spec.ts`
- Pre-commit: `npx playwright test tests/auth.spec.ts --project=chromium-unauthenticated`

---

### Task 4: Generate Dashboard Content Test File (4 scenarios)

**What to do**:
Generate `/home/taenia/Code/metadatapp/e2e/tests/dashboard-content.spec.ts` covering:

1. **2.1 Dashboard Initial Load - Admin User**
   - Use admin storageState project
   - Navigate to `/admin`
   - Verify loading indicator appears then disappears
   - Verify all sections visible: navigation, main content, headings
   - No console errors

2. **2.2 Dashboard Initial Load - Regular User**
   - Use user storageState project
   - Navigate to `/admin`
   - Verify loads successfully OR shows access denied
   - Document actual behavior observed

3. **2.3 Loading States**
   - Navigate to `/admin`
   - Observe `data-testid="loading"` visibility
   - Verify smooth transition to loaded state
   - No content flash

4. **2.4 Empty State Handling**
   - If applicable, test empty data scenarios
   - Verify helpful messages shown
   - UI remains functional

**Must NOT do**:
- Skip loading state verification
- Use text-only assertions for critical content
- Ignore console errors

**Recommended Agent Profile**:
- **Category**: `unspecified-low`
  - Reason: Standard test generation with content verification
- **Skills**: [`playwright`]
  - `playwright`: Browser automation for content testing

**Skills Evaluated but Omitted**:
- `frontend-ui-ux`: Testing, not designing

**Parallelization**:
- **Can Run In Parallel**: YES
- **Parallel Group**: Wave 2 (with Tasks 5, 6)
- **Blocks**: Task 11
- **Blocked By**: Task 2

**References** (CRITICAL - Be Exhaustive):

**Pattern References** (existing code to follow):
- `tests/dashboard.spec.ts:10-21` - Page load and heading verification pattern
- `tests/dashboard.spec.ts:15` - Loading state handling with catch

**Documentation References** (specs and requirements):
- `specs/dashboard-test-plan.md:135-229` - Full content/layout specifications

**WHY Each Reference Matters**:
- Existing dashboard test shows exact patterns for loading verification
- Test plan provides detailed expected outcomes for each scenario

**Acceptance Criteria**:

**Automated Verification (via playwright skill):**
```
1. Navigate to: https://localhost/admin (with admin storageState)
2. Wait for: selector [data-testid="loading"] to be hidden (or timeout)
3. Assert: At least one heading (h1-h6) is visible
4. Assert: Main content area is visible
5. Screenshot: .sisyphus/evidence/task-4-dashboard-content.png
```

**Commit**: YES
- Message: `test(e2e): add dashboard content tests (2.1-2.4)`
- Files: `tests/dashboard-content.spec.ts`
- Pre-commit: `npx playwright test tests/dashboard-content.spec.ts --project=chromium-admin`

---

### Task 5: Generate Navigation Test File (4 scenarios)

**What to do**:
Generate `/home/taenia/Code/metadatapp/e2e/tests/navigation.spec.ts` covering:

1. **3.1 Dashboard Navigation Menu**
   - Identify all nav items (Dashboard, Projects, Experiments, etc.)
   - Click each item
   - Verify navigation to correct URL
   - Verify active state indication
   - Test keyboard Tab navigation

2. **3.2 Dashboard Interactive Elements - Buttons**
   - Find all buttons
   - Test hover states
   - Click and verify actions
   - Check for loading states on async operations

3. **3.3 Dashboard Interactive Elements - Forms**
   - Locate any forms
   - Fill with valid data
   - Submit and verify success
   - Test validation

4. **3.4 Dashboard Interactive Elements - Tables/Lists**
   - Locate data tables
   - Test sorting (click headers)
   - Test pagination if present
   - Test filtering/search if present

**Must NOT do**:
- Test navigation to external links
- Modify actual data without cleanup

**Recommended Agent Profile**:
- **Category**: `unspecified-low`
  - Reason: Interactive element testing requires careful selector work
- **Skills**: [`playwright`]
  - `playwright`: Interactive element testing

**Skills Evaluated but Omitted**:
- `frontend-ui-ux`: Testing behavior, not design

**Parallelization**:
- **Can Run In Parallel**: YES
- **Parallel Group**: Wave 2 (with Tasks 4, 6)
- **Blocks**: Task 11
- **Blocked By**: Task 2

**References** (CRITICAL - Be Exhaustive):

**Pattern References**:
- `.sisyphus/drafts/playwright-test-generation.md:126-129` - Known navigation menu items

**Documentation References**:
- `specs/dashboard-test-plan.md:232-326` - Navigation scenario specifications

**WHY Each Reference Matters**:
- Draft notes list actual navigation items discovered in codebase exploration
- Test plan gives detailed interaction steps

**Acceptance Criteria**:

**Automated Verification (via playwright skill):**
```
1. Navigate to: https://localhost/admin
2. Find: navigation menu
3. Click: each nav item
4. Assert: URL changes to expected path
5. Assert: Content updates accordingly
```

**Commit**: YES
- Message: `test(e2e): add navigation tests (3.1-3.4)`
- Files: `tests/navigation.spec.ts`
- Pre-commit: `npx playwright test tests/navigation.spec.ts --project=chromium-admin`

---

### Task 6: Generate Error Handling Test File (5 scenarios)

**What to do**:
Generate `/home/taenia/Code/metadatapp/e2e/tests/error-handling.spec.ts` covering:

1. **4.1 Network Error - API Unavailable**
   - Use route interception to simulate API failure
   - Verify graceful error message
   - No infinite loading

2. **4.2 Slow Network Conditions**
   - Use Playwright network throttling
   - Verify loading indicators work
   - UI remains responsive

3. **4.3 Session Expiration**
   - Clear cookies mid-session
   - Attempt action
   - Verify redirect to login

4. **4.4 Browser Back Button**
   - Navigate to sub-page
   - Press back
   - Verify return to previous state

5. **4.5 Browser Refresh**
   - Navigate and interact
   - Refresh page
   - Verify recovery to stable state

**Must NOT do**:
- Actually stop Docker services (use route mocking instead)
- Leave session in invalid state for subsequent tests

**Recommended Agent Profile**:
- **Category**: `unspecified-low`
  - Reason: Error simulation requires Playwright route/network APIs
- **Skills**: [`playwright`]
  - `playwright`: Route interception and network simulation

**Skills Evaluated but Omitted**:
- `typescript-programmer`: Playwright skill covers needed patterns

**Parallelization**:
- **Can Run In Parallel**: YES
- **Parallel Group**: Wave 2 (with Tasks 4, 5)
- **Blocks**: Task 11
- **Blocked By**: Task 2

**References** (CRITICAL - Be Exhaustive):

**External References**:
- Playwright route interception: https://playwright.dev/docs/network#handle-requests
- Network throttling: https://playwright.dev/docs/api/class-browsercontext#browser-context-route

**Documentation References**:
- `specs/dashboard-test-plan.md:329-439` - Error handling specifications

**WHY Each Reference Matters**:
- Playwright network APIs are key to simulating error conditions
- Test plan details exact expected behaviors

**Acceptance Criteria**:

**Automated Verification:**
```bash
npx playwright test tests/error-handling.spec.ts --project=chromium-admin
# Assert: All 5 scenarios execute
# Assert: Error states handled gracefully (no unhandled exceptions)
```

**Commit**: YES
- Message: `test(e2e): add error handling tests (4.1-4.5)`
- Files: `tests/error-handling.spec.ts`
- Pre-commit: `npx playwright test tests/error-handling.spec.ts --project=chromium-admin`

---

### Task 7: Generate Accessibility Test File (4 scenarios)

**What to do**:
Generate `/home/taenia/Code/metadatapp/e2e/tests/accessibility.spec.ts` covering:

1. **5.1 Keyboard Navigation**
   - Tab through all interactive elements
   - Verify focus indicators visible
   - Verify logical tab order
   - Activate with Enter/Space

2. **5.2 Screen Reader Compatibility**
   - Check ARIA labels exist
   - Verify heading hierarchy
   - Check form labels
   - (Note: Full SR testing is manual, automated checks for attributes)

3. **5.3 Responsive Design - Mobile**
   - Set viewport to 375x667
   - Verify layout adapts
   - No horizontal scroll
   - Touch targets appropriately sized

4. **5.4 Responsive Design - Tablet**
   - Set viewport to 768x1024
   - Test portrait and landscape
   - Verify layout adapts

**Must NOT do**:
- Require manual screen reader testing
- Skip automated a11y attribute checks

**Recommended Agent Profile**:
- **Category**: `unspecified-low`
  - Reason: Accessibility testing with viewport changes
- **Skills**: [`playwright`]
  - `playwright`: Viewport manipulation and a11y testing

**Skills Evaluated but Omitted**:
- `frontend-ui-ux`: Testing a11y, not designing

**Parallelization**:
- **Can Run In Parallel**: YES
- **Parallel Group**: Wave 3 (with Tasks 8, 9)
- **Blocks**: Task 11
- **Blocked By**: Task 2

**References** (CRITICAL - Be Exhaustive):

**External References**:
- Playwright viewport: https://playwright.dev/docs/emulation#viewport
- axe-playwright for automated a11y: https://github.com/abhinaba-ghosh/axe-playwright

**Documentation References**:
- `specs/dashboard-test-plan.md:443-536` - Accessibility specifications

**Acceptance Criteria**:

**Automated Verification:**
```bash
npx playwright test tests/accessibility.spec.ts --project=chromium-admin
# Verify keyboard focus works
# Verify responsive viewports render correctly
```

**Commit**: YES
- Message: `test(e2e): add accessibility tests (5.1-5.4)`
- Files: `tests/accessibility.spec.ts`
- Pre-commit: `npx playwright test tests/accessibility.spec.ts --project=chromium-admin`

---

### Task 8: Generate Performance Test File (2 scenarios)

**What to do**:
Generate `/home/taenia/Code/metadatapp/e2e/tests/performance.spec.ts` covering:

1. **6.1 Large Dataset Handling**
   - Navigate to dashboard/list with many items
   - Measure load time (< 5 seconds expected)
   - Test scroll performance
   - Test sorting/filtering responsiveness

2. **6.2 Concurrent User Actions**
   - Open dashboard in two contexts
   - Perform action in context 1
   - Verify context 2 handles appropriately
   - No data corruption

**Must NOT do**:
- Run heavy performance benchmarks in every CI run
- Create actual large datasets (test with existing data or mark as `.skip` if no data)

**Recommended Agent Profile**:
- **Category**: `unspecified-low`
  - Reason: Performance measurement requires timing APIs
- **Skills**: [`playwright`]
  - `playwright`: Multiple contexts and timing

**Skills Evaluated but Omitted**:
- `data-scientist`: Not data analysis, just performance testing

**Parallelization**:
- **Can Run In Parallel**: YES
- **Parallel Group**: Wave 3 (with Tasks 7, 9)
- **Blocks**: Task 11
- **Blocked By**: Task 2

**References** (CRITICAL - Be Exhaustive):

**Documentation References**:
- `specs/dashboard-test-plan.md:539-585` - Performance specifications

**External References**:
- Playwright performance timing: https://playwright.dev/docs/api/class-page#page-metrics

**Acceptance Criteria**:

**Automated Verification:**
```bash
npx playwright test tests/performance.spec.ts --project=chromium-admin
# Assert: Page load < 5000ms
# Assert: No performance warnings
```

**Commit**: YES
- Message: `test(e2e): add performance tests (6.1-6.2)`
- Files: `tests/performance.spec.ts`
- Pre-commit: `npx playwright test tests/performance.spec.ts --project=chromium-admin`

---

### Task 9: Generate Security Test File (3 scenarios)

**What to do**:
Generate `/home/taenia/Code/metadatapp/e2e/tests/security.spec.ts` covering:

1. **7.1 Authorization - Admin vs User**
   - Login as regular user
   - Attempt admin-only actions
   - Verify denied/hidden
   - Login as admin
   - Verify full access

2. **7.2 HTTPS and Secure Connection**
   - Verify page loads via HTTPS
   - Check no mixed content
   - All resources over HTTPS

3. **7.3 XSS Prevention**
   - Find input field
   - Enter XSS payload: `<script>alert('XSS')</script>`
   - Submit
   - Verify script doesn't execute
   - Content escaped/sanitized

**Must NOT do**:
- Actually exploit security vulnerabilities
- Leave malicious data in database

**Recommended Agent Profile**:
- **Category**: `unspecified-low`
  - Reason: Security testing requires careful payload handling
- **Skills**: [`playwright`]
  - `playwright`: Form submission and response verification

**Skills Evaluated but Omitted**:
- `typescript-programmer`: Playwright covers needed patterns

**Parallelization**:
- **Can Run In Parallel**: YES
- **Parallel Group**: Wave 3 (with Tasks 7, 8)
- **Blocks**: Task 11
- **Blocked By**: Task 2

**References** (CRITICAL - Be Exhaustive):

**Documentation References**:
- `specs/dashboard-test-plan.md:588-656` - Security specifications

**Acceptance Criteria**:

**Automated Verification:**
```bash
npx playwright test tests/security.spec.ts
# Requires both admin and user projects
# Assert: Role-based access works
# Assert: No XSS execution
```

**Commit**: YES
- Message: `test(e2e): add security tests (7.1-7.3)`
- Files: `tests/security.spec.ts`
- Pre-commit: `npx playwright test tests/security.spec.ts`

---

### Task 10: Generate Integration Test File (3 scenarios)

**What to do**:
Generate `/home/taenia/Code/metadatapp/e2e/tests/integration.spec.ts` covering:

1. **8.1 Keycloak Integration - Login Flow**
   - Test full redirect → login → callback flow
   - Verify session established
   - Verify return to original destination

2. **8.2 Keycloak Integration - Logout Flow**
   - Start authenticated
   - Click logout
   - Verify Keycloak logout called
   - Verify session invalidated
   - Cannot access protected pages

3. **8.3 API Communication**
   - Open DevTools Network (via Playwright route inspection)
   - Observe API calls
   - Verify correct endpoints
   - Verify auth headers included
   - Verify response handling

**Must NOT do**:
- Duplicate auth tests already in auth.spec.ts
- Mock API responses (we're testing real integration)

**Recommended Agent Profile**:
- **Category**: `unspecified-low`
  - Reason: Integration testing with network inspection
- **Skills**: [`playwright`]
  - `playwright`: Route inspection and auth flow testing

**Skills Evaluated but Omitted**:
- `git-master`: No git operations

**Parallelization**:
- **Can Run In Parallel**: NO (uses both auth states)
- **Parallel Group**: Wave 4 (after Waves 2-3)
- **Blocks**: Task 11
- **Blocked By**: Task 2

**References** (CRITICAL - Be Exhaustive):

**Documentation References**:
- `specs/dashboard-test-plan.md:659-728` - Integration specifications
- `.sisyphus/drafts/playwright-test-generation.md:136-149` - Auth flow details

**WHY Each Reference Matters**:
- Test plan gives exact integration points to verify
- Draft notes detail actual auth flow discovered

**Acceptance Criteria**:

**Automated Verification:**
```bash
npx playwright test tests/integration.spec.ts
# Assert: Login/logout flows work end-to-end
# Assert: API calls include auth headers
```

**Commit**: YES
- Message: `test(e2e): add integration tests (8.1-8.3)`
- Files: `tests/integration.spec.ts`
- Pre-commit: `npx playwright test tests/integration.spec.ts`

---

### Task 11: Run Full Suite and Heal Failing Tests

**What to do**:
1. Run complete test suite: `npx playwright test`
2. Collect failure report
3. For each failure:
   - Analyze error message and screenshot
   - Determine if selector issue, timing issue, or actual bug
   - Fix test code (not application code)
   - If unfixable, mark with `.fixme('reason')` and document
4. Re-run until stable
5. Generate final test report

**Must NOT do**:
- Delete tests to make suite pass
- Modify application code
- Use `.skip()` without converting to `.fixme()` with reason
- Give up after one healing pass

**Recommended Agent Profile**:
- **Category**: `unspecified-high`
  - Reason: Complex debugging and multi-file fixes
- **Skills**: [`playwright`]
  - `playwright`: Test debugging and healing

**Skills Evaluated but Omitted**:
- `typescript-programmer`: Playwright skill covers debugging needs
- `python-debugger`: Not Python

**Parallelization**:
- **Can Run In Parallel**: NO
- **Parallel Group**: Wave 5 (sequential)
- **Blocks**: Task 12
- **Blocked By**: Tasks 3-10 (all test files)

**References** (CRITICAL - Be Exhaustive):

**Pattern References**:
- All generated test files from Tasks 3-10
- Playwright trace files for debugging

**External References**:
- Playwright trace viewer: https://playwright.dev/docs/trace-viewer
- Playwright debugging: https://playwright.dev/docs/debug

**Acceptance Criteria**:

**Automated Verification:**
```bash
npx playwright test --reporter=json > test-results.json

# Count results
passed=$(jq '.suites[].specs[].tests[].results[].status' test-results.json | grep -c '"passed"')
failed=$(jq '.suites[].specs[].tests[].results[].status' test-results.json | grep -c '"failed"')
fixme=$(grep -r "\.fixme(" tests/*.spec.ts | wc -l)

echo "Passed: $passed"
echo "Failed: $failed"  
echo "Fixme: $fixme"

# Assert: No failures (all are either passed or fixme'd)
test $failed -eq 0 && echo "PASS: All tests pass or have fixme"
```

**Commit**: YES
- Message: `test(e2e): heal failing tests and finalize suite`
- Files: Any modified test files
- Pre-commit: `npx playwright test`

---

### Task 12: Cleanup and Documentation

**What to do**:
1. Remove or refactor old `tests/dashboard.spec.ts`:
   - Migrate any unique tests to appropriate new files
   - Delete duplicate tests
   - Keep file if it serves a unique purpose
2. Update `README.md` with:
   - New test file structure
   - How to run tests by project (admin/user/unauthenticated)
   - Auth setup notes
3. Verify `.gitignore` excludes `storageState/*.json`
4. Final test run to confirm everything works

**Must NOT do**:
- Delete tests without verifying coverage
- Commit storageState files

**Recommended Agent Profile**:
- **Category**: `quick`
  - Reason: Documentation and cleanup tasks
- **Skills**: [`git-master`]
  - `git-master`: Final commit organization

**Skills Evaluated but Omitted**:
- `playwright`: No browser testing
- `typescript-programmer`: Minimal code changes

**Parallelization**:
- **Can Run In Parallel**: NO
- **Parallel Group**: Wave 5 (final)
- **Blocks**: None (end of chain)
- **Blocked By**: Task 11

**References** (CRITICAL - Be Exhaustive):

**Pattern References**:
- `README.md` - Current documentation to update
- `tests/dashboard.spec.ts` - File to evaluate for removal

**Acceptance Criteria**:

**Automated Verification:**
```bash
# Final full test run
npx playwright test

# Verify no storageState committed
git status | grep -q "storageState/" && echo "FAIL: storageState tracked" || echo "PASS: storageState not tracked"

# Verify README updated
grep -q "chromium-admin" README.md && echo "PASS: README documents projects"
```

**Commit**: YES
- Message: `test(e2e): cleanup and update documentation`
- Files: `README.md`, potentially `tests/dashboard.spec.ts` removal
- Pre-commit: `npx playwright test`

---

## Commit Strategy

| After Task | Message | Files | Verification |
|------------|---------|-------|--------------|
| 1 | `test(e2e): add global setup for Keycloak authentication` | global-setup.ts, storageState/.gitkeep | TypeScript compiles |
| 2 | `test(e2e): configure playwright projects for authenticated testing` | playwright.config.ts | Config compiles |
| 3 | `test(e2e): add authentication test scenarios (1.1-1.5)` | tests/auth.spec.ts | Tests pass |
| 4 | `test(e2e): add dashboard content tests (2.1-2.4)` | tests/dashboard-content.spec.ts | Tests pass |
| 5 | `test(e2e): add navigation tests (3.1-3.4)` | tests/navigation.spec.ts | Tests pass |
| 6 | `test(e2e): add error handling tests (4.1-4.5)` | tests/error-handling.spec.ts | Tests pass |
| 7 | `test(e2e): add accessibility tests (5.1-5.4)` | tests/accessibility.spec.ts | Tests pass |
| 8 | `test(e2e): add performance tests (6.1-6.2)` | tests/performance.spec.ts | Tests pass |
| 9 | `test(e2e): add security tests (7.1-7.3)` | tests/security.spec.ts | Tests pass |
| 10 | `test(e2e): add integration tests (8.1-8.3)` | tests/integration.spec.ts | Tests pass |
| 11 | `test(e2e): heal failing tests and finalize suite` | Modified test files | Full suite passes |
| 12 | `test(e2e): cleanup and update documentation` | README.md, old files | Full suite passes |

---

## Success Criteria

### Verification Commands
```bash
# Run full suite
npx playwright test

# Expected output:
# Running X tests using Y workers
# ...
# X passed (Yms)

# View report
npx playwright show-report
```

### Final Checklist
- [ ] All 39 scenarios from test plan have corresponding tests
- [ ] `npx playwright test` passes (or only has documented .fixme())
- [ ] Auth infrastructure works (globalSetup creates storageState files)
- [ ] No credentials committed to repository
- [ ] README documents how to run tests
- [ ] Old dashboard.spec.ts cleaned up
- [ ] storageState/ directory gitignored
