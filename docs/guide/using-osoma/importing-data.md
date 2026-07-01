# Importing data

Metadatapp offers two ways to bring spreadsheet data in: a quick **CSV wizard** for
simple subject lists, and a full **AI-assisted curation workflow** for messy,
real-world data that needs mapping and cleanup. Both are feature-gated.

## Quick CSV import

A lightweight wizard for mapping a spreadsheet of subjects into the registry.

- **Route:** `/import/csv` — gated by `import.enabled`.
- **Flow:**
  1. **Upload** a CSV (drag-and-drop or pick a file).
  2. **Map columns** — headers are auto-detected; the wizard guesses the subject-ID
     column and validates IDs against a pattern.
  3. **Look up subject IDs** against the live registry; matches are grouped by
     cohort, and unknown IDs are listed separately.
  4. **Confirm** — review the summary (record count, target cohort), then commit.

Use this when you already have clean subject identifiers and just need to attach
them to a cohort.

## AI-assisted curation (Smart Import Hub)

For richer imports, the curation workflow profiles your file, proposes how it maps
to the data model, suggests value normalizations, and shows you every change
**before** anything is written.

- **Entry point:** **Import & Curation Hub** (`/smart-import`).
- **Workflow routes:** `/curation/import`, `/curation/mapping`,
  `/curation/validation`, `/curation/resolution`, `/curation/patch-review`,
  `/curation/graph` — gated by `feature.curationWorkflow.enabled`.

A visual stepper walks you through:

1. **Upload** — ingest a CSV/Excel file (an *import session* is created).
2. **Map** — the system (with LLM help) proposes column-to-entity mappings.
3. **Validate** — data-quality checks run.
4. **Resolve** — identity resolution links and de-duplicates records.
5. **Review** — you see the computed **patches** (subject-level changes) and choose
   to apply them: *"Commit N mutations."*
6. **Graph** — a knowledge-graph view of the resulting relationships.

```{admonition} Review-first, always
:class: important
The curation workflow is **review-first**: AI and rules generate *proposals*, but
no canonical data changes until you approve the patches at the Review step. Every
proposal records its source, rationale, and (for AI suggestions) confidence and
the reviewer who accepted it. See the
[governed-AI concept](../introduction/concepts.md#governed-human-reviewed-ai).
```

## Which should I use?

| Situation | Use |
| --- | --- |
| A clean list of known subject IDs to attach to a cohort | [Quick CSV import](#quick-csv-import) |
| A spreadsheet that needs column mapping, cleanup, or normalization | [AI-assisted curation](#ai-assisted-curation-smart-import-hub) |

After importing, head to [FAIR & exports](fair) to assess and export the result.
