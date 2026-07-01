---
  title: "Feature: CI Workflows Improvement"
  type: feature
  updated: 2026-06-08
  source_prs: [284]
  related: [areas/ci.md]
  ---

  # Feature: CI Workflows Improvement

  ## Status
  Active — Automated PR-based evolution report workflow implemented

  ## Summary
  This feature improves the CI workflow for handling evolution reports. It ensures enhanced traceability, collaboration, and auditability by automating the generation, branching, and pull request creation for evolution report updates. This replaces the previous mechanism, which directly committed changes to the `main` branch.

  ## Key PRs (chronological)

  | PR    | Date       | What changed |
  |-------|------------|--------------|
  | #284  | 2026-06-08 | Switched evolution report updates to use automated pull requests |

  ## Architecture
  - Adjusted `.github/workflows/evolution-report.yml` workflow to enable `pull-requests: write` permission.
  - Added logic to create a dedicated branch when generating evolution report updates.
  - Automatically creates a pull request for review after a report is generated. A manual fallback mechanism is in place if the automated PR fails.

  ## Current capabilities
  - Automatically routes all evolution report updates through dedicated branches and pull requests for enhanced traceability.
  - Disables direct commits to the `main` branch by the CI.
  - Provides an automated fallback mechanism for manual action if PR creation fails.

  ## Known limitations & tech debt
  - **Risk:** Increased runtime for the evolution report CI job due to additional branching and pull request creation steps.
  - **Resolution path:** Monitor the new workflow's performance and ensure it consistently produces the expected outcomes (Target: 2026-06-15).

  ## Future opportunities
  - Expand this mechanism to manage other CI-generated changes, such as documentation updates or release management workflows (Target: 2026-07-01).

  ## Related
  - [areas/ci.md](../areas/ci.md) — details on CI/CD stack and workflows.
