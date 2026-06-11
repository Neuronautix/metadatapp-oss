# Evolution Report - PR #231

## Merge metadata

- Date: 2026-05-29
- PR: #231
- Title: feat: integrate deepagents-code (dcode) with pinned version and bounded CI review workflow
- Branch: copilot/integrate-deepagents-framework
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

- **`.devcontainer/post-create.sh`**: installs `uv` (Astral) if absent, then installs `deepagents-code==0.1.6` (pinned) via `uv tool install` as a developer tool — not a project dependency.
- **`.deepagents/AGENTS.md`**: new project-level dcode startup instruction file covering stack, coding conventions, testing commands, and a completion checklist.
- **`.github/agents/deepagents.agent.md`**: rewritten as a GitHub Copilot custom agent profile; unsupported `tools:` frontmatter removed; clearly marked as a Copilot profile, not a dcode config file.
- **`.github/workflows/dcode-review.yml`**: new `workflow_dispatch`-only workflow running `dcode` in bounded read-only review mode (`permissions: contents: read`, shell allow-list: `recommended,git`, configurable `max_turns` and `timeout_seconds`); never commits, pushes, or creates branches; requires `ANTHROPIC_API_KEY` CI secret.
- **`AGENTS.md`**: updated DeepAgents Code Integration section with correct commands, environment variable reference table, and accurate description of configuration file roles.
- **`scripts/deepagents-run.sh`**: updated CI entry point using `dcode -n`; adds `--shell-allow-list`, `--max-turns`, `--timeout`; accepts multi-word tasks.

## What it brings

- `dcode` can now be invoked non-interactively in CI/CD pipelines via `bash scripts/deepagents-run.sh "task"`.
- Interactive local use is available with `dcode` from any devcontainer terminal.
- A bounded, read-only review mode is available through the GitHub Actions UI (`workflow_dispatch`) without risk of unintended commits.
- Project-level conventions are injected into every dcode session via `.deepagents/AGENTS.md`, ensuring consistent behaviour across agents.

## Benefits

- User benefit: Not directly user-facing; supports the engineering team's AI-assisted development toolchain.
- Product benefit: Lowers the barrier to launching bounded code reviews and investigation tasks in CI without exposing uncontrolled write access.
- Engineering benefit: Pinned `deepagents-code==0.1.6` makes upgrades deliberate; allow-lists and turn/timeout limits bound agent resource use.
- Operational benefit: The dcode review workflow is observable via GitHub Actions logs, making AI-assisted reviews auditable.

## Long-term vision

- Strategic theme: Bounded, auditable AI-agent integration into the development workflow.
- Horizon impact: Medium to long term — as dcode capabilities grow, more CI tasks can be delegated to bounded agents.
- Future opportunities unlocked: Automated architectural reviews, regression triage, and documentation gap detection via scheduled dcode runs.

## Risks and tradeoffs

- Requires `ANTHROPIC_API_KEY` CI secret; dcode review workflow is non-functional without it.
- Shell allow-list (`recommended,git`) may need periodic review as new project tooling is added.
- Pinned version (`0.1.6`) requires a deliberate bump to adopt upstream fixes.

## Follow-up actions

- [ ] Evaluate adding a periodic scheduled dcode lint run (owner: DevOps, target: TBD)
- [ ] Document `ANTHROPIC_API_KEY` setup in the devcontainer onboarding guide (owner: docs, target: TBD)

## References

- Merge commit: 62594fd1063b3faee8e62985856650f28aa83329
