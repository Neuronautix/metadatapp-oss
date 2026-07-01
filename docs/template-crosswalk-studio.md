# Template Crosswalk Studio

> Module display name: **Template Crosswalk Studio** (technical alias: ELN-CDE Crosswalk).
> Frontend route: `/template-crosswalks`.
>
> This page is the user- and contributor-facing guide. The authoritative engineering spec
> (entity property lists, service signatures, exact route shapes) lives in
> `reports/eln-cde-crosswalk-design.md`. Repo conventions live in `AGENTS.md`.

## Why ELN templates and CDE Forms are complementary

Electronic Lab Notebook (ELN) templates and Common Data Element (CDE) Forms solve **different
halves of the same problem**, and Metadatapp treats them as partners rather than rivals.

- **ELN templates** (from `ELN.community`, the `.eln` / RO-Crate exchange format, or eLabFTW)
  capture *how an experiment was actually recorded*. They are rich, FAIR-friendly, packaged with
  the data, and carry provenance — but their field labels are local conventions ("molecularWeight",
  "MW", "mol. weight") and rarely carry machine-checkable semantics.
- **CDE-style Forms** (including NIH CDE, but also Metadatapp-local and custom CDEs) describe *what a
  field means* in reusable, versioned, semantically anchored terms: a definition, a datatype, a unit
  constraint, permissible values, and ontology mappings.

Neither replaces the other. An ELN template tells you the data exists and where it came from; a CDE
tells you that the "molecularWeight" field means the IUPAC concept *molecular weight*, must be a
decimal in `g/mol`, and maps to a known ontology IRI. **Metadatapp's job is to connect them** — to
keep the original ELN metadata untouched while layering a reviewed, versioned semantic interpretation
on top.

### The molecular-weight example

An imported ELN template has a `PropertyValue` field labelled `molecularWeight` with example value
`128.12592` and unit `g/mol`. On its own, that is a string label and a number. After the crosswalk,
the same field is *also* linked to a `molecular_weight` CDE — definition, decimal datatype, `g/mol`
unit constraint, and ontology mapping — without changing the original ELN field name. The export then
carries both the original record and the enriched semantic layer.

## What links them: the reviewed, versioned crosswalk

Metadatapp links the two worlds through a **crosswalk**: a reviewed, versioned set of mappings at
several granularities. Each level maps an ELN-side artifact to a canonical/CDE-side artifact:

| ELN side | maps to | Canonical / CDE side |
| --- | --- | --- |
| Template (`.eln` / RO-Crate) | ↔ | Canonical Form (and optionally an external CDE Form) |
| Extracted field | ↔ | Canonical field / Common Data Element |
| Field value | ↔ | Permissible value |
| Unit on a field | ↔ | Unit constraint |
| Ontology term (DefinedTerm IRI) | ↔ | Ontology mapping |
| RO-Crate profile (`conformsTo`) | ↔ | Canonical Form definition |

Design rules that always hold:

- CDEs are **versioned, source-aware, and semantic** (anchored to meaning, not labels).
- Original ELN metadata is **never overwritten**; provenance and raw payloads are always preserved.
- **Partial mappings are allowed** — you do not have to map every field to ship a useful crosswalk.
- Many CDE Forms can apply to one ELN template, and one Form can apply across many templates. The
  crosswalk is the join entity that makes this many-to-many possible.
- **Human review is required before any mapping becomes `accepted`.** Suggestions are never
  auto-accepted.
- **NIH CDE is one provider, not the foundation.** The system works fully offline with local CDEs.

## What a crosswalk is: the entity model

A crosswalk is built from a small set of entities. The ELN side and the CDE side are imported
independently and joined by the crosswalk entities.

- **`ExternalTemplate`** — an imported ELN template (`.eln` upload or `ELN.community` URL). Stores the
  parsed `ro-crate-metadata.json` graph, raw payload, file hash, and source. Its extracted fields are
  stored as `ExtractedTemplateField` children so that **no field is discarded**, even if unmapped.
