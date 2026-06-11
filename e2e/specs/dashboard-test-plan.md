# Dashboard Test Plan

**Application:** MMAPP DEMO  
**Page:** Dashboard (`/admin`)  
**Base URL:** https://localhost  
**Last Updated:** 2026-01-31

## Overview

This test plan covers comprehensive testing of the Dashboard page, including authentication flows, user interactions, and data displays. The application uses Keycloak for authentication.

## Test Accounts

- **User Account:** njuke.oaye@example.invalid / Pa55w0rd
- **Admin Account:** doctor.yiolyo@example.invalid / Pa55w0rd

---

## 1. Authentication and Access

### 1.1 Successful Admin Login and Dashboard Access

**Prerequisites:** User is logged out

**Steps:**
1. Navigate to `/admin`
2. Verify redirect to Keycloak sign-in page with heading "Sign in to your account"
3. Enter username: `doctor.yiolyo@example.invalid`
4. Enter password: `Pa55w0rd`
5. Click "Sign In" button
6. Wait for navigation to dashboard

**Expected Outcome:**
- User successfully authenticates with Keycloak
- Redirected to dashboard at `/admin`
- Dashboard content loads and displays
- Page title changes from "Sign in to MMAPP DEMO" to dashboard title

**Success Criteria:**
- Authentication completes without errors
- Dashboard page loads within 15 seconds
- Main content area is visible

---

### 1.2 Successful User Login and Dashboard Access

**Prerequisites:** User is logged out

**Steps:**
1. Navigate to `/admin`
2. Verify redirect to Keycloak sign-in page
3. Enter username: `njuke.oaye@example.invalid`
4. Enter password: `Pa55w0rd`
5. Click "Sign In" button
6. Wait for navigation

**Expected Outcome:**
- User successfully authenticates
- Redirected to appropriate page (dashboard or access denied based on permissions)
- If access is granted, dashboard loads successfully
- If access is denied, appropriate error message is displayed

**Success Criteria:**
- Authentication completes without errors
- User receives appropriate access based on role

---

### 1.3 Failed Login - Invalid Credentials

**Prerequisites:** User is logged out

**Steps:**
1. Navigate to `/admin`
2. Wait for Keycloak sign-in page to load
3. Enter username: `invalid.user@example.invalid`
4. Enter password: `WrongPassword`
5. Click "Sign In" button

**Expected Outcome:**
- Authentication fails
- Error message is displayed: "Invalid username or password" or similar
- User remains on sign-in page
- Form fields remain populated (username) or are cleared (password)

**Success Criteria:**
- Appropriate error message is shown
- No navigation occurs
- User can retry login

---

### 1.4 Failed Login - Empty Fields

**Prerequisites:** User is logged out

**Steps:**
1. Navigate to `/admin`
2. Wait for Keycloak sign-in page to load
3. Leave username field empty
4. Leave password field empty
5. Click "Sign In" button

**Expected Outcome:**
- Form validation prevents submission OR
- Server returns validation error
- Error message indicates required fields

**Success Criteria:**
- User cannot proceed with empty credentials
- Clear feedback is provided

---

### 1.5 Unauthenticated Access Redirect

**Prerequisites:** User is logged out, no session exists

**Steps:**
1. Attempt to navigate directly to `/admin`

**Expected Outcome:**
- User is immediately redirected to Keycloak sign-in page
- Original destination (`/admin`) is preserved for post-login redirect
- Sign-in page displays correctly

**Success Criteria:**
- Redirect happens within 2 seconds
- No dashboard content is briefly visible
- Post-login redirect works correctly

---

## 2. Dashboard Content and Layout

### 2.1 Dashboard Initial Load - Admin User

**Prerequisites:** User is authenticated as admin (doctor.yiolyo@example.invalid)

**Steps:**
1. Ensure user is logged in as admin
2. Navigate to `/admin`
3. Wait for page to fully load (loading indicators disappear)

**Expected Outcome:**
- Dashboard page loads successfully
- Loading indicator appears and then disappears
- All primary sections are visible:
  - Navigation/header
  - Main content area
  - Any sidebars or panels
- Page title is set correctly
- Main headings (h1, h2, h3, etc.) are visible

**Success Criteria:**
- Page loads within 15 seconds
- No console errors
- All major sections render
- Content is accessible and readable

---

### 2.2 Dashboard Initial Load - Regular User

**Prerequisites:** User is authenticated as regular user (njuke.oaye@example.invalid)

**Steps:**
1. Ensure user is logged in as regular user
2. Navigate to `/admin`
3. Wait for page to fully load

**Expected Outcome:**
- Dashboard loads successfully OR access is denied based on permissions
- If access is granted:
  - Dashboard displays with appropriate permissions
  - User may see limited functionality compared to admin
