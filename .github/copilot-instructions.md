# Metadatapp Copilot Adapter

Start with `AGENTS.md`.
That file is the canonical source of truth for repo structure, commands, ownership, and agent-specific constraints.

## Folder-specific adapters

- `api/**/*` -> `.github/instructions/backend.instructions.md`
- `osoma/**/*` -> `.github/instructions/frontend-osoma.instructions.md`

## Local reminders

- Prefer `castor` for stack and QA commands.
- Keep repo-wide facts in `AGENTS.md`; do not duplicate them here.
- If a GitHub-specific instruction conflicts with `AGENTS.md`, update the GitHub file and follow `AGENTS.md`.

## Agent context sources (GitHub sessions)

When a GitHub Copilot agent session starts in this repo, use these repository files as the sub-agent context stack:

- `AGENTS.md` (canonical ownership and execution model)
- `.github/agents/*.agent.md` (invocable team roles and handoffs)
- `.agent/rules/*.md` and `.agent/workflows/*.md` (workspace rules and playbooks)
- `.agents/workflows/organized-contributions.md` (contribution hygiene workflow)
- `CLAUDE.md` (`.claude` companion pointer back to `AGENTS.md`)
- `.deepagents/AGENTS.md` for dcode-specific tasks

There is currently no `.codex/` directory in this repository; if one is added later, keep it aligned with `AGENTS.md` and this adapter model.
