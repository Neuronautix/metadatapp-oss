# Research records

These are the core metadata records, organized along the
[ISA hierarchy](../introduction/concepts.md#the-research-hierarchy-isa-model):
Investigations contain Studies, and Studies group Subjects, Assays, Samples, and
Datasets.

Most record types share the same patterns: a **list/grid** with filters and search,
a **detail page**, **create** and **edit** forms, **delete**, and **CSV export**.
Where a record type differs, it's noted below.

## Investigations

The top-level research effort.

- **Routes:** `/investigations`, `/investigations/new`, `/investigations/:id`,
  `/investigations/:id/edit`, `/investigations/:id/animals`
- **List:** grid with a FAIR-score badge (0–100), species, schedule, assigned
  operators, animal count, and a recent-activity feed.
- **Create:** a short multi-step form — investigation details, then assign
  operators.
- **Relationships:** link operators, manage animals (`/investigations/:id/animals`),
  and view linked studies.

## Studies

A study within an investigation. *(In the API this is the `Experiment` resource,
exposed at `/study`.)*

- **Routes:** `/studies`, `/studies/:id`, `/studies/:id/edit`
- **List:** table with status (draft / running / paused / completed / failed), QC
  status, data volume, and protocol lineage. Aggregate stats show running count,
  QC-flagged count, and total data volume.
- **Filters:** status, investigation, QC status, name search. **Export:** CSV.
- **Notable:** an eLabFTW sync badge appears when the study is linked to a lab
  notebook entry.

## Subjects

An individual research animal (mouse, rat, zebrafish, etc.).

- **Routes:** `/subjects`, `/subjects/:id`, `/subjects/:id/edit`
- **List:** grid with subject ID, species, cohort, cage assignment (with room/rack
  context), consent status, and last update.
- **Filters:** species, consent, text search. **Export:** CSV.
- **Notable:** the detail page can show an **AI curation suggestion panel** for
  normalizing attributes like strain or genotype — suggestions are reviewed, never
  auto-applied (see [AI Assistant](ai-assistant)).

## Assays

A procedure / standard operating procedure.

- **Routes:** `/assays`, `/assays/:id`, `/assays/:id/edit`
- **List:** table with name, version, method tags, and review status (current /
  review-due / retired), supporting SOP review cadence.

## Samples

A specimen collected in a study.

- **Routes:** `/samples`, `/samples/:id`, `/samples/:id/edit`
- **List:** table with status (collected / processing / stored / consumed), type
  (e.g. blood, tissue, plasma, CSF), QC status, storage location, and collection
  date.
- **Notable:** supports **bulk selection** for batch operations, plus CSV export.

## Datasets

A data product attached to your research.

- **Routes:** `/datasets`, `/datasets/:id`, `/datasets/:id/edit`
- **List:** table with status (draft / published / restricted), format (e.g.
  FASTQ, CSV, HDF5, JSON-LD), a FAIR-score badge, and size.
- **Notable:** dataset *creation* is intentionally disabled in the registry;
  datasets arrive via studies/imports. Editing, viewing, filtering, and CSV export
  are available.

## Creating records via the Metadata Catalog

Several "New …" buttons route through the **Metadata Catalog** (`/metadata`), a
generic browser over every resource the platform exposes (Investigations, Studies,
Subjects, Assays, Datasets, and more). From there you can list, create
(`/metadata/:resource/new`), view, and edit (`/metadata/:resource/:id/edit`) any
resource type with a consistent form. The catalog is gated by the
`metadatapp-feat.enabled` feature flag. See [FAIR & exports](fair) for how the
catalog relates to exports.
