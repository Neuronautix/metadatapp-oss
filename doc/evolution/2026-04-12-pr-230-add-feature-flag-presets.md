# Evolution Report - PR #230

## Merge metadata

- Date: 2026-04-12
- PR: #230
- Title: feat: add zefix, tecniplast, and god feature-flag presets with account-level enforcement
- Branch: copilot/define-user-profiles-zefix-tecniplast-god
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

### New feature flag: `zefix.enabled`
- Decouples zebrafish (Zefix) sidebar navigation from the shared `non-metadatapp-feat.enabled` flag.
- Zefix sidebar items (Batches, Lines, Housing Management, Rooms, Systems, Zefix Alerts, ZEFIX Overview) now follow `zefix.enabled`.
- Tecniplast operations items (Operations, Facility Map, Sensors, Conditions, Alarms) keep `non-metadatapp-feat.enabled`.
- Existing presets (`professional`, `demo`) explicitly set `zefix.enabled: true` — no behavior change for current users.

### Three new named presets (`presets.ts`)
| Key | Label | Profile |
|---|---|---|
| `zefix` | Zefix / Zebrafish | Only zebrafish dashboards and bio pages; both `metadatapp-feat` and `non-metadatapp-feat` off |
| `tecniplast` | Tecniplast / Mice | Subjects, cages, TP operations, DVC sensors/analytics, connected apps; `zefix.enabled: false` |
| `god` | God / All Access | All flags on — equivalent to `studyal` |

### Account-level preset enforcement
- `Account` entity gains a nullable `featurePreset` field (`zefix`, `tecniplast`, `god`).
- New `FeatureFlagController` serves `GET /api/feature-flags/overrides`; when the logged-in user's account has a `featurePreset`, the endpoint returns the full flag overrides plus `"enforced": true`.
- `POST /api/feature-flags/overrides` and `POST /api/feature-flags/presets` are silently no-op'd when a preset is enforced, preventing manual overrides.
- Doctrine migration `Version20260412120000` adds the `feature_preset` column to the `account` table.
- Frontend `FeatureFlagProvider.tsx` reads `preset` and `enforced` from the overrides response; exposes `isPresetEnforced` in context; `setFlag`, `setCategoryFlags`, and `applyPreset` become no-ops when enforced.
- MSW mock updated: `MOCK_ACCOUNT_PRESET` constant allows simulating an enforced profile in development.

## What it brings

- Operators can assign a feature preset to an account so all users in that account automatically see the correct UI profile (Zefix, Tecniplast, or all-access) on login.
- Zebrafish and mice workflows are now independently gatable without affecting each other.
- Manual flag overrides are blocked for enforced accounts, preventing user confusion from mismatched flags.

## Benefits

- User benefit: Users automatically see only the features relevant to their lab setup; no manual flag configuration required.
- Product benefit: Enables clean multi-tenant SaaS deployments where different customer accounts have different product profiles.
- Engineering benefit: Named presets codify the intended feature bundles for each lab type, making future preset changes centrally manageable.
- Operational benefit: Account-level enforcement prevents support tickets caused by users accidentally changing their feature flags.

## Long-term vision

- Strategic theme: Multi-tenant feature segmentation as a foundation for differentiated product offerings (zebrafish labs vs. mice labs vs. full-access).
- Horizon impact: Medium to long term — enables product tiering and customer-specific deployments.
- Future opportunities unlocked: Preset-based billing tiers, onboarding flows that auto-apply the correct preset, and admin UI for managing account presets.

## Risks and tradeoffs

- The Doctrine migration adds a `feature_preset` column; rolling back requires a separate down migration.
- Account-level enforcement is silent from the user's perspective; there is currently no UI indication that flags are enforced, which may cause confusion when users try to change them.
- The `god` preset enables all flags, including potentially unstable or experimental features; accounts assigned `god` may encounter bugs not visible to standard users.

## Follow-up actions

- [ ] Add UI indicator in Flag Studio when flags are account-enforced, to communicate non-overridability to users (owner: osoma-worker, target: 2026-04-30)
- [ ] Add admin UI or Symfony command to assign/unassign `featurePreset` on an account without direct DB access (owner: api-backend-worker, target: 2026-04-30)
- [ ] Write PHPUnit tests for `FeatureFlagController` covering enforced and non-enforced account scenarios (owner: api-backend-worker, target: 2026-04-25)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/230
- Changed files: 10 files (+434 / -35 lines, 3 commits)
- Related: `api/src/Entity/Account.php`, `api/src/Controller/FeatureFlagController.php`, `osoma/src/app/FeatureFlagProvider.tsx`
