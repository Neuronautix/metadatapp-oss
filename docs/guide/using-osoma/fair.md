# FAIR & exports

Metadatapp helps you measure how FAIR your metadata is and export it in
interoperable formats. For the concepts behind FAIR, RO-Crate, and JSON-LD, see
[Key concepts](../introduction/concepts.md#fair-assessment).

## FAIR scoring

Each study carries a **FAIR score (0–100)**, computed from real metadata signals
rather than a checklist you fill in.

- A **FAIR score badge** appears on investigation and dataset lists.
- A **FAIR panel** on the study/investigation detail page breaks the score down by
  the four pillars (Findable, Accessible, Interoperable, Reusable) and shows each
  sub-criterion as pass/fail with an explanation — so you know exactly what to fix.
- You can also ask the [AI Assistant](ai-assistant) for a study's FAIR assessment.

Some deployments expose extra FAIR views (e.g. a **FAIR Navigator** and a
welfare-oriented **WellFAIR** view that ties FAIR compliance to the 3Rs principle).
These are feature-gated and may not appear on every instance.

## Metadata Catalog

The **Metadata Catalog** (`/metadata`, gated by `metadatapp-feat.enabled`) is a
single browser over every metadata collection the platform exposes. Use it to find
records across types, see which collections support list/create/detail/edit, and
jump straight into any resource grid. It's the most direct way to navigate the full
data model.

## Exports

Open **Export** (`/export`) to produce shareable, archival, or machine-readable
outputs. Select an investigation (and optionally a study within it), then choose a
format:

| Format | What it is | Good for |
| --- | --- | --- |
| **RO-Crate** (`.zip`) | A packaged research object with a standard metadata descriptor | Archiving, repository submission |
| **FAIR² / JSON-LD** | Linked-data metadata using shared vocabularies | Machine discovery, interoperability |
| **ELN** (`.eln`) | eNotebook exchange format | Moving between notebook systems |
| **CSV** | Tabular animal/record data | Spreadsheets, quick sharing |
| **FAIR report (PDF)** | A FAIR assessment summary | Sharing FAIR status with collaborators |
| **ARRIVE report (PDF)** | An ARRIVE reporting helper checklist | Animal-study transparency reporting |

Exports embed **connected resource links** (pointers to external systems the study
is linked to), so provenance travels with the metadata. See
[Connecting third-party apps](../connecting-apps/index) for how those links are
created.

```{note}
The JSON-LD/RO-Crate exporters declare rich ontology namespaces. In the current
public preview, some fields (certain procedures, measurement units, and
genotype/sex values) are not yet fully mapped to ontology IRIs — see the
[architecture notes](https://github.com/Neuronautix/metadatapp-oss/blob/main/ARCHITECTURE.md)
for the current state.
```