- If access is denied:
  - Clear "Access Denied" or permission error is shown

**Success Criteria:**
- Appropriate access control is enforced
- User experience is clear and understandable

---

### 2.3 Loading States

**Prerequisites:** User is authenticated

**Steps:**
1. Navigate to `/admin`
2. Observe loading indicators immediately after navigation
3. Monitor the transition from loading to loaded state

**Expected Outcome:**
- Loading indicator (via `data-testid="loading"`) appears during data fetch
- Loading state is visible for a reasonable time
- Loading indicator disappears when content is ready
- No "flash of unstyled content"

**Success Criteria:**
- Loading state provides user feedback
- Transition to loaded state is smooth
- Content doesn't shift unexpectedly

---

### 2.4 Empty State Handling

**Prerequisites:** User is authenticated, no data exists for display

**Steps:**
1. Navigate to `/admin` with empty or cleared data
2. Observe how empty sections are handled

**Expected Outcome:**
- Empty states show helpful messages like:
  - "No items to display"
  - "Get started by..."
  - Appropriate illustrations or icons
- UI remains functional
- No broken layouts or errors

**Success Criteria:**
- Empty states are user-friendly
- Clear calls-to-action are provided
- No confusing blank spaces

---

## 3. Navigation and Interaction

### 3.1 Dashboard Navigation Menu

**Prerequisites:** User is authenticated and on dashboard

**Steps:**
1. Locate the main navigation menu
2. Identify all navigation items/links
3. Click each navigation item
4. Verify navigation to correct pages

**Expected Outcome:**
- All navigation items are clickable
- Each item navigates to the correct destination
- Active/current page is visually indicated
- Navigation is accessible via keyboard (Tab key)

**Success Criteria:**
- All links work correctly
- No broken links or 404 errors
- Keyboard navigation functions properly

---

### 3.2 Dashboard Interactive Elements - Buttons

**Prerequisites:** User is authenticated and on dashboard

**Steps:**
1. Identify all buttons on the dashboard
2. Hover over each button (observe hover states)
3. Click each button
4. Verify expected action occurs

**Expected Outcome:**
- All buttons have clear labels
- Hover states provide visual feedback
- Buttons perform their intended actions
- Loading states appear for async operations

**Success Criteria:**
- All buttons are functional
- User receives feedback for actions
- No console errors on interaction

---

### 3.3 Dashboard Interactive Elements - Forms

**Prerequisites:** User is authenticated and on dashboard

**Steps:**
1. Locate any forms on the dashboard
2. Fill out form fields with valid data
3. Submit the form
4. Verify success feedback and data persistence

**Expected Outcome:**
- Form fields accept appropriate input
- Validation works correctly
- Submit action completes successfully
- Success message is displayed
- Data is saved/updated

**Success Criteria:**
- Forms function as expected
- Validation prevents invalid submissions
- User receives clear feedback

---

### 3.4 Dashboard Interactive Elements - Tables/Lists

**Prerequisites:** User is authenticated and on dashboard with data

**Steps:**
1. Locate any tables or lists of data
2. Verify data is displayed correctly
3. Test sorting (if available) by clicking column headers
4. Test pagination (if available)
5. Test filtering/search (if available)

**Expected Outcome:**
- Data displays in organized format
- Sorting changes data order correctly
- Pagination navigates through pages
- Search/filter updates visible data
- Actions on rows (edit, delete, view) work correctly

**Success Criteria:**
- All table/list features function properly
- Data integrity is maintained
- Performance is acceptable with large datasets

---

## 4. Error Handling and Edge Cases

### 4.1 Network Error - API Unavailable

**Prerequisites:** API service is stopped or unreachable

**Steps:**
1. Stop the API service (Docker: `docker compose stop php`)
2. Authenticate and navigate to `/admin`
3. Observe error handling

**Expected Outcome:**
- Dashboard shows error message indicating connectivity issue
- Error message is user-friendly (not technical stack trace)
- User can retry or is prompted to refresh
- No infinite loading states

**Success Criteria:**
- Error is handled gracefully
- User is informed of the issue
- Application remains stable

---

### 4.2 Slow Network Conditions

**Prerequisites:** Network throttling is enabled (Chrome DevTools)

**Steps:**
1. Enable network throttling (Slow 3G)
2. Navigate to `/admin`
3. Observe loading behavior
4. Interact with dashboard elements

**Expected Outcome:**
- Loading indicators show extended time
- Content loads progressively (not all at once)
- UI remains responsive
- No timeouts or errors

**Success Criteria:**
- Dashboard functions on slow connections
- User experience is acceptable
- Critical content loads first

---

### 4.3 Session Expiration

**Prerequisites:** User is authenticated with short session timeout

