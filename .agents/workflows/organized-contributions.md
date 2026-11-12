---
description: Guidelines for professional, atomic, and trackable contributions by AI agents.
---

# Organized Contributions Workflow

This file is a contribution-hygiene supplement.
For repo structure, command references, and ownership, read `AGENTS.md`.

## Contribution Rules

- Keep changes atomic: one logical change per commit or review unit.
- Use conventional commit prefixes when creating commit messages.
- Use `.github/PULL_REQUEST_TEMPLATE.md` for pull requests.
- Summaries should explain user-visible impact and verification, not just file churn.

## Verification

- Run the smallest relevant checks for the changed surface.
- When a change crosses the API and frontend boundary, include both contract verification and consumer verification.
- Use the current commands from `AGENTS.md` instead of copying shell snippets into this file.

## Documentation Rule

- Do not restate the repo map or command catalog here.
- If this workflow needs a repo-wide fact, link back to `AGENTS.md` and keep this file short.
