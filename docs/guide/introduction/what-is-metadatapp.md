# What is Metadatapp?

Metadatapp is an open-source platform for **managing research metadata** — the
structured description of *what* an experiment is, *who* and *what* it involved,
*how* it was done, and *where* the resulting data lives. It is built for
biomedical and preclinical (animal) research, where good metadata is essential for
reproducibility, animal-welfare reporting, and data reuse.

The project is developed and maintained by [Neuronautix](https://www.neuronautix.com)
and is released under the AGPL-3.0-or-later license.

## Why metadata matters

Raw data files (spreadsheets, images, sequencing output) are only useful if you
can later answer: *Which study is this from? Which animals? Under what protocol?
Who can reuse it, and how?* Metadatapp captures those answers in a structured,
machine-readable way so your data becomes **FAIR**:

- **F**indable — rich, identifier-bearing descriptions.
- **A**ccessible — retrievable through documented APIs.
- **I**nteroperable — uses shared vocabularies and standards.
- **R**eusable — carries clear license and provenance.

See [Key concepts](concepts) for how FAIR is modelled in the app.

## What you can do with it

- **Capture and edit** structured metadata for investigations, studies, subjects
  (animals), samples, assays/procedures, and datasets.
- **Assess FAIR compliance** per study, with a scored, criterion-by-criterion
  breakdown, and generate FAIR and ARRIVE helper reports.
- **Import data** from spreadsheets, with an AI-assisted curation workflow that
  proposes corrections you review before anything is committed.
- **Connect external lab systems** (electronic lab notebooks, colony management,
  repositories) so their records flow into your metadata — see
  [Connecting third-party apps](../connecting-apps/index).
- **Export** to interoperable standards: RO-Crate, FAIR²/JSON-LD, and CSV.
- **Use a governed AI assistant** for read-only questions and metadata
  suggestions — always human-reviewed before any change.

## Who it's for

| You are… | Start here |
| --- | --- |
| A researcher capturing/curating metadata | [Getting started](../getting-started) → [Using Osoma](../using-osoma/index) |
| Someone connecting external tools | [Connecting third-party apps](../connecting-apps/index) |
| An administrator of an instance | [Administration](../administration/index) |
| An operator deploying an instance | [Self-hosting & configuration](../self-hosting/index) |
| A developer extending the code | [For developers](../for-developers/index) |

## The technology, in brief

Metadatapp is a **Symfony / API Platform** backend (PHP) with a **React / Vite**
frontend called **Osoma**, authenticated through **Keycloak** (OIDC), and run as a
**Docker Compose** stack. You don't need to know any of this to use the app — but
if you're deploying or extending it, the [architecture overview](../for-developers/index)
goes deeper.
