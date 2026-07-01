---
title: "Feature: Reference Hub"
type: feature
updated: 2026-06-30
source_prs: []
related: [features/connected-apps.md, features/metadata-standards.md]
---

# Feature: Reference Hub

## Status
Active — extended in issue #330 to cover metadata schemas, templates, and guidelines

## Summary
The Reference Hub is a federated search across multiple external reference databases and metadata standards sources. Researchers can search for phenotyping resources (strains, measures, procedures, CDEs, protocols) and metadata artifacts (schemas, templates, guidelines, ontology classes) in a single query.

Selected results can be:
- **Imported** into the account's reference library
- **Compared with AI** for concept-level standardization (concept types)
- **Field-harmonized with AI** for schema/template types via the Compare Fields tool
- **Promoted** to Common Data Elements in the Crosswalk Studio

## Architecture

### Adapter pattern

```
ReferenceSearchService
  └─ fans query to each active ConnectedApp's adapter
       └─ ReferenceSearchAdapterInterface::search(ConnectedApp, query, limit)
            └─ returns list<ReferenceResult>
```

New adapters are auto-tagged via `#[AutoconfigureTag('app.reference_search_adapter')]` and collected automatically.

### Adding a new source

1. Add a new `AppCode` case if the source needs a ConnectedApp record
2. Create a client interface + implementation in `api/src/ConnectedApps/Apps/{Name}/Client/`
3. Implement `ReferenceSearchAdapterInterface` in `api/src/ConnectedApps/Reference/Adapter/`
4. If the source requires no credentials, add its `AppCode` to `ReferenceSearchService::publicDefaults()`
5. Add a unit test in `api/tests/Unit/ConnectedApps/Reference/Adapter/`

## Reference sources

| AppCode | Source | Type(s) emitted | Public default |
|---|---|---|---|
| `jax_phenome` | JAX Phenome (MPD) | strain, measure | yes |
| `impc` | IMPReSS (IMPC) | procedure, pipeline | yes |
| `nih_cde` | NIH CDE Repository | cde_element, template | yes |
| `preclinicaltrials` | PreclinicalTrials.eu | protocol | yes |
| `mnms` | MNMS (Minimal Neuroimaging Metadata Standards) | schema | yes |
| `guidelines_hub` | ARRIVE 2.0 / PREPARE / EQIPD | guideline | yes |
| `elabftw` | ElabFTW | template | no — tenant ConnectedApp |
| `cedar` | CEDAR Workbench | schema | no — tenant ConnectedApp |
| `osf` | OSF Registration Schemas | template | no — tenant ConnectedApp |
| `bioportal` | BioPortal (HCMO, NBO, …) | ontology_class | no — tenant ConnectedApp |

## Reference types

| `type` value | Human label | Typical source |
|---|---|---|
| `strain` | Strain | JAX Phenome |
| `measure` | Measure | JAX Phenome, IMPC |
| `procedure` | Procedure | IMPC |
| `pipeline` | Pipeline | IMPC |
| `cde_element` | CDE | NIH CDE |
| `protocol` | Protocol | PreclinicalTrials |
| `template` | Template | ElabFTW, OSF, NIH CDE forms |
| `schema` | Schema | CEDAR, MNMS |
| `guideline` | Guideline | ARRIVE / PREPARE / EQIPD |
| `ontology_class` | Ontology term | BioPortal |

## AI features

### Concept standardization (`POST /references/standardize`)
Clusters selected concept-type results (strain, measure, etc.) against ontology terms (VT, MP, MA, EFO). Returns `StandardizationResult` with canonical terms and `HarmonizedField[]` for per-field conflict resolution.

### Schema field comparison (`POST /references/compare-schemas`)
Accepts selected `template | schema | guideline` results. Extracts field definitions from each source's `raw` payload via `SchemaFieldExtractorService` (source-specific extraction per CEDAR JSON-LD, ElabFTW extra_fields, OSF schema pages, etc.). An AI model groups equivalent fields across sources into `FieldGroup[]`, each with a canonical label, datatype, unit, confidence, and conflict list. Falls back to label-normalization grouping when AI is unavailable.

Bridge to Crosswalk Studio: after comparing, the frontend offers "Import to Crosswalk Studio" (calls existing `useImportElnUrl` or `useImportCde` hooks).

## Known limitations / future opportunities

- grit42 SEND format integration is deferred (Tier 3 — grit42 API surface needs clarification; consider CDISC public terminology API as alternative)
- OSF registration schemas are tenant-activated; a future PR can add them as a public default (they are unauthenticated)
- PreclinicalTrials.eu JSON protocol documents: adapter can be extended to emit `type: 'template'` when `jsonProtocol` field is present; needs live API response inspection first
- Automatic crosswalk creation from schema comparison field groups (Option B in the design) deferred to a follow-on PR

## Related

- [features/connected-apps.md](connected-apps.md) — ConnectedApp entity and activation flow
- [features/metadata-standards.md](metadata-standards.md) — RO-Crate, FAIR², Croissant ML export formats