**Steps:**
1. Log in as admin
2. Wait for session to expire (or manually expire session)
3. Attempt to interact with dashboard

**Expected Outcome:**
- Session expiration is detected
- User is redirected to login page
- Original action is preserved for retry after re-auth (if applicable)
- Clear message explains session expired

**Success Criteria:**
- Session expiration is handled gracefully
- User can re-authenticate easily
- No data loss occurs

---

### 4.4 Browser Back Button

**Prerequisites:** User is authenticated and has navigated within dashboard

**Steps:**
1. Navigate to `/admin`
2. Navigate to a sub-page or different section
3. Click browser back button
4. Verify return to previous state

**Expected Outcome:**
- Back button navigates to previous page
- Page state is restored correctly
- No unexpected reloads or errors
- User remains authenticated

**Success Criteria:**
- Browser history works as expected
- Application state is preserved
- No broken navigation

---

### 4.5 Browser Refresh

**Prerequisites:** User is authenticated and on dashboard

**Steps:**
1. Navigate to `/admin`
2. Interact with the page (fill forms, expand sections, etc.)
3. Refresh the page (F5 or Cmd+R)

**Expected Outcome:**
- Page reloads successfully
- User remains authenticated (if session is valid)
- Dashboard loads to default state
- No errors occur

**Success Criteria:**
- Refresh works correctly
- Authentication persists
- Application recovers to stable state

---

## 5. Accessibility and Usability

### 5.1 Keyboard Navigation

**Prerequisites:** User is authenticated

**Steps:**
1. Navigate to `/admin` using keyboard only (Tab, Enter, Arrow keys)
2. Tab through all interactive elements
3. Activate buttons/links using Enter or Space
4. Navigate forms using Tab and Arrow keys

**Expected Outcome:**
- All interactive elements are reachable via keyboard
- Focus indicators are visible
- Tab order is logical
- Actions can be performed without mouse

**Success Criteria:**
- Dashboard is fully keyboard accessible
- Focus management is correct
- No keyboard traps exist

---

### 5.2 Screen Reader Compatibility

**Prerequisites:** Screen reader is enabled (NVDA, JAWS, or VoiceOver)

**Steps:**
1. Navigate to `/admin` with screen reader active
2. Listen to page announcements
3. Navigate through headings and landmarks
4. Activate buttons and links

**Expected Outcome:**
- Page title is announced
- Headings provide structure
- Buttons and links have descriptive labels
- Form fields have associated labels
- Status messages are announced

**Success Criteria:**
- Dashboard is navigable by screen reader
- All content is accessible
- ARIA labels and roles are used correctly

---

### 5.3 Responsive Design - Mobile

**Prerequisites:** User is authenticated, viewport is mobile size (375x667)

**Steps:**
1. Resize browser to mobile dimensions or use device emulation
2. Navigate to `/admin`
3. Verify layout adapts correctly
4. Test touch interactions

**Expected Outcome:**
- Layout adapts to mobile screen
- Content is readable without horizontal scroll
- Touch targets are appropriately sized (44x44px minimum)
- Navigation is accessible (hamburger menu or similar)
- All functionality works on mobile

**Success Criteria:**
- Dashboard is fully functional on mobile
- Layout is responsive
- User experience is optimized for small screens

---

### 5.4 Responsive Design - Tablet

**Prerequisites:** User is authenticated, viewport is tablet size (768x1024)

**Steps:**
1. Resize browser to tablet dimensions
2. Navigate to `/admin`
3. Verify layout utilizes available space
4. Test both portrait and landscape orientations

**Expected Outcome:**
- Layout adapts to tablet screen
- Content is well-organized
- Navigation is appropriate for tablet
- Touch targets are appropriately sized

**Success Criteria:**
- Dashboard works well on tablet
- Layout makes good use of space
- UX is optimized for tablet interaction

---

## 6. Performance and Data Handling

### 6.1 Large Dataset Handling

**Prerequisites:** Dashboard displays list/table with many items (100+)

**Steps:**
1. Navigate to `/admin` with large dataset
2. Observe initial load time
3. Test scrolling performance
4. Test sorting and filtering with large dataset

**Expected Outcome:**
- Page loads in reasonable time (< 5 seconds)
- Scrolling is smooth
- Pagination or virtual scrolling is used if needed
- Sorting/filtering remains responsive

**Success Criteria:**
- Performance is acceptable with large data
- UI remains responsive
- Memory usage is reasonable

---

### 6.2 Concurrent User Actions

**Prerequisites:** Multiple browser tabs/windows open to dashboard

**Steps:**
1. Open dashboard in two browser tabs
2. Perform action in tab 1 (create/update data)
3. Observe tab 2 for updates
4. Perform action in tab 2
5. Observe tab 1

