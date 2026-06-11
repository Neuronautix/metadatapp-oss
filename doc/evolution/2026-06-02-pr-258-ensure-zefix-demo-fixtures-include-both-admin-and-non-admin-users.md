# Evolution Report - PR #258

## Merge metadata

- Date: 2026-06-02
- PR: #258
- Title: Ensure Zefix demo fixtures include both admin and non-admin users
- Branch: copilot/demo-account-admin-issue
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- **Fixture updates** in `ZefixFixtures`:
  - Kept `zefix.demo@metadatapp.net` as the demo admin user.
  - Added `zefix.viewer@metadatapp.net` as a standard non-admin user (`ROLE_USER` default) under `Zefix Demo Facility`.
  - Introduced separate constants for the demo admin email (`DEMO_ADMIN_USER_EMAIL`) and demo standard-user email (`DEMO_USER_EMAIL`).
- **Functional test coverage** in `ZefixFixturesTest`:
  - Updated to assert both demo users are created and attached to the demo account.
  - Added role assertions: admin user includes `ROLE_ADMIN`; standard user does not include `ROLE_ADMIN`.
- 2 changed files (+19 / -5 lines).

## What it brings

- The Zefix demo dataset now always includes one admin account and one standard non-admin account in the same demo facility.
- Demo scenarios requiring both privilege levels (e.g. showing admin-only UI vs standard user view) are reliably reproducible.
- Functional tests enforce the expected user/role structure, preventing regression.

## Benefits

- User benefit: Demo users and sales team can reliably demonstrate admin vs standard user behaviour without manual fixture adjustment.
- Product benefit: Demos of role-based features (admin settings, access control) are consistent and repeatable.
- Engineering benefit: Test assertions for user role structure catch fixture regressions early; constants for demo email addresses reduce magic strings.
- Operational benefit: Demo environment setup is deterministic; no manual intervention needed to get both user roles into the demo dataset.

## Long-term vision

- Strategic theme: Reliable, role-complete demo datasets as a standard for all demo facilities.
- Horizon impact: Short term — immediate fix for demo reliability; the pattern is repeatable for other demo accounts.
- Future opportunities unlocked: Multi-role scenario testing for all connected-app demo fixtures.

## Risks and tradeoffs

- Demo credentials are hardcoded in fixtures; any credential rotation requires a fixture update.
- The `zefix.viewer@metadatapp.net` account uses default `ROLE_USER`; if default role behaviour changes, the fixture test assertion may need updating.

## Follow-up actions

- [ ] Apply the same admin+viewer fixture pattern to other demo facilities (TecniPlast, Elabftw demo, etc.) as needed (owner: QA, target: TBD)

## References

- Merge commit: 81d026adb6b5a18816558a0f2b96a0a770d9a294