- **`CanonicalForm`** — Metadatapp's source-agnostic form model (`CanonicalFormSection`,
  `CanonicalFormField`). This is the neutral target a template is mapped onto.
- **`CommonDataElement`** — a versioned, semantic data element: definition, datatype, unit constraint,
  permissible values, ontology mappings, provenance, source, and status. Carries a stable `localKey`
  slug (e.g. `molecular_weight`) used in JSON-LD `@id`s.
- **`ExternalForm`** *(optional)* — an external CDE Form (e.g. an NIH CDE form) referenced by a
  crosswalk alongside the canonical form.
- **`TemplateFormCrosswalk`** — the top-level join: one `ExternalTemplate` ↔ one `CanonicalForm`
  (and optionally one `ExternalForm`). Carries `mappingType`, `mappingStatus`, `confidence`,
  `evidence`, reviewer, version, and notes.
- **`FieldCrosswalk`** — a per-field mapping inside a `TemplateFormCrosswalk`: an
  `ExtractedTemplateField` ↔ a `CanonicalFormField` and/or a `CommonDataElement`, with its own
  mapping type, status, confidence, and datatype/unit/value mappings.
- **`ValueCrosswalk`** — a per-value mapping inside a `FieldCrosswalk`: a source value ↔ a target
  permissible value / code / ontology term.

Mapping types (shared `CrosswalkMappingType` enum): `exact`, `close`, `partial`, `composite`,
`broader`, `narrower`, `related`, `no_match`. Mapping status (`MappingStatus`): `suggested`,
`accepted`, `rejected`, `deprecated`.

## Importing an ELN template (`.eln` or `ELN.community` URL)

There are two import paths; both produce an `ExternalTemplate` with extracted fields.

**Upload a `.eln` file** — multipart `file`:

```
POST /api/external-templates/import-eln
Content-Type: multipart/form-data
file=<your-template.eln>
```

**Import from an `ELN.community` record URL** — JSON body:

```json
POST /api/external-templates/import-url
{ "url": "https://eln.community/record/019bff9f-df44-71a0-9d3b-11a62730d34c" }
```

What the importer does:

1. Treats the `.eln` as a ZIP and finds the `ro-crate-metadata.json` member.
2. Parses the JSON-LD `@graph`, indexes nodes by `@id`, and locates the root `Dataset`.
3. Walks `Dataset`, `CreativeWork`, `File`, `PropertyValue`, `DefinedTerm`, `FormalParameter`,
   `variableMeasured`, `instrument`, `measurementTechnique`, `hasPart`, `conformsTo`, authors, etc.
4. For each `PropertyValue` / custom field, captures label (`name`), `propertyID`, value, unit
   (`unitCode`/`unitText`), guessed datatype, resolved ontology IRIs, and the JSON-LD path.
5. **Does not discard unmapped fields** — every extracted field is stored as an
   `ExtractedTemplateField`.

The full `ro-crate-metadata.json` graph, raw payload, and a sha256 file hash are preserved on the
`ExternalTemplate` so the original record can always be reproduced.

## Importing or creating CDEs

CDEs are imported through a **provider abstraction** (`CdeProviderInterface`), so the source is
pluggable and the studio behaves identically regardless of where a CDE came from:

- **`metadatapp` (local)** — CDEs stored in Metadatapp itself. This is the default and works **fully
  offline**; you never need network access to build, map, or export a crosswalk.
- **`nih_cde`** — the NIH CDE repository API (`https://cde.nlm.nih.gov/api`, configurable). NIH is
  **only one provider**, not a dependency: if there is no connectivity or a non-2xx response, the
  provider degrades gracefully (returns empty) and the rest of the studio keeps working with local
  CDEs.
- **`custom_json`** — a hand-supplied JSON array of CDE definitions for manual import.

Import a CDE from a provider, or push a raw payload directly:

```json
POST /api/cdes/import
{ "provider": "nih_cde", "externalId": "2179898" }
```

```json
POST /api/cdes/import
{ "payload": { "label": "Molecular weight", "datatype": "decimal",
               "unitConstraint": "g/mol", "localKey": "molecular_weight" } }
```

You can also create CDEs directly (`POST /api/cdes`) — for example, the "create new Metadatapp CDE"
action in the studio when no existing CDE fits. Whatever the source, the importer normalizes the
result into a `CommonDataElement` and **preserves the raw payload and provenance**.

## Mapping fields

Mapping is a two-step process: a deterministic suggestion pass, then required human review.

### Deterministic suggestions

The `MappingSuggestionEngine` runs a **deterministic first pass (no LLM)**. For each extracted field
it scores candidate CDEs by combining weighted signals and clamping to `[0, 1]`:

- exact normalized label match (case/space/punctuation) → strong (`+0.6`, type `exact`)
- normalized / synonym / stem label match → `+0.3..0.45` (type `close`)
- `propertyID` equals the CDE external id / IRI → `+0.3`
- ontology term IRI overlap → `+0.25`
- datatype compatibility → `+0.1` (incompatible → risk + cap)
- unit compatibility → `+0.1` (mismatch → risk)
- section / context similarity → `+0.05`

Trigger suggestions for a crosswalk:

```
POST /api/template-crosswalks/{id}/suggest-mappings  → { "suggestions": [ ... ] }
```

Example suggestion — ELN field `molecularWeight` against a `Molecular weight` CDE with unit `g/mol`:

```json
{
  "cde": { "localKey": "molecular_weight", "label": "Molecular weight", "unitConstraint": "g/mol" },
  "mappingType": "exact",
  "confidence": 0.94,
  "reasons": ["exact normalized label match", "compatible unit g/mol"],
  "risks": []
}
```

An optional `MappingSuggestionRefinerInterface` hook allows later AI assistance to re-rank
suggestions; the default refiner is a no-op, keeping the baseline fully deterministic.

### Human review (required before `accepted`)

Suggestions land with status `suggested`. **A curator must explicitly accept or reject each field
mapping** — nothing is auto-accepted. Per field, the curator can choose the mapping type, add curator
notes, and set datatype/unit/value mappings.

```
POST /api/field-crosswalks/{id}/accept   → FieldCrosswalk (status accepted)
POST /api/field-crosswalks/{id}/reject   → FieldCrosswalk (status rejected)
```

Only `accepted` field crosswalks flow into the enriched export; `rejected` ones never appear.

## Validating semantic completeness

The `CrosswalkValidator` produces a `ValidationReport` summarizing how semantically complete a
crosswalk is:

```
POST /api/template-crosswalks/{id}/validate  → ValidationReport JSON
```

The report includes counts and issues:

- counts: `fieldsExtracted`, `fieldsMappedToCdes`, `unmappedFields`, `numericFields`,
  `numericFieldsWithUnits`, `categoricalFields`, `categoricalFieldsWithControlledValues`,
  `fieldsRequiringReview`
- issues: each `{ code, severity (error | warning | info), message, fieldRef? }` — codes include
  `MISSING_DATATYPE`, `MISSING_UNIT`, `INVALID_UNIT`, `VALUE_NOT_PERMISSIBLE`, `AMBIGUOUS_MAPPING`,
  `NO_MATCHING_CDE_FORM`, `DEPRECATED_CDE_USED`, `MAPPING_NOT_REVIEWED`, `UNMAPPED_FIELD`
- `semanticCompletenessScore` (0–100): a weighted blend of mapped ratio, unit coverage,
  controlled-value coverage, and review ratio.

Example report fragment — 18 of 25 fields mapped, with gaps still flagged:

```json
{
  "fieldsExtracted": 25,
  "fieldsMappedToCdes": 18,
  "unmappedFields": 7,
  "numericFields": 9,
  "numericFieldsWithUnits": 7,
  "categoricalFields": 6,
  "categoricalFieldsWithControlledValues": 4,
  "fieldsRequiringReview": 3,
  "issues": [
    { "code": "MISSING_UNIT", "severity": "warning",
      "message": "Numeric field has no unit constraint", "fieldRef": "#field-concentration" },
    { "code": "UNMAPPED_FIELD", "severity": "info",
      "message": "Field not mapped to any CDE", "fieldRef": "#field-operator-notes" }
  ],
  "semanticCompletenessScore": 74
}
```

A partial crosswalk is still valid and exportable — the score and issues simply tell you where the
remaining semantic gaps are.

## Exporting enriched RO-Crate / JSON-LD

Export adds the semantic layer **on top of** the original record. The exporter deep-copies
`externalTemplate.roCrateMetadata` and **never mutates or overwrites original nodes** — it only adds
or enriches, preserving original field names. `rejected` field crosswalks are excluded.

```
POST /api/template-crosswalks/{id}/export-ro-crate  → enriched RO-Crate JSON
POST /api/template-crosswalks/{id}/export-jsonld     → JSON-LD graph fragment
```

For each `accepted` field crosswalk with a CDE, an enriched `PropertyValue` node is added/updated.
It links back to the source record (`isBasedOn`), the canonical form (`conformsTo`), and the CDE
(`subjectOf`):

```json
{
  "@id": "#field-molecular-weight",
  "@type": "PropertyValue",
  "name": "Molecular weight",
  "propertyID": "metadatapp:cde:molecular_weight",
  "value": 128.12592,
  "unitCode": "g/mol",
  "isBasedOn": { "@id": "https://eln.community/record/<uuid>" },
  "conformsTo": { "@id": "metadatapp:form:<canonicalFormSlug>" },
  "subjectOf": { "@id": "metadatapp:cde:molecular_weight" }
}
```

Each referenced CDE is added as a combined `DefinedTerm` + `PropertyValueSpecification` node so the
semantics travel with the crate:

```json
{
  "@id": "metadatapp:cde:molecular_weight",
  "@type": ["DefinedTerm", "PropertyValueSpecification"],
  "name": "Molecular weight",
  "description": "The mass of one mole of a substance.",
  "valueRequired": true,
  "valuePattern": "decimal",
  "unitCode": "g/mol",
  "inDefinedTermSet": { "@id": "metadatapp:cde-registry:<domain>" }
}
```

The crosswalk itself is added as a first-class `CreativeWork` node tying source to target:

```json
{
  "@id": "metadatapp:crosswalk:<slug>",
  "@type": "CreativeWork",
  "name": "Crosswalk between <template> and <form>",
  "source": { "@id": "<template externalUrl>" },
  "target": { "@id": "metadatapp:form:<slug>" },
  "mappingStatus": "accepted",
  "hasPart": [ { "@id": "#field-molecular-weight" } ]
}
```

The `metadatapp:cde:<localKey>` slug derives from `CommonDataElement.localKey`; the
`metadatapp:form:<slug>` slug derives from `CanonicalForm.title`.

## Known limitations

- **Suggestions are deterministic, not authoritative.** The engine ranks candidates from label,
  property, ontology, datatype, and unit signals; it cannot infer intent. Human review is always
  required before `accepted`, and the AI refiner hook is a no-op by default.
- **NIH CDE access is best-effort.** The NIH provider depends on an external API and degrades
  gracefully to empty results offline. Build crosswalks with local CDEs when connectivity is
  unavailable.
- **Partial mappings are expected.** Not every ELN field maps cleanly to a CDE; unmapped fields are
  preserved but lower the semantic completeness score.
- **RO-Crate import is scoped to recognised node types.** Fields outside the walked node types are
  still captured as raw extracted fields, but may not be auto-typed (datatype/unit guessed
  heuristically).
- **Crosswalks are versioned but not auto-migrated.** When a CDE is deprecated or a new template
  version is imported, existing mappings are not automatically re-pointed; the validator flags
  `DEPRECATED_CDE_USED` so a curator can revise and re-review.
- **Value-level mapping is manual for unconstrained vocabularies.** Permissible-value matching relies
  on the target CDE having controlled values; free-text categorical fields require curator input.
