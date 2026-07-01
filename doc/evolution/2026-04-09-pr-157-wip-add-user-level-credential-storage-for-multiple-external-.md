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

- Restored structured credential storage on connected apps through `api/migrations/Version20260401112000.php` and the `authenticationParameters` field on `api/src/Entity/ConnectedApp.php`.
- Added app-specific validation and masking rules in `ConnectedApp`, including required `username` / `password` handling for SoftMouse and token-like key support for Fair3R, ElabFTW, Precliniset, Protocol.io, and Tecniplast.
- Expanded `api/tests/Api/ConnectedAppTest.php` to cover create, update, masking, and validation flows for the new structured credential payloads.

## What it brings

- Lets the platform store richer per-app authentication payloads than a single flat token field.
- Preserves backward compatibility by still deriving token-like behaviour from the structured credential map where possible.
- Prevents accidental secret disclosure on reads by returning masked hints instead of raw values.

## Benefits

- User benefit: Connected-app configuration can represent the credential shapes real external systems need.
- Product benefit: The platform is better positioned to support multiple external IDs and connection styles across integrations.
- Engineering benefit: Validation now lives next to the entity rules, and the new API tests document the expected payload shapes.
- Operational benefit: Masking behaviour reduces the chance of secrets being echoed back through the API.

## Long-term vision

- Strategic theme: Evolve connected-app support from one-off token fields into a reusable integration credential layer.
- Horizon impact: Medium term — this is foundation work that later linked-resource and multi-system sync features depend on.
- Future opportunities unlocked: More capable connected-app configuration UIs and per-integration credential management can build on the structured JSON shape introduced here.

## Risks and tradeoffs

- The change still stores structured credentials on `ConnectedApp` rather than in a dedicated credential entity, so later multi-user or multi-secret scenarios may need another refactor.
- Validation currently covers the known app codes but may need expansion as new integrations arrive.

## Follow-up actions

- [ ] Reassess whether credentials should move to a dedicated model once multiple per-resource external identifiers become a first-class feature (owner: backend team, target: backlog)
- [ ] Extend the same masking and validation rules to any new connected-app code added after this merge (owner: backend team, target: backlog)

## References

- PR: https://github.com/Neuronautix/metadatapp/pull/157
- Changed areas: `api/src/Entity/ConnectedApp.php`, `api/tests/Api/ConnectedAppTest.php`, `api/migrations/Version20260401112000.php`
- Validation evidence (tests, checks, metrics): PR notes mention PHP syntax checks and automated code review feedback; full containerized test execution was blocked in that environment by a `pecl.php.net` dependency failure.
