# Evolution Report - PR #244

## Merge metadata

- Date: 2026-05-05
- PR: #244
- Title: Integrate reviewed corrections from LOLOpus4.7 expert
- Branch: integrate-brother-zip
- Contributors: Damien Huzard, LOLOpus4.7
- Reviewer(s): LOLOpus4.7

## What was merged

This branch consolidates a substantial "brother-zip" review pass across backend capabilities, Osoma integration points, and open-source-release hardening.

- Added first-class connected resource links on experiments/studies, with API Platform CRUD, account scoping, seeded demo links, study-page rendering in Osoma, and export propagation into FAIR2 JSON-LD and RO-Crate.
- Hardened AI provider credential handling with a user-scoped `LlmProviderCredential` entity, libsodium-encrypted storage behind `App\Secrets\SecretStoreInterface`, masked hints in API responses, and Osoma settings support for OpenAI and Anthropic keys.
- Expanded reporting and DVC analytics with circadian metric computation, experiment summary and cage circadian endpoints, structured FAIR and ARRIVE helper PDF generation, and follow-on tests around access control and report content.
- Continued import and release readiness work by introducing `DvcImportServiceInterface`, reconciling Doctrine migration drift, clarifying public-safe credential documentation, and strengthening public CI with fork-safe registry login and a clean-clone smoke workflow.
- Reframed top-level project docs (`README.md`, `OPEN_SOURCE_READINESS.md`, audit notes) around open-source continuity, release blockers, public-safe defaults, and known publication risks.

## What it brings

- Studies can now surface provenance links to external systems such as eLabFTW, SoftMouse, Tecniplast DVC, and OSF both in the UI and in exported metadata bundles.
- Users can configure AI providers without exposing raw keys back to the browser, while deployments retain an escape hatch to swap the encrypted-column default for a managed secret store.
- FAIR and ARRIVE reporting moved from a thin PDF endpoint into a more explicit reporting layer with better text rendering, richer content, and stronger account-boundary tests.
- DVC-backed experiment summaries now expose circadian parameters and cage-grouped subject metadata suitable for dashboards and downstream analytics.
- Public release preparation became more operationally concrete through smoke checks, fork-safe CI behavior, a credential-handling guide, and an explicit checklist of remaining blockers.

## Benefits

- User benefit: Researchers can inspect connected external resources directly from study pages and configure AI providers with safer credential handling.
- Product benefit: The platform now tells a stronger interoperability story by carrying connected-resource provenance through FAIR2 and RO-Crate exports.
- Engineering benefit: Secret storage is abstracted behind an interface, DVC import is decoupled from one concrete implementation, and report generation is better modularized.
- Operational benefit: Public CI is safer for fork PRs, and the clean-clone smoke workflow gives a low-cost signal that the repository still boots as an open-source artifact.

## Long-term vision

- Strategic theme: productize interoperability, safer AI configuration, and open-source sustainability without weakening tenant isolation.
- Horizon impact: medium term
- Future opportunities unlocked: managed secret-store adapters, richer connected-app synchronization provenance, scheduled FAIR/ARRIVE reporting, and stronger DVC-driven welfare analytics.

## Risks and tradeoffs

- `SecretStoreInterface` currently abstracts storage, but the shipped implementation is still a single-process sodium-encrypted database column rather than a managed secret store.
- The branch combines product work, release-hardening, and documentation updates, so regression risk spans backend exports, study views, AI settings, and CI behavior.
- Open-source readiness is improved but not finished; full-history secret remediation and third-party publication confirmation remain outside this merge.

## Follow-up actions

- [ ] Ship at least one managed secret-store adapter and document the production override path (owner: Damien Huzard, target: 2026-05-31)
- [ ] Add broader integration coverage for connected-resource exports and DVC import/reporting interactions (owner: Damien Huzard, target: 2026-05-31)
- [ ] Close the remaining open-source publication blockers in `OPEN_SOURCE_READINESS.md` before any public release cut (owner: Damien Huzard, target: 2026-05-31)

## References

- Ticket(s): PR #244
- Related docs: `OPEN_SOURCE_READINESS.md`, `docs/CREDENTIALS.md`, `docs/audit/OPEN_SOURCE_AUDIT_2026-05-04.md`, `doc/wiki/`
- Validation evidence (tests, checks, metrics): branch adds/updates API, functional, and frontend tests for connected resource links, DVC analytics/import paths, FAIR/ARRIVE reports, and AI provider credential handling; public CI adds a clean-clone smoke workflow.