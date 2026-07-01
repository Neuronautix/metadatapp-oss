# App Evolution Documentation

This folder is the home for tracking how the app evolves over time.

## Goal

Create a simple, consistent reporting habit after each merge so we can answer:

- What changed?
- What value did it bring now?
- What long-term direction does it support?

## Expected workflow

1. After a PR is merged into `main`, the `evolution-report` GitHub workflow generates (or reuses) the report in `doc/evolution/`.
2. The same workflow runs wiki ingest from that report, following `doc/wiki/SCHEMA.md`.
3. The workflow commits the report and wiki updates **directly to `main`** — no separate documentation PR is opened.
4. Keep reports and wiki updates concise, factual, and outcome-oriented.
5. Link related tickets, specs, or follow-up work.

**Infinite-loop prevention**: The workflow skips PRs whose head branch starts with `evolution-report/`, ensuring that no documentation commit triggers a recursive documentation run.

**Wiki lint**: A separate `wiki-lint` workflow runs on a weekly schedule and appends a lint entry to `doc/wiki/LOG.md`, checking for orphan pages, stale dates, and coverage drift.

## Naming convention

Use one file per merged PR, with this format:

`YYYY-MM-DD-pr-<number>-<short-slug>.md`

Example:

`2026-04-09-pr-123-api-filtering-improvements.md`

## Required sections (for every report)

- Merge metadata (PR, branch, contributors, date)
- What was merged
- What it brings
- Benefits
- Long-term vision
- Risks / tradeoffs
- Follow-up actions

## Scope

This documentation is intended for all contributors:

- Human contributors
- AI agents
- Reviewers and maintainers

Every merged change should have an evolution report.
