# Key concepts

This page explains the ideas behind Metadatapp in plain language. Every term here
also appears in the [Glossary](../reference/glossary).

## The research hierarchy (ISA model)

Metadatapp organizes metadata using the widely-used **ISA** structure
(Investigation → Study → Assay), adapted for preclinical research:

```{mermaid}
flowchart TD
  I[Investigation] --> S[Study]
  S --> Sub[Subjects -- animals]
  S --> A[Assays -- procedures]
  S --> Sa[Samples]
  S --> D[Datasets]
  S --> L[Connected resource links]
```

- **Investigation** — the top-level research effort (the "project").
- **Study** — a study within an investigation. *(In the API this entity is
  `Experiment`, exposed as `/study`.)*
- **Subject** — an individual research animal (mouse, rat, zebrafish…), with
  biological attributes such as species, sex, strain, and genotype.
- **Assay** — a procedure or standard operating procedure applied in a study.
- **Sample** — a specimen (blood, tissue, plasma…) with type, status, and storage.
- **Dataset** — a data product attached to your research, with a format and a FAIR
  score.

Around this core, an animal-care domain (**Zefix**) models cages, lines, breeding
batches, mortality, environmental monitoring, and cryo-preservation — surfaced in
the relevant deployments.

## Organizations, users, and isolation (multi-tenancy)

Every piece of data belongs to an **Account** (an organization / tenant). Users
sign in through Keycloak and are scoped to their account, so **one organization
can never see another's data**. Roles control what a user can do:

- **Standard user** (`ROLE_USER`) — work with research records.
- **Administrator** (`ROLE_ADMIN`) — plus user/organization management, API keys,
  AI providers, and feature flags.
- **Super administrator** (`ROLE_SUPER_ADMIN`) — cross-account management.

See [Administration](../administration/index) for what each role can do.

## FAIR assessment

Metadatapp computes a **FAIR score (0–100)** per study from concrete metadata
signals — not a checkbox survey. The score breaks down across the four FAIR
pillars and a set of sub-criteria, each shown as pass/fail with an explanation, so
you can see exactly what to improve. You can also generate **FAIR** and **ARRIVE**
(Animal Research: Reporting of In Vivo Experiments) helper reports. See
[FAIR & exports](../using-osoma/fair).

## Metadata standards and exports

To make metadata portable, Metadatapp exports it in interoperable formats:

- **RO-Crate** — a packaged "research object" (a ZIP) suitable for archiving and
  repository submission.
- **FAIR² / JSON-LD** — linked-data metadata using shared vocabularies
  (schema.org, bioschemas, OBI, Croissant, and others) for machine discovery.
- **CSV** — plain tabular export for spreadsheets.

Exports carry **connected resource links** (provenance pointers to external
systems), so cross-system lineage travels with your metadata.

## Curation

**Curation** is the workflow of getting messy, real-world data into clean,
standardized metadata. You upload a spreadsheet; Metadatapp profiles it, proposes
how columns map to the data model, suggests value normalizations (often with help
from an LLM and ontologies), and then shows you the proposed changes. **Nothing is
written until you review and approve it.** See [Importing data](../using-osoma/importing-data).

## Connected Apps

A **Connected App** is a server-side adapter to an external research system — an
electronic lab notebook (eLabFTW), colony management (SoftMouse), a repository
(OSF, FAIR3R), cage-monitoring hardware (Tecniplast DVC), and more. You configure
each from Osoma; the API holds the credentials and does the syncing. See
[Connecting third-party apps](../connecting-apps/index).

## Governed, human-reviewed AI

Metadatapp includes an optional **AI assistant** and AI-assisted curation, built on
a deliberately constrained model:

- AI reads metadata through approved, **read-only** tools.
- It produces **structured proposals**, never direct database writes.
- A human must **approve** any change before it is persisted (fail-closed).
- Every AI action is **traced** (provider, model, prompt version, reviewer).

The assistant is **disabled by default**; an administrator enables and configures a
provider. See [AI Assistant](../using-osoma/ai-assistant) and
[AI Providers](../using-osoma/ai-providers).
```{seealso}
The conceptual ground here is documented canonically in the repository:
[`ARCHITECTURE.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/ARCHITECTURE.md),
[`FAIR.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/FAIR.md), and
[`AI-declaration.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/AI-declaration.md).
```
