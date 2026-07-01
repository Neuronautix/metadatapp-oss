# Wiki Schema

This file defines the structure, conventions, and workflows for the `doc/wiki/` knowledge base.
It is authoritative: when in doubt, follow this file.

## Purpose

The wiki is a **compiled, persistent knowledge base** built from the raw evolution reports in
`doc/evolution/`. It accumulates and synthesizes knowledge so that Claude (and human readers)
do not need to re-derive it from 70+ individual reports on every session.

**Raw sources** (`doc/evolution/`) are immutable — one file per merged PR, never edited after creation.
**The wiki** (`doc/wiki/`) is owned entirely by Claude — created, updated, cross-referenced, and linted
by Claude. Humans read it; Claude writes it.

---

## Directory layout

```
doc/wiki/
  SCHEMA.md          ← this file — the rules
  INDEX.md           ← catalog of all wiki pages (read first on any query)
  LOG.md             ← append-only chronological record of wiki operations
  overview.md        ← high-level synthesis of the project's state and direction
  tech-debt.md       ← consolidated known debt, regressions, and deferred work
  features/          ← one page per product/technical feature area
  decisions/         ← ADR-lite: architectural decisions extracted from reports
  areas/             ← cross-cutting summaries by stack layer
    backend.md
    frontend.md
    ci.md
```

---

## Operations

### Ingest (after a new evolution report is written)

Triggered when the user says "ingest the new report" or when a new file appears in `doc/evolution/`.

1. Read the new report in `doc/evolution/`.
2. Extract: area, type, impact, concrete changes, capabilities unlocked, risks/debt introduced.
3. Update or create the relevant feature page(s) in `doc/wiki/features/`.
4. Update the relevant area page(s) in `doc/wiki/areas/`.
5. If an architectural decision is established or changed, update `doc/wiki/decisions/`.
6. Update `doc/wiki/tech-debt.md` if new debt is introduced or old debt is resolved.
7. Update `doc/wiki/overview.md` if the project's direction or capabilities change significantly.
8. Add the new page(s) to `doc/wiki/INDEX.md` if created.
9. Append a log entry to `doc/wiki/LOG.md`.

A single ingest may touch 3–8 wiki pages. That is expected and correct.

### Query

Triggered when the user asks a question about the codebase, history, or project.

1. Read `INDEX.md` to identify relevant pages.
2. Read the relevant pages.
3. Synthesize an answer with citations to wiki pages and, where useful, to raw reports.
4. If the answer produces a non-trivial synthesis (a comparison, an analysis, a decision), file it
   as a new wiki page and add it to `INDEX.md`.

### Lint

Triggered when the user says "lint the wiki" or periodically.

Check for:
- Pages with no inbound links (orphans)
- Claims contradicted by newer reports
- Resolved tech debt still listed as open
- Feature areas that have grown but whose wiki page was not updated
- Missing cross-references between related feature pages
- Boilerplate text ("Delivers the capability described in the PR title") in any wiki page

---

## Page conventions

### Frontmatter

Every wiki page (except SCHEMA.md, INDEX.md, LOG.md) must have YAML frontmatter:

```yaml
---
title: <page title>
type: overview | feature | decision | area | analysis
updated: YYYY-MM-DD
source_prs: [123, 124, ...]   # PRs whose data contributed to this page
related: [other-page.md, ...]
---
```

### Feature pages (`features/`)

Structure:
```markdown
# Feature: <name>

## Status
Active | Stable | Deprecated | In Progress

## Summary
One paragraph. What this feature is and what it enables.

## Key PRs (chronological)
| PR | Date | What changed |
|----|------|--------------|

## Architecture
How this feature works technically.

## Current capabilities
What users/developers can do today.

## Known limitations & tech debt
Specific, actionable items with PR references.

## Future opportunities
What this feature unlocks next.

## Related
Links to other wiki pages.
```

### Decision pages (`decisions/`)

Structure:
```markdown
# Decision: <short title>

## Status
Active | Superseded | Under review

## Context
Why this decision was needed.

## Decision
What was decided.

## Established by
PR(s) that established or confirmed this pattern.

## Consequences
What this enables and what it constrains.

## Alternatives considered (if known)
```

### Area pages (`areas/`)

Structure:
```markdown
# Area: <Backend | Frontend | CI>

## Stack
Current tech stack for this area.

## Key conventions
Patterns and rules that apply here.

## Recent significant changes
Last 5–10 notable changes with PR references.

## Active features
Links to feature pages.

## Known debt
Debt specific to this area.
```

---

## INDEX.md format

```markdown
# Wiki Index

_Last updated: YYYY-MM-DD_

## Overview
- [overview.md](overview.md) — Project synthesis and current state
- [tech-debt.md](tech-debt.md) — All known debt and deferred work

## Features
- [features/fair-checking.md](features/fair-checking.md) — FAIR assessment, PDF reports, AI integration
...

## Decisions
- [decisions/backend-as-source-of-truth.md](...) — All external API access via backend proxy
...

## Areas
- [areas/backend.md](areas/backend.md) — PHP/Symfony/API Platform layer
...
```

---

## LOG.md format

Entries are prepended (newest first). Each entry starts with a consistent prefix for grepping:

```markdown
## [YYYY-MM-DD] <operation> | <subject>

- Operation: ingest | query | lint | manual
- Source: PR #NNN | user question | scheduled
- Pages touched: page1.md, page2.md
- Notes: ...
```

---

## What NOT to put in the wiki

- Code snippets that are better read from source
- Git history (use `git log`)
- Information already in `AGENTS.md` or `CLAUDE.md`
- Speculation without PR evidence
- Boilerplate copied from reports