**Expected Outcome:**
- Changes in one tab eventually reflect in other tabs OR
- User is notified of stale data
- No data conflicts or corruption
- Actions in both tabs complete successfully

**Success Criteria:**
- Concurrent access is handled properly
- Data integrity is maintained
- User experience is clear

---

## 7. Security

### 7.1 Authorization - Admin vs User

**Prerequisites:** Two accounts with different permission levels

**Steps:**
1. Log in as regular user
2. Attempt to access admin-only features
3. Log out and log in as admin
4. Verify admin features are accessible

**Expected Outcome:**
- Regular user cannot access admin features
- Appropriate error message or UI hiding occurs
- Admin user has full access to all features
- No privilege escalation is possible

**Success Criteria:**
- Authorization is enforced correctly
- Clear feedback is provided
- Security boundaries are maintained

---

### 7.2 HTTPS and Secure Connection

**Prerequisites:** Application is accessed via HTTPS

**Steps:**
1. Navigate to `https://localhost/admin`
2. Verify browser shows secure connection indicator
3. Check for mixed content warnings
4. Verify all API calls use HTTPS

**Expected Outcome:**
- Connection is secure (HTTPS)
- No mixed content warnings
- All resources load over HTTPS
- Certificates are valid (or self-signed warning is expected)

**Success Criteria:**
- Application enforces HTTPS
- No security warnings (except expected self-signed cert)
- All traffic is encrypted

---

### 7.3 XSS Prevention

**Prerequisites:** User has ability to input text

**Steps:**
1. Navigate to `/admin`
2. Find input field (form, search, etc.)
3. Enter XSS payload: `<script>alert('XSS')</script>`
4. Submit and observe behavior

**Expected Outcome:**
- Script does not execute
- Input is sanitized/escaped
- No alert popup appears
- Content is displayed as plain text

**Success Criteria:**
- XSS attacks are prevented
- Input validation and sanitization work correctly
- Application security is maintained

---

## 8. Integration Points

### 8.1 Keycloak Integration - Login Flow

**Prerequisites:** Keycloak service is running

**Steps:**
1. Navigate to `/admin` (not authenticated)
2. Verify redirect to Keycloak login page
3. Complete login
4. Verify redirect back to dashboard

**Expected Outcome:**
- Seamless redirect to Keycloak
- Login page displays correctly
- Authentication completes successfully
- Return redirect preserves intended destination

**Success Criteria:**
- Keycloak integration works correctly
- User experience is smooth
- No errors in authentication flow

---

### 8.2 Keycloak Integration - Logout Flow

**Prerequisites:** User is authenticated

**Steps:**
1. Navigate to `/admin` (authenticated)
2. Locate and click logout button
3. Verify logout process

**Expected Outcome:**
- User is logged out of application
- Session is invalidated
- User is logged out of Keycloak (single logout)
- Redirect to login page or home page
- Cannot access protected pages after logout

**Success Criteria:**
- Logout completes successfully
- Session is fully terminated
- Single sign-out works if applicable

---

### 8.3 API Communication

**Prerequisites:** User is authenticated, API is running

**Steps:**
1. Navigate to `/admin`
2. Open browser DevTools Network tab
3. Observe API requests
4. Verify responses

**Expected Outcome:**
- API requests are made to correct endpoints
- Responses return expected data
- Proper HTTP status codes (200, 201, etc.)
- Authentication headers are included
- Response times are reasonable

**Success Criteria:**
- API integration works correctly
- Data is fetched and displayed
- Error responses are handled

---

## Test Execution Notes

### Setup Requirements
- Docker Compose services running: `docker compose up -d --wait`
- Services healthy: Osoma, API, Database, Keycloak
- Base URL: https://osoma.metadatapp.test
- Self-signed certificate warnings are expected

### Test Data
- Test accounts are pre-configured via Keycloak Config CLI
- Data may need seeding before certain tests
- Consider data cleanup between test runs

### Known Issues
- Self-signed HTTPS certificate requires `ignoreHTTPSErrors: true` in Playwright config
- MSW must be disabled in E2E tests via localStorage: `use_msw: false`

### Test Environment
- Browser: Chromium (Playwright)
- Viewport: Desktop default (1280x720) unless testing responsive
- Network: Normal unless testing throttled conditions

---

## Success Metrics

A comprehensive test suite should:
- Achieve >80% coverage of user workflows
- Include both positive and negative test cases
- Cover all authentication and authorization scenarios
- Validate accessibility standards (WCAG 2.1 Level AA)
- Test performance under various conditions
- Verify security boundaries

## Future Enhancements

Consider adding tests for:
- Multi-language support (i18n)
- Dark mode / theme switching
- Notification systems
- Real-time updates (WebSocket/SSE)
- Export/import functionality
- Advanced search and filtering
- Batch operations
- Audit logs
