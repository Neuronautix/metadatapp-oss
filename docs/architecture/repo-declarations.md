# Repository Declarations — Strategy & Pipeline

This repository publishes a small set of **declarations**: files that state, in
both machine-readable and human-readable form, how the project handles data, AI,
AI knowledge management, and design. They are generated automatically from
evidence found in the repository so they stay accurate as the codebase evolves.

## What gets declared

| File | Emitted when… | Standard it follows |
| --- | --- | --- |
| `AI-declaration.md` | the repo uses AI (agents, prompts, models, MCP) | AI transparency / usage disclosure |
| `FAIR.md` | the repo manages or describes research **data** | [FAIR principles](https://www.go-fair.org/fair-principles/) |
| `TRUST.md` | the repo does AI **knowledge management** (MCP/RAG, prompt & eval corpora, governed knowledge base) | [TRUST principles](https://doi.org/10.1038/sdata.2020.33) |
| `design.md` | the repo ships a frontend / design system | design tokens (hand-authored here) |
| `CITATION.cff` | the repo is citable software | [Citation File Format](https://citation-file-format.github.io/) |
| `codemeta.json` | the repo is software with FAIR metadata | [CodeMeta 2.0](https://codemeta.github.io/) (JSON-LD) |
| `.well-known/repo-declarations.json` | always | machine index of all declarations |
| `llms.txt` | always | [llms.txt](https://llmstxt.org/) discovery convention |

`CITATION.cff` and `codemeta.json` are pure machine-readable metadata (no
narrative body); they are populated from `project` and `authors` in
`.declarations.yml` and can be disabled via `metadata.citation_cff` /
`metadata.codemeta`.

Each declaration is a single file with:

1. **YAML front matter** — the machine-readable payload: which principle is
   supported, the evidence paths that back each claim, a `content_sha256`, and
   provenance (`generator`, `spec_version`).
2. **Markdown body** — the human-readable narrative, with every claim linked to
   real files in the repo.

The `.well-known/repo-declarations.json` index lets other tools and agents
discover every active declaration, its trigger condition, and its content hash
in one fetch.

## How the pipeline works

The generator lives at [`scripts/generate-declarations.py`](../../scripts/generate-declarations.py).

```
                 ┌──────────────────────┐
  repo files ───▶│  signal detection    │  globs/paths that exist
                 └──────────┬───────────┘
                            ▼
                 ┌──────────────────────┐
 .declarations.yml ───────▶│  declaration registry │  trigger? render?
                 └──────────┬───────────┘
                            ▼
        ┌───────────────────────────────────────────┐
        │ render front matter (evidence) + narrative  │
        │ → FAIR.md / TRUST.md / AI-declaration.md     │
        │ → .well-known/repo-declarations.json         │
        │ → llms.txt                                   │
        └───────────────────────────────────────────┘
```

1. **Detect signals.** For each declaration, a list of candidate paths/globs is
   resolved to the subset that actually exists. That subset is the *evidence*.
2. **Decide which apply.** A declaration is emitted when it has evidence (or is
   forced on/off in `.declarations.yml`).
3. **Render.** Front matter is built from the evidence; the narrative explains
   it. Facts that can't be inferred from code (people, identifiers, policy
   choices) come from `.declarations.yml`.
4. **Write or check.** `--check` regenerates everything in memory and fails if
   the committed files differ; the default mode writes the files.

### Evidence-derived prose (capabilities, not hand-written text)

FAIR.md and TRUST.md are **composed from detected capabilities**, never from
hand-written per-repo prose. Each principle dimension has:

- a **framework definition** — the standard's wording (e.g. what "Interoperable"
  means), owned centrally in the generator and pinned by `vocabulary_version`;
- a list of **capabilities** — `{id, label, rationale, signals}`. A capability
  is claimed *only* when at least one of its `signals` exists. So a repo that
  uses RO-Crate names "RO-Crate packaging" because the file is there; a repo
  that drops it stops claiming it automatically — **no evidence, no claim.**

The renderer prints, per principle, the labels of whatever capabilities were
detected with their evidence links. This keeps declarations honest by
construction and lets the *same* generator produce accurate prose in any repo
without authoring text per repository. `.declarations.yml` may attach optional
`capability_notes` to detected capabilities, but cannot fabricate a capability
without evidence. See [ADR 0001](decision-records/0001-evidence-derived-declarations.md).

### Determinism

Generated artifacts contain **no timestamps**, so output is byte-stable across
runs. Provenance is captured as generator name, `spec_version`, the evidence
list, and a `content_sha256` over the body. This is what makes the CI drift
check reliable.

### Non-destructive by design

`design.md` is hand-authored design tokens. It is marked `managed: false` in
`.declarations.yml`, so the generator **discovers and indexes** it but never
overwrites the body. Any declaration can be made human-owned the same way.

## Running it

```bash
# Regenerate all declarations
castor declarations            # alias for declarations:generate
python3 scripts/generate-declarations.py

# CI mode: fail if anything is out of date
castor declarations --check
python3 scripts/generate-declarations.py --check

# Show what was detected and why
python3 scripts/generate-declarations.py --list
```

The [`.github/workflows/declarations.yml`](../../.github/workflows/declarations.yml)
workflow runs `--check` on pull requests and auto-commits any drift on pushes to
`main`.

## Configuration (`.declarations.yml`)

The config holds the facts the scanner can't infer and per-declaration
overrides:

- `project` — name, description, homepage, repository, license, keywords.
- `authors` — for citation/credit.
- `ai` — product vs. development AI use, guardrails, oversight, training stance.
- `declarations.<key>.managed` — set `false` to make a file human-owned.
- `declarations.<key>.force` — force a declaration on (`true`) or off (`false`).

## Generic core vs. per-repo extension

The generator ships a **generic core** that matches cross-ecosystem conventions
(`CITATION.cff`, `LICENSE`, `prompts/`, `evals/`, `ro-crate-metadata.json`,
`**/*.jsonld`, …). Anything specific to *this* repo — RO-Crate templates, MCP
wiki pages, a particular AI config path — lives in `.declarations.yml`, not in
the generator. This is what keeps the generator repo-agnostic and ready to
extract into a shared tool later (see the multi-repo section above).

Two extension points, both in `.declarations.yml`, no code changes:

- **Trigger signals** (`declarations.<key>.signals`) — extra paths that decide
  whether a declaration file is emitted and enrich its top-level evidence.
- **Capabilities** (`capabilities.<framework>.<dimension>`) — extra FAIR/TRUST
  capabilities. An entry reusing a core `id` extends that capability's signals;
  a new `id` adds a capability. `merge_capabilities()` does the merge.

```yaml
# .declarations.yml
declarations:
  fair:
    signals: [scripts/assemble-ro-crate.py]   # also triggers FAIR.md
capabilities:
  fair:
    findable:
      - id: datacite-doi
        label: DataCite DOIs
        rationale: globally resolvable identifiers
        signals: ["**/*.datacite.xml", ".zenodo.json"]
```

## Extending it (in code)

Add a *generic* capability to the relevant dimension in `FAIR_CAPABILITIES` /
`TRUST_CAPABILITIES` only when it is universal enough to apply to every repo;
otherwise prefer the `.declarations.yml` route above.

To add a whole new declaration, register a `Declaration` in `build_registry()`:

```python
Declaration(
    key="security",
    filename="SECURITY-POSTURE.md",
    title="Security Posture",
    applies_when="repository declares a security posture",
    signals=["SECURITY.md", ".github/workflows/codeql.yml"],
    render=render_security,   # (ctx, evidence) -> (frontmatter_extra, body)
)
```

### Roadmap — other relevant declarations

These are well-established machine-readable declarations that fit this project
and can be added the same way:

- **SBOM** (`CycloneDX`/`SPDX`) — software bill of materials for supply-chain
  transparency.
- **`.well-known/security.txt`** — security contact disclosure
  ([RFC 9116](https://www.rfc-editor.org/rfc/rfc9116)).

## Running across multiple repositories

The generator is **portable by design**: detection resolves a list of candidate
paths to the subset that exists, so paths that don't exist in a given repo
simply contribute no evidence and their declaration is skipped. Repo-specific
facts live entirely in `.declarations.yml`. Three ways to run it across repos,
from simplest to most centralized:

### 1. Vendor the script (simplest)

Copy `scripts/generate-declarations.py`, `.declarations.yml`, and
`.github/workflows/declarations.yml` into each repo and edit `.declarations.yml`
for that project. Pros: zero infrastructure, each repo is self-contained. Cons:
updates to the generator must be re-copied.

### 2. Reusable GitHub Actions workflow (recommended)

Host the generator in one repo and expose a `workflow_call` workflow. Other
repos call it in a one-line workflow:

```yaml
# .github/workflows/declarations.yml in each consumer repo
name: Repo Declarations
on: [pull_request, push]
jobs:
  declarations:
    uses: Neuronautix/metadatapp/.github/workflows/declarations-reusable.yml@main
```

The reusable workflow checks out the caller, fetches the generator, and runs
`--check` on PRs / refresh on `main`. Each repo keeps its own `.declarations.yml`.
Pros: single source of truth for the generator logic; bump once, all repos
benefit. Cons: requires the central repo to be readable from the others.

### 3. Distributable CLI

Package the generator (e.g. `pipx install repo-declarations` or a `curl`-able
single file) and invoke `repo-declarations --check` in any CI. This decouples it
from GitHub entirely and works in GitLab/Bitbucket/local pre-commit hooks too.

> For purely repo-specific narrative (e.g. the RO-Crate / MCP wording in the
> FAIR/TRUST bodies), move project-specific phrasing into `.declarations.yml`
> fields rather than hard-coding it in renderers, so the same generator produces
> accurate prose in every repo. The signal lists already degrade gracefully.
