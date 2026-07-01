# Evolution Report - PR #284

## Merge metadata

- **Date:** 2026-06-08  
- **PR:** #284  
- **Title:** fix(ci): route evolution reports through pull requests  
- **Branch:** dax/fix-evolution-report-pr-flow  
- **Contributors:** @dhuzard  
- **Reviewer(s):** None explicitly listed  

## What was merged

This PR reworked the process for generating and integrating evolution reports by modifying the CI workflow in `.github/workflows/evolution-report.yml`. Key changes include:

- Adjusted GitHub Action permissions to grant `pull-requests: write` for creating pull requests directly from automation.
- Updated the evolution report workflow to create a dedicated branch for evolution report updates rather than committing changes directly to the `main` branch.
- Introduced logic to automatically open a new pull request for evolution reports whenever a PR is merged. If auto-creation of the pull request fails, the workflow provides a manual fallback with instructions.

## What it brings

This change introduces the following capabilities:

- Automates the creation and tracking of evolution report changes through pull requests, improving traceability and enabling peer review.
- Reduces the risk of direct commits to the `main` branch by routing all automated changes through pull requests.
- Provides a fallback mechanism in case GitHub Actions encounters issues with creating a PR, ensuring no changes are lost.

## Benefits

- **User benefit:** None — this change does not directly impact end-users.  
- **Product benefit:** None directly applicable, as this change is related to internal processes.
- **Engineering benefit:**  
  - Improves process transparency and change traceability by handling evolution report updates through pull requests.  
  - Enhances code review practices for CI-generated changes, reducing unexpected automation-related errors.
- **Operational benefit:** Eliminates direct CI pushes to `main`, offering better control over deployments and change management.

## Long-term vision

This change supports the organization’s vision of maintaining robust and auditable CI/CD workflows by ensuring that automated changes align with collaborative development practices.

- **Strategic theme:** Increase reliability, traceability, and auditability of automation workflows.  
- **Horizon impact:** Medium-term — the change strengthens development workflows and sets the groundwork for scaling automated report creation with minimal risks.  
- **Future opportunities unlocked:** Potential to expand automated workflows into other areas using similar rerouting mechanics, such as release management or documentation updates.

## Risks and tradeoffs

- **Risk:** Increased runtime for the evolution report CI job due to new branching and pull request creation steps.  
- **Tradeoff:** While the change may slightly increase CI execution times, its benefits, such as better traceability and code review practices, outweigh the downsides.  

## Follow-up actions

- [ ] Monitor the new workflow to ensure pull requests for evolution reports are consistently created. (Owner: @dhuzard, Target: 2026-06-15)  
- [ ] Evaluate whether this mechanism could be extended to manage other CI-generated changes, such as documentation updates. (Owner: @dhuzard, Target: 2026-07-01)  

## References

- **Ticket(s):** None explicitly mentioned.  
- **Related docs:** `.github/workflows/evolution-report.yml` (workflow updates)  
- **Validation evidence:**  
  - `castor qa:all` and `castor qa:agent-docs` passed according to PR body.  
  - Manual testing verified the branch and pull request creation for evolution reports operates as expected.  