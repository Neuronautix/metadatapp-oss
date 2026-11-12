# HCM Minimal Metadata Form

> Canonical source of truth: `api/resources/hcm/hcm-minimal-metadata-form.json`.
> That file is the machine-readable definition consumed by the seed command; this
> page is the human-facing guide. Concepts referenced here are introduced in
> [Key concepts](../introduction/concepts.md) and [FAIR & exports](../using-osoma/fair.md).

## What it is

The **HCM Minimal Metadata Form** is a minimal, FAIR-aligned metadata template for
**Home Cage Monitoring (HCM)** studies in preclinical research. It captures just
enough metadata — about the study, animals, cohort, housing, monitoring device,
recording protocol, behavioural metrics, and data reuse — to make an HCM dataset
Findable, Accessible, Interoperable, and Reusable, without overwhelming the
researcher with hundreds of optional fields.

The key principle behind the form is simple but load-bearing:

```{admonition} Each field references a CDE, not a label
:class: important
Every field in the form references a **versioned, semantically-mapped Common Data
Element (CDE)** — not a standalone text label. "Species" is not just the word
*species*; it is a CDE with a definition, a datatype, permissible values, and an
ontology mapping (here, NCIT *Organism Species*). This is what lets two different
labs that both filled in "Species" mean exactly the same machine-checkable thing.
```

This form is a concrete, end-to-end example of Metadatapp's **CDE + CanonicalForm +
ISA + Ontology** pattern applied to one domain.

## The CDE + Form + ISA + Ontology model

Four layers work together. Each does one job:

| Layer | What it is | In this form |
| --- | --- | --- |
| **Common Data Element (CDE)** | The atomic, versioned, ontology-mapped metadata element: a definition, datatype, optional unit constraint, permissible values, source, and status. | Every field (e.g. `species`, `sampling_frequency`) materialises as a `CommonDataElement`. |
| **CanonicalForm** | A reusable, source-agnostic data-entry template that groups CDEs into sections (`CanonicalFormSection` → `CanonicalFormField`). | `hcm_minimal_metadata_form`, version `1.0.0`, status `active`. |
| **ISA hierarchy** | The experimental structure: **Investigation → Study → Assay**. It says *where* each piece of metadata belongs in the experiment. | Each section is tagged `study` or `assay` (see [ISA mapping](#isa-mapping)). |
| **Ontology** | Shared vocabularies that fix the *meaning* of a field via an IRI. | Each CDE carries one or more ontology mappings (NCIT, OBI, PATO, EFO, ENVO, GO, DCTERMS, BIBO, HCMO, MBO). |

Two points worth stressing, consistent with the rest of Metadatapp:

- **CDEs are the foundation, not any single registry.** Metadatapp uses the CDE
  *pattern*. The HCM form's CDEs come from local Metadatapp sources plus the
  domain ontologies **HCMO** (home cage monitoring) and **MBO** (mouse behaviour),
  with curated ontology mappings. It does not depend on any external CDE service to
  function.
- **Ontology IRIs are curated, illustrative mappings.** They anchor meaning; they
  are reviewed mappings rather than authoritative external records.

CDE defaults for this form: source `metadatapp`, status `endorsed`, version
`1.0.0`. Device, recording, and behavioural-metric CDEs additionally declare a
domain source — `hcmo` for the device and recording sections, `mbo` for the
behavioural metrics section.

## The 8 sections

The form has eight sections. The tables below list every field with its datatype,
unit constraint (where one applies), required flag, and primary ontology mapping
(source + label), taken directly from the canonical JSON.

### 1. Study

Top-level study identification, objectives, design, and ethical authorisation.
(ISA level: **study**.)

| Field | Datatype | Unit | Required | Ontology (source · label) |
| --- | --- | --- | --- | --- |
| Study identifier | string | — | yes | DCTERMS · identifier |
| Experimental objective | string | — | yes | OBI · objective specification |
| Experimental design | string | — | yes | OBI · study design |
| Ethical authorization | string | — | yes | NCIT · Institutional Review Board Approval |

### 2. Animals

Biological characteristics of the experimental animals. (ISA level: **study**.)

| Field | Datatype | Unit | Required | Ontology (source · label) |
| --- | --- | --- | --- | --- |
| Species | string | — | yes | NCIT · Organism Species |
| Strain | string | — | yes | NCIT · Strain |
| Genotype | string | — | no | EFO · genotype |
| Sex | string | — | yes | PATO · biological sex |
| Age at recording | decimal | weeks | yes | PATO · age |
| Body weight | decimal | g | no | NCIT · Body Weight |

`Species` and `Sex` carry permissible values bound to ontology terms (e.g.
*Mus musculus* → NCBITaxon 10090, *female* → PATO 0000383); `Strain` offers a
controlled list (C57BL/6J, BALB/c, Wistar).

### 3. Cohort

Cohort and batching structure, group assignment, and randomisation. (ISA level:
**study**.)

| Field | Datatype | Unit | Required | Ontology (source · label) |
| --- | --- | --- | --- | --- |
| Cohort ID | string | — | yes | NCIT · Cohort |
| Batch ID | string | — | no | NCIT · Batch |
| Group assignment | string | — | yes | NCIT · Study Group |
| Randomization method | string | — | no | NCIT · Randomization |

### 4. Housing

Cage and environmental housing conditions during the study. (ISA level: **study**.)

| Field | Datatype | Unit | Required | Ontology (source · label) |
| --- | --- | --- | --- | --- |
| Cage type | string | — | yes | NCIT · Animal Housing |
| Animals per cage | integer | — | yes | — |
| Bedding | string | — | no | — |
| Enrichment | string | — | no | ENVO · environmental enrichment |
| Light/dark cycle | string | — | yes | NCIT · Light Dark Cycle |
| Temperature | decimal | Cel | no | NCIT · Temperature |
| Humidity | decimal | % | no | NCIT · Humidity |

### 5. HCM Device

The Home Cage Monitoring hardware and software configuration. (ISA level:
**assay**; CDE source `hcmo`.)

| Field | Datatype | Unit | Required | Ontology (source · label) |
| --- | --- | --- | --- | --- |
| Device manufacturer | string | — | yes | NCIT · Manufacturer |
| Device model | string | — | yes | HCMO · home cage monitoring device |
| Sensor type | string | — | yes | NCIT · Sensor Device |
| Camera configuration | string | — | no | — |
| Sampling frequency | decimal | Hz | yes | NCIT · Sampling Frequency |
| Software version | string | — | no | NCIT · Software Version |

`Sensor type` is constrained to RFID, capacitance, passive infrared (PIR),
load cell, or video.

### 6. Recording Protocol

Habituation, recording window, interventions, and disturbance events. (ISA level:
**assay**; CDE source `hcmo`.)

| Field | Datatype | Unit | Required | Ontology (source · label) |
| --- | --- | --- | --- | --- |
| Habituation duration | decimal | h | no | — |
| Recording start/end | datetime | — | yes | NCIT · Recording Period |
| Intervention timing | string | — | no | NCIT · Intervention |
| Cleaning/disturbance events | string | — | no | — |

### 7. Behavioral Metrics

Definitions of the behavioural metrics derived from HCM data. (ISA level:
**assay**; CDE source `mbo`.)

| Field | Datatype | Unit | Required | Ontology (source · label) |
| --- | --- | --- | --- | --- |
| Activity metric definition | string | — | yes | NCIT · Locomotor Activity |
| Sleep/rest definition | string | — | no | NCIT · Sleep |
| Feeding metric definition | string | — | no | NCIT · Food Intake |
| Social proximity definition | string | — | no | NCIT · Social Behavior |
| Circadian aggregation method | string | — | no | GO · circadian rhythm |

`Circadian aggregation method` is constrained to cosinor, hourly binning, or
light/dark phase averaging.

### 8. Data Reuse

Availability, licensing, and export format of study data for FAIR reuse. (ISA
level: **assay**.)

| Field | Datatype | Unit | Required | Ontology (source · label) |
| --- | --- | --- | --- | --- |
| Raw data available? | boolean | — | yes | — |
| Processed data available? | boolean | — | yes | — |
| Code available? | boolean | — | no | — |
| DOI | string | — | no | BIBO · doi |
| License | string | — | no | DCTERMS · license |
| Export format | string | — | no | NCIT · File Format |

`License` permits CC BY 4.0 and CC0 1.0; `Export format` permits RO-Crate,
ISA-Tab, FAIR2/Croissant JSON-LD, and CSV.

## ISA mapping

The form populates the ISA **Investigation → Study → Assay** hierarchy. The
`form.isaMapping` block assigns each section to an ISA level and a named ISA form.
Study-level sections describe the experimental subjects and conditions; assay-level
sections describe the HCM measurement and its outputs.

```{mermaid}
graph TD
    I[Investigation] --> S[Study]
    S --> SF1[Study · Study form]
    S --> SF2[Animals · Animal Form]
    S --> SF3[Cohort · Cohort Form]
    S --> SF4[Housing · Housing Form]
    S --> SF5[Recording Protocol · Protocol Form]
    S --> A[Assay]
    A --> AF1[HCM Device · HCM Device Form]
    A --> AF2[Recording Protocol · Recording Form]
    A --> AF3[Behavioral Metrics · Behavioral Metrics Form]
    A --> AF4[Data Reuse · Data Export Form]
```

| ISA level | ISA form | Section |
| --- | --- | --- |
| Study | Study | study |
| Study | Animal Form | animals |
| Study | Cohort Form | cohort |
| Study | Housing Form | housing |
| Study | Protocol Form | recording_protocol |
| Assay | HCM Device Form | hcm_device |
| Assay | Recording Form | recording_protocol |
| Assay | Behavioral Metrics Form | behavioral_metrics |
| Assay | Data Export Form | data_reuse |

```{note}
The **Recording Protocol** section appears at both levels: as the *Protocol Form*
at the study level (the planned protocol) and as the *Recording Form* at the assay
level (the executed recording). This mirrors the ISA convention of describing a
protocol once and referencing it from the assay that applies it.
```

## How to load it

The form is materialised from the canonical JSON by a backend seed command, which
creates the `CommonDataElement`, `CanonicalForm`, `CanonicalFormSection`, and
`CanonicalFormField` entities described above.

Run it through the standard one-off Symfony console pattern (see *One-off Symfony
console commands* in `AGENTS.md`):

```bash
docker compose -p metadatapp --profile default \
  -f infrastructure/docker/docker-compose.yml \
  exec api sh -lc 'cd /var/www/api && bin/console app:hcm:seed-form'
```

After seeding:

- The **HCM Minimal Metadata Form** is visible in Osoma at **Data Intelligence &
  FAIR → HCM Metadata Form** (`/hcm/metadata-form`) as a **read-only inspector**:
  it renders the form grouped along the ISA hierarchy with each field's CDE
  reference, units, and ontology annotations. It is a viewer of the canonical
  form's structure, not a data-entry screen.
- Studies that use the form can be exported through the existing exporters — the
  **RO-Crate** packager and the **FAIR² / Croissant JSON-LD** exporter — so the
  CDE semantics (definitions, units, ontology IRIs) travel with the dataset. See
  [FAIR & exports](../using-osoma/fair.md).
