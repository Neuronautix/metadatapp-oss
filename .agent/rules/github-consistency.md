---
activation: glob
glob_pattern: ".github/**/*"
description: Ensures GitHub configurations and workflows stay consistent with the public CI surface
---

# GitHub Automation Consistency Rule

Read `AGENTS.md` first for the canonical repo map.

## Source Files

- CI behavior lives in `.github/workflows/ci.yml`.
- Public automation behavior lives in the workflows present under `.github/workflows/`.
- GitHub-specific AI adapters live in `.github/copilot-instructions.md` and `.github/instructions/`.

## Current Consistency Checks

- Reference the real infrastructure files under `infrastructure/docker/docker-compose*.yml`.
- Keep paths in `CODEOWNERS`, `dependabot.yml`, and workflows aligned with the current repo tree.
- Do not duplicate the full repo guide in `.github/`; point back to `AGENTS.md` for shared rules.
- Follow the actual CI pipeline instead of documenting an idealized one. This repo currently uses Castor plus targeted Node setup in CI.

## Avoid

- Referencing removed paths such as root `compose.yaml`, `docs/`, `osomapp/`, or `pwa/`.
- Adding new GitHub instruction files that restate repo-wide conventions already covered by `AGENTS.md`.
