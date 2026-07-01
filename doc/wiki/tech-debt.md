---
title: Tech Debt & Known Limitations
type: overview
updated: 2026-06-09
source_prs: [149, 186, 196, 199, 202, 204, 205, 207, 209, 210, 213, 244]
related: [features/fair-checking.md, features/ai-mcp.md, features/zefix.md, areas/ci.md]
---

# Tech Debt & Known Limitations

Consolidated from all evolution reports. Items are removed from this page when resolved (with a log entry).

---

## Critical / High Priority

### MCP Tool Authorization Escalation Risk
**Source:** PR #199
**Description:** The MCP bridge's read-only constraint is enforced by convention, not by a policy layer. As new tools are added, each addition requires active discipline to maintain the read-only boundary.
**Risk:** AI-driven flows could inadvertently trigger state mutations if a future tool bypasses the constraint.
**Owner:** MCP/AI maintainers
**Resolution path:** Add an explicit authorization layer or decorator that enforces read-only at the bridge level, not per-tool.

### FAIR Scoring Interpretation & Domain Calibration
**Source:** PRs #209, #210
**Description:** FAIR scoring algorithms produce outputs that non-technical stakeholders may misinterpret. No guidance or thresholds documented for what constitutes an "acceptable" FAIR score.
**Risk:** Reports may be used without understanding the scoring criteria, leading to incorrect conclusions.
**Resolution path:** Document scoring criteria per FAIR principle; add threshold guidance to the report UI or PDF.

### Large-Scope PR Regression Risk (PR #210)
**Source:** PR #210
**Description:** PR #210 combined FAIR assessment, AI assistant integration, and the full curation workflow (import sessions, proposals, patches, review) into a single merge. Cross-boundary regression coverage is incomplete.
**Risk:** Interactions between FAIR reporting, import session persistence, and AI tool dispatch may be untested.
**Resolution path:** Add integration tests that cover the full ingest → FAIR assess → report flow end-to-end.

### Managed Secret Store Adapter Missing
**Source:** PR #244
**Description:** `App\Secrets\SecretStoreInterface` decouples credential consumers from storage, but the shipped implementation is still the default sodium-encrypted database payload. No Vault / AWS Secrets Manager / GCP Secret Manager / Azure Key Vault adapter is included yet.
**Risk:** Production deployments must either accept the database-column default or implement a custom adapter before adopting the user-scoped Connected Apps and LLM credential flows.
**Resolution path:** Ship at least one managed secret-store adapter, document service overrides for production, and provide a migration path for existing encrypted records.

---

## Medium Priority

### E2E Test Selector Brittleness
**Source:** PR #205
**Description:** E2E tests rely on selectors and assertions tied to UI copy and layout. Changes to labels or component structure can silently break tests.
**Risk:** False negatives in CI; regressions missed when tests fail for selector reasons rather than logic reasons.
**Resolution path:** Move to `data-testid` attributes for all test-critical elements; enforce in code review.

### Dark Mode Accessibility Gap
**Source:** PR #207
**Description:** Dark mode color adjustments were made globally but a full WCAG contrast pass for edge components was not done.
**Risk:** Some UI elements may have insufficient contrast in dark mode, affecting accessibility compliance.
**Resolution path:** Run automated contrast checker (e.g., axe-core) across all pages in dark mode.

### Route Encoding Edge Cases
**Source:** PR #207
**Description:** Routing fixes addressed common cases; behavior with encoded identifiers (special characters in dataset IDs) not fully tested.
**Risk:** Navigation failures for datasets with non-ASCII or URL-encoded identifiers.
**Resolution path:** Add E2E tests with encoded identifiers; verify router handles percent-encoding correctly.

### CI Step Ordering Implicit Dependency
**Source:** PR #213
**Description:** The optimized CI workflow relies on `castor start` always building images before starting services. This is a verified behavior but undocumented as a contract.
**Risk:** If `castor start` behavior changes, the workflow may silently skip builds.
**Resolution path:** Add a comment in `.github/workflows/ci.yml` documenting the dependency; consider adding an explicit build step as a safety check.

### Zefix Edge-Case Coverage
**Source:** PR #205
**Description:** E2E coverage is concentrated on core Zefix flows (location explorer, line batches, mortality). Edge cases (partial data, concurrent updates, permission denied scenarios) are not tested.
**Risk:** Silent regressions in edge workflows.
**Resolution path:** Expand E2E matrix for role-denied access, empty batch states, and import failure recovery.

### Import Session Negative-Path Coverage
**Source:** PR #205
**Description:** Import session tests cover the happy path; authorization-denied and invalid-payload scenarios are not validated.
**Risk:** Silent failures or incorrect error responses for malformed imports.
**Resolution path:** Add PHPUnit tests for invalid payload and unauthorized access to `SessionImport` endpoints.

### elabFTW API Coupling
**Source:** PR #115, #202
**Description:** The elabFTW HTTP client response parsing is sensitive to API evolution. Mock-driven tests cover current response shapes; real API changes could cause silent breakage.
**Risk:** Silent failures after elabFTW API updates.
**Resolution path:** Add contract tests against the real elabFTW API in a controlled environment; monitor API changelog.

---

## Deferred / Not Yet Started

| Item | Source PRs | Description |
|------|-----------|-------------|
| FAIR Report Policy Layer | #209, #210 | No workflow gating based on score thresholds (e.g., blocking publication until score ≥ threshold) |
| Scheduled FAIR Exports | #210 | Mentioned as future opportunity; not yet implemented |
| Multi-Provider LLM Orchestration | #151, #197 | Abstracted curation provider interface exists; orchestration logic for multiple simultaneous providers not visible |
| Open-source release blockers | #244 | Full-history secret remediation and third-party publication sign-off remain manual blockers before any public release cut |
| Sensor Agent Production Hardening | #186, #196 | Live sensor demo works; failover, reconnection, and alarm escalation not production-hardened |
| Zefix Mortality Data Completeness | #182 | Persistence model added; unknown if all mortality tracking fields are covered per Zefix spec |
| CRYO Line Tracking Edge Cases | #204 | UI aligned with API; cold-chain tracking edge cases (partial thaw, multiple straws) unknown |
| Dependabot Replacement Strategy | #107 | Dependabot removed but no automated replacement; dependency freshness now entirely manual |

---

## Resolved (recent)

| Item | Resolved by | Date |
|------|------------|------|
| MCP validation regressions (Mercure + IMPC schema) | PR #149 | 2026-03-24 |
| CI redundant Docker image builds | PR #213 | 2026-04-09 |
| Osoma/PWA dual frontend complexity | PR #96 | 2026-03-17 |
