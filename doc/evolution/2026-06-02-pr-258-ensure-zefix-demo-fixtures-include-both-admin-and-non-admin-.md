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

- Updated `api/src/DataFixtures/ZefixFixtures.php` so the Zefix demo account now creates both an admin user (`zefix.demo@metadatapp.net`) and a standard viewer (`zefix.viewer@metadatapp.net`).
- Added matching assertions in `api/tests/Functional/ZefixFixturesTest.php` to verify both users are attached to the same demo account and that only the admin user carries `ROLE_ADMIN`.
- Split the former single demo-user constant into explicit admin and standard-user constants so the fixture intent is unambiguous.

## What it brings

- Makes the demo dataset more realistic by exposing both privilege levels in the same example facility.
- Gives demos and manual verification a stable non-admin account instead of overloading one admin-only identity.
- Protects the fixture from regressing back to a single-user assumption through the new functional assertions.

## Benefits

- User benefit: Demo users can show both admin and standard-user behaviour without creating ad hoc extra accounts.
- Product benefit: The Zefix demo better represents real account-role combinations.
- Engineering benefit: The functional test now documents the expected role split directly in code.
- Operational benefit: Support and demo flows have a predictable viewer identity to use.

## Long-term vision

- Strategic theme: Make demo data trustworthy enough to exercise real permission flows, not just happy-path records.
- Horizon impact: Short term — the benefit is immediate for anyone using the Zefix demo fixtures.
- Future opportunities unlocked: Other demo accounts and fixture suites can adopt the same explicit role-coverage pattern.

## Risks and tradeoffs

- The fix only covers the Zefix demo dataset; other fixture bundles may still implicitly assume one demo user per account.
- More role-specific demo users means future fixture maintenance must keep those identities consistent with evolving auth rules.

## Follow-up actions

- [ ] Review other demo and fixture accounts to ensure they also cover the role combinations their demos rely on (owner: backend team, target: backlog)
- [ ] Keep the fixture tests aligned with any future role-model changes so the viewer/admin split does not silently drift (owner: backend team, target: backlog)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/258
- Changed areas: `api/src/DataFixtures/ZefixFixtures.php`, `api/tests/Functional/ZefixFixturesTest.php`
- Validation evidence (tests, checks, metrics): the merged functional test now asserts both demo users exist and verifies the admin/non-admin role split.
