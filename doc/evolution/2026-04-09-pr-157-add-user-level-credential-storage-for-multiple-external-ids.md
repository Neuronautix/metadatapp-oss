# Evolution Report - PR #157

## Merge metadata

- Date: 2026-04-09
- PR: #157
- Title: [WIP] Add user-level credential storage for multiple external IDs
- Branch: copilot/organize-external-ids-data
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- Restored structured `authenticationParameters` JSON storage on `ConnectedApp` for per-user connected app credentials.
- Maintained backward compatibility with the legacy `token` field by falling back to token-like values from structured credentials.
- Added masked read-side credential hints so secrets are not exposed in API responses (password hints fully masked).
- Added app-specific validation for credential payloads: private integration requires `username`/`password`; Fair3r, ElabFTW, ProtocolIo, and Tecniplast require `apiKey`, `token`, or `accessToken`.
- Added focused API test coverage for create, update, masking, and validation flows including the `accessToken` path.
- Added a migration to restore the `authentication_parameters` JSON column on `connected_app`.

## What it brings

- A single item can now carry multiple external IDs (UUID in MAPP, ID in private integration, ID from Fair3r, etc.) through structured credential storage per connected app.
- Credential secrets are never returned in full through the API; only masked hints are visible, reducing accidental secret exposure.
- App-specific validation enforces the correct credential shape at write time, failing fast with actionable errors rather than silently storing bad data.

## Benefits

- User benefit: Credentials for multiple connected apps can be configured and managed independently without conflicts between different external system integrations.
- Product benefit: Removes the single-token limitation that prevented proper multi-system credential management; the `connected_app` model is now credential-type-aware.
- Engineering benefit: Structured JSON storage is extensible to new credential types without schema migrations; masking logic is centralised.
- Operational benefit: Reduces the risk of accidental credential exposure through API responses.

## Long-term vision

- Strategic theme: Secure, structured multi-system integration credential management.
- Horizon impact: Medium term — needed as more external systems (OSF, private integration, Tecniplast) are integrated.
- Future opportunities unlocked: Per-user credential override (users with their own API keys for shared apps), credential rotation tooling.

## Risks and tradeoffs

- Full containerised test execution was not possible in the CI environment due to `pecl.php.net` DNS block; some integration paths rely on PHP syntax validation only.
- Backward compatibility fallback adds complexity that should eventually be cleaned up once all clients use structured credentials.

## Follow-up actions

- [ ] Deprecate and remove legacy `token` field once all integrations migrate to structured `authenticationParameters` (owner: backend team, target: TBD)
- [ ] Add Playwright coverage for credential configuration UI (owner: frontend, target: TBD)

## References

- Fixes: #37
- Merge commit: e61a1f90449b3b10e83c61ffbbfc62e3b8c05033
