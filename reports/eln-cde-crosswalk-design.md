# ELN–CDE Crosswalk Module — Authoritative Design Spec

> This is the single source of truth for the "Template Crosswalk Studio" / "ELN-CDE Crosswalk"
> module. Every subagent implementing a slice MUST code to the exact names, types, routes,
> enum values, service signatures and JSON-LD shapes defined here so the slices fit together.
> Follow repo conventions in `AGENTS.md`. PHP files start with `declare(strict_types=1);`.
>
> Environment note: `composer install` and the Docker/Castor stack are NOT available in this
> session (composer plugins disabled, no `vendor/`). Do NOT try to run `castor`, `composer`,
> `phpunit`, or `pnpm install`. Write correct code matching the patterns referenced below; CI
> validates it. Reference patterns:
> - Entity: `api/src/Entity/MappingTemplate.php`, `api/src/Entity/CurationSuggestion.php`
> - Enum: `api/src/Enum/AlertEntityType.php`, `api/src/Enum/EnumApiResourceTrait.php`
> - Account auto-set: `api/src/State/Processor/SetAccountProcessor.php` (auto-sets account on
>   persist for any new `AccountAwareInterface` entity — do NOT write your own).
> - Upload controller: `api/src/Controller/Api/NwbImportController.php`
> - Export controller: `api/src/Controller/Api/RoCrateExportController.php`
> - Factory: `api/src/DataFixtures/Factory/MortalityRecordFactory.php`
> - API test: `api/tests/Api/RoCrateExportControllerTest.php`
> - Frontend feature: `osoma/src/features/curation/` + `osoma/src/lib/api.ts` (`apiFetch`)

## Naming

- Module display name: **Template Crosswalk Studio** (technical alias: ELN-CDE Crosswalk).
- Frontend route: `/template-crosswalks`.
- Entities live flat in `api/src/Entity/` namespace `App\Entity` (Doctrine maps that dir).
- Enums in `api/src/Enum/` namespace `App\Enum`.
- Repositories in `api/src/Repository/` namespace `App\Repository`.
- Services in `api/src/Crosswalk/` namespace `App\Crosswalk` (new top-level service dir).
- Controllers in `api/src/Controller/Api/` namespace `App\Controller\Api`.

## Conventions every entity follows

- UUID id exactly like `MappingTemplate`:
  ```php
  #[ORM\Id]
  #[ORM\Column(type: UuidType::NAME, unique: true)]
  #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
  #[ORM\GeneratedValue(strategy: 'CUSTOM')]
  private ?Uuid $id = null;
  public function getId(): ?Uuid { return $this->id; }
  ```
- Fluent setters returning `self`. Getters `getX()`, setters `setX()`. Booleans `isX()`.
- JSON columns typed `array` with `#[ORM\Column(type: 'json')]`, nullable where noted, default `[]`.
- Timestamps `\DateTimeImmutable`, set in constructor; `imported_at`/`created_at` in ctor.
- Serializer groups: each entity uses group prefix = snake_case singular (e.g. `external_template.read`
  / `external_template.write`). `read` on all readable props, `write` on client-writable props.
- Tenant isolation: aggregate roots implement `AccountAwareInterface` (and `UserAwareInterface`
  where a `created_by` user exists) so `SetAccountProcessor` auto-assigns the account. Add the
  `private Account $account;` + `getAccount()/setAccount()` and `private User $user;` +
  `getUser()/setUser()` members exactly like `MappingTemplate`. Child entities (sections, fields,
  field/value crosswalks) are NOT AccountAware — they inherit tenancy via their parent relation
  and are exposed only as sub-resources / through their parent.

## Enums (string-backed, namespace App\Enum)

Add `use EnumApiResourceTrait;` to each so they can be exposed if needed.

```
ExternalTemplateSource: ELN_COMMUNITY='eln_community', ELN_FILE='eln_file', ELABFTW='elabftw', CUSTOM='custom'
ExternalTemplateFormat: ELN_RO_CRATE='eln_ro_crate', CUSTOM='custom'
ExternalFormSource:     NIH_CDE='nih_cde', METADATAPP='metadatapp', CUSTOM='custom'
CdeSource:              NIH_CDE='nih_cde', METADATAPP='metadatapp', HCMO='hcmo', MBO='mbo', CUSTOM='custom'
CdeStatus:              DRAFT='draft', RECOMMENDED='recommended', ENDORSED='endorsed', DEPRECATED='deprecated'
CanonicalFormStatus:    DRAFT='draft', ACTIVE='active', DEPRECATED='deprecated'
CrosswalkMappingType:   EXACT='exact', CLOSE='close', PARTIAL='partial', COMPOSITE='composite', BROADER='broader', NARROWER='narrower', RELATED='related', NO_MATCH='no_match'
MappingStatus:          SUGGESTED='suggested', ACCEPTED='accepted', REJECTED='rejected', DEPRECATED='deprecated'
```
`CrosswalkMappingType` is the single shared mapping-type enum (covers template-level exact/close/
partial/composite/related/no_match AND field/value-level exact/close/broader/narrower/related/no_match).

## Entities (exact property list; types in parens)

### ExternalTemplate  — implements AccountAwareInterface, UserAwareInterface
- id (Uuid)
- source (ExternalTemplateSource enum col)
- externalUrl (?string 1024)
- title (string 255)
- description (?text)
- version (?string 100)
- format (ExternalTemplateFormat enum col, default ELN_RO_CRATE)
- roCrateMetadata (array json nullable)  — the parsed ro-crate-metadata.json graph
- rawPayload (array json nullable)        — any other raw import payload
- fileHash (?string 128)
- importedAt (\DateTimeImmutable)
- account (Account), user (User)  // user == created_by
- relation: extractedFields → OneToMany ExtractedTemplateField (see below), cascade persist/remove
NOTE: store extracted fields in a dedicated child entity `ExtractedTemplateField` (this is the
"do not discard unmapped fields" store). Properties of ExtractedTemplateField:
  id, externalTemplate(ManyToOne, JoinColumn nullable:false onDelete CASCADE),
  jsonldId(?string 512), jsonldPath(?string 1024), label(string 255), propertyId(?string 512),
  datatype(?string 100), exampleValue(?text), unit(?string 100),
  ontologyTerms(array json), rawNode(array json), orderIndex(int default 0),
  mapped(bool default false).
  Serializer group prefix `extracted_template_field`.

### ExternalForm — implements AccountAwareInterface, UserAwareInterface
- id, source (ExternalFormSource), externalId (?string 255), externalUrl (?string 1024),
  title (string 255), description (?text), version (?string 100), rawPayload (array json nullable),
  importedAt (\DateTimeImmutable), account, user

### CanonicalForm — implements AccountAwareInterface
- id, title (string 255), description (?text), domain (?string 255), version (string 50 default '1.0.0'),
  status (CanonicalFormStatus default DRAFT), sourceLinks (array json),
  createdAt (\DateTimeImmutable), updatedAt (\DateTimeImmutable), account
- relations: sections → OneToMany CanonicalFormSection (cascade persist/remove, orderBy orderIndex ASC),
  fields → OneToMany CanonicalFormField (cascade persist/remove, orderBy orderIndex ASC)
- `#[ORM\HasLifecycleCallbacks]` with PreUpdate touching updatedAt.

### CanonicalFormSection
- id, canonicalForm (ManyToOne CanonicalForm, JoinColumn nullable:false onDelete CASCADE),
  title (string 255), description (?text), orderIndex (int default 0)

### CanonicalFormField
- id, canonicalForm (ManyToOne nullable:false onDelete CASCADE),
  section (ManyToOne CanonicalFormSection nullable onDelete SET NULL),
  localKey (string 255), label (string 255), description (?text),
  datatype (?string 100), required (bool default false), unitConstraint (?string 100),
  allowedValues (array json), ontologyMappings (array json),
  jsonldPath (?string 1024), sourceFieldReference (?string 1024), orderIndex (int default 0)

### CommonDataElement — implements AccountAwareInterface
- id, source (CdeSource), externalId (?string 255), externalUrl (?string 1024),
  version (?string 100), label (string 255), definition (?text), questionText (?text),
  datatype (?string 100), unitConstraint (?string 100),
  permissibleValues (array json), ontologyMappings (array json), provenance (array json),
  rawPayload (array json nullable), status (CdeStatus default DRAFT),
  localKey (?string 255)  // stable slug used in JSON-LD @id, e.g. molecular_weight
  account
  - permissibleValues item shape: {value: string, code?: string, ontologyTerm?: string}
  - ontologyMappings item shape: {iri: string, label?: string, source?: string}

### TemplateFormCrosswalk — implements AccountAwareInterface
- id,
  externalTemplate (ManyToOne ExternalTemplate, nullable:false onDelete CASCADE),
  externalForm (ManyToOne ExternalForm, nullable onDelete SET NULL),
  canonicalForm (ManyToOne CanonicalForm, nullable:false onDelete CASCADE),
  mappingType (CrosswalkMappingType default RELATED),
  mappingStatus (MappingStatus default SUGGESTED),
  confidence (?float), evidence (?text),
  reviewedBy (ManyToOne User nullable onDelete SET NULL), reviewedAt (?\DateTimeImmutable),
  version (string 50 default '1.0.0'), notes (?text),
  createdAt (\DateTimeImmutable), account
- relation: fieldCrosswalks → OneToMany FieldCrosswalk (cascade persist/remove)

### FieldCrosswalk
- id, templateFormCrosswalk (ManyToOne nullable:false onDelete CASCADE),
  canonicalFormField (ManyToOne CanonicalFormField nullable onDelete SET NULL),
  cde (ManyToOne CommonDataElement nullable onDelete SET NULL),
  elnField (ManyToOne ExtractedTemplateField nullable onDelete SET NULL),
  elnJsonldPath (?string 1024), elnPropertyId (?string 512),
  cdeFormFieldId (?string 255),
  mappingType (CrosswalkMappingType default RELATED),
  mappingStatus (MappingStatus default SUGGESTED),
  confidence (?float), evidence (?text),
  datatypeMapping (array json), unitMapping (array json), valueMapping (array json),
  curatorNotes (?text)
- relation: valueCrosswalks → OneToMany ValueCrosswalk (cascade persist/remove)

### ValueCrosswalk
- id, fieldCrosswalk (ManyToOne nullable:false onDelete CASCADE),
  sourceValue (?string 512), targetValue (?string 512), targetCode (?string 255),
  ontologyTerm (?string 512),
  mappingType (CrosswalkMappingType default RELATED),
  mappingStatus (MappingStatus default SUGGESTED)

## Repositories
Plain `ServiceEntityRepository` subclasses for each top-level entity
(ExternalTemplate, ExternalForm, CanonicalForm, CommonDataElement, TemplateFormCrosswalk),
wired via `repositoryClass:` on the entity. Add finder helpers only as needed by services.

## Migration
One Doctrine migration `api/migrations/Version20260617120000.php` creating all tables with
`crosswalk_` table-name prefix (e.g. `crosswalk_external_template`, `crosswalk_external_form`,
`crosswalk_canonical_form`, `crosswalk_canonical_form_section`, `crosswalk_canonical_form_field`,
`crosswalk_common_data_element`, `crosswalk_template_form_crosswalk`, `crosswalk_field_crosswalk`,
`crosswalk_value_crosswalk`, `crosswalk_extracted_template_field`). Set explicit
`#[ORM\Table(name: '...')]` on each entity to match. Postgres column types: uuid, json (jsonb ok via
`json` doctrine type), text, varchar, bool, float8, timestamp. Include FKs with ON DELETE rules above.
Provide both `up()` and `down()`.

## Services (api/src/Crosswalk/, namespace App\Crosswalk)

### Import\ElnRoCrateImporter
```php
public function importFromFile(\SplFileInfo|UploadedFile $file): ParsedRoCrate
public function importFromUrl(string $url): ParsedRoCrate   // uses Symfony HttpClientInterface to download .eln
```
`ParsedRoCrate` DTO (App\Crosswalk\Import\ParsedRoCrate) holds:
  metadataJson (array, the full ro-crate-metadata.json), rootDataset (array),
  fileHash (string sha256 of the .eln bytes), title (?string), description (?string),
  version (?string), externalUrl (?string),
  extractedFields (list of ExtractedFieldData DTOs).
Behavior:
  - Treat `.eln` as ZIP (`ZipArchive`). Find the member ending in `ro-crate-metadata.json`.
  - Parse JSON-LD `@graph`. Index nodes by `@id`. Find root Dataset (the entity referenced by the
    metadata descriptor's `about`, or the node with `@type` Dataset and `@id` './').
  - Walk these node types and produce extracted fields: Dataset, CreativeWork, File, PropertyValue,
    DefinedTerm, DefinedTermSet, FormalParameter, plus `variableMeasured`, `instrument`,
    `measurementTechnique`, `hasPart`, `conformsTo`, `license`, author/creator/contributor.
  - For each `PropertyValue` and custom/extra field, capture: label (name), propertyId (propertyID),
    value (value/exampleValue), unit (unitCode/unitText), datatype (guessed: number/decimal/string/
    boolean/date), ontologyTerms (resolved DefinedTerm IRIs), jsonldId, jsonldPath, rawNode.
  - Do NOT discard unmapped fields. Every extracted field becomes an ExtractedFieldData.
`ExtractedFieldData` (App\Crosswalk\Import\ExtractedFieldData): readonly DTO with the fields above.
A separate `ElnImportService` persists: creates ExternalTemplate (source ELN_FILE for upload /
ELN_COMMUNITY for url), stores roCrateMetadata=metadataJson, rawPayload, fileHash, and one
ExtractedTemplateField per ExtractedFieldData. Method:
  `public function importEln(UploadedFile $file, User $user): ExternalTemplate`
  `public function importElnUrl(string $url, User $user): ExternalTemplate`

### Cde\CdeProviderInterface (App\Crosswalk\Cde)
```php
interface CdeProviderInterface {
  public function key(): string;                       // 'metadatapp' | 'nih_cde' | 'custom_json'
  public function searchCdes(string $query): array;    // list of CdeCandidate
  public function getCde(string $externalId): ?CdeCandidate;
  public function searchForms(string $query): array;   // list of FormCandidate
  public function getForm(string $externalId): ?FormCandidate;
}
```
`CdeCandidate` / `FormCandidate` are readonly DTOs normalizing provider results to the
CommonDataElement / ExternalForm shape (include rawPayload + provenance + sourceUrl + version +
status/steward when available).
Providers:
  - `LocalMetadatappCdeProvider` — queries CommonDataElement repo (source METADATAPP/local).
  - `NihCdeProvider` — uses HttpClientInterface against the NIH CDE repository API
    (base configurable via `%env(default::NIH_CDE_API_BASE)%`, default
    `https://cde.nlm.nih.gov/api`). Import by tinyId/externalId, store raw payload + provenance.
    MUST degrade gracefully (return [] / null) when no connectivity or non-2xx.
  - `CustomJsonCdeProvider` — accepts a JSON array of CDE definitions (manual import).
A `CdeProviderRegistry` (tagged-iterator of providers) resolves by `key()`.
A `CdeImportService` normalizes a CdeCandidate/FormCandidate into persisted CommonDataElement /
ExternalForm and preserves rawPayload + provenance.

### Mapping\MappingSuggestionEngine (App\Crosswalk\Mapping)
Deterministic first pass (NO LLM). Signature:
```php
public function suggestForField(ExtractedTemplateField $field, iterable $cdeCandidates): array
// returns list of MappingSuggestion sorted by confidence desc
public function suggestForCrosswalk(TemplateFormCrosswalk $crosswalk): array
```
`MappingSuggestion` (readonly DTO): cde (CommonDataElement|CdeCandidate), confidence (float 0..1),
reasons (string[]), risks (string[]), mappingType (CrosswalkMappingType).
Scoring signals (combine, weighted, clamp to [0,1]):
  - exact label match (normalized case/space/punct) → strong (+0.6 / type EXACT)
  - normalized/synonym/stem label match → +0.3..0.45 (type CLOSE)
  - propertyID == CDE externalId/iri → +0.3
  - ontology term IRI overlap → +0.25
  - datatype compatibility → +0.1 (incompatible → risk + cap)
  - unit compatibility → +0.1 (mismatch unit → risk)
  - section/context similarity → +0.05
Example required to work: ELN field label "molecularWeight" + CDE label "Molecular weight" with
unit g/mol ⇒ suggestion type EXACT, confidence ≈ 0.94, reasons include "exact normalized label match"
and "compatible unit g/mol".
Expose a hook interface `MappingSuggestionRefinerInterface { public function refine(array $suggestions, ExtractedTemplateField $field): array; }`
for optional later AI assistance (default no-op refiner). The engine calls the refiner if present.

### Validation\CrosswalkValidator (App\Crosswalk\Validation)
```php
public function validate(TemplateFormCrosswalk $crosswalk): ValidationReport
```
`ValidationReport` (readonly DTO, JSON-serializable to array via `toArray()`): counts
(fieldsExtracted, fieldsMappedToCdes, unmappedFields, numericFields, numericFieldsWithUnits,
categoricalFields, categoricalFieldsWithControlledValues, fieldsRequiringReview), list of issues
(each {code, severity: error|warning|info, message, fieldRef?}), and `semanticCompletenessScore`
(0..100 int). Issue codes: MISSING_DATATYPE, MISSING_UNIT, INVALID_UNIT, VALUE_NOT_PERMISSIBLE,
AMBIGUOUS_MAPPING, NO_MATCHING_CDE_FORM, DEPRECATED_CDE_USED, MAPPING_NOT_REVIEWED, UNMAPPED_FIELD.
Score = round(100 * weighted(mapped ratio, unit coverage, controlled-value coverage, review ratio)).

### Export\CrosswalkJsonLdExporter (App\Crosswalk\Export)
```php
public function exportEnrichedRoCrate(TemplateFormCrosswalk $crosswalk): array  // full RO-Crate JSON-LD
public function exportJsonLd(TemplateFormCrosswalk $crosswalk): array            // graph fragment
```
Rules:
  - Start from `externalTemplate.roCrateMetadata` (deep copy). NEVER mutate/overwrite original nodes;
    only ADD or ENRICH. Preserve original field names.
  - For each ACCEPTED FieldCrosswalk with a CDE, add/enrich a `PropertyValue` node:
    ```json
    {"@id":"#field-molecular-weight","@type":"PropertyValue","name":"Molecular weight",
     "propertyID":"metadatapp:cde:molecular_weight","value":128.12592,"unitCode":"g/mol",
     "isBasedOn":{"@id":"https://eln.community/record/<uuid>"},
     "conformsTo":{"@id":"metadatapp:form:<canonicalFormSlug>"},
     "subjectOf":{"@id":"metadatapp:cde:molecular_weight"}}
    ```
  - Add each referenced CDE as a DefinedTerm + PropertyValueSpecification node:
    ```json
    {"@id":"metadatapp:cde:molecular_weight","@type":["DefinedTerm","PropertyValueSpecification"],
     "name":"Molecular weight","description":"...","valueRequired":true,"valuePattern":"decimal",
     "unitCode":"g/mol","inDefinedTermSet":{"@id":"metadatapp:cde-registry:<domain>"}}
    ```
  - Add the crosswalk itself as a first-class CreativeWork node:
    ```json
    {"@id":"metadatapp:crosswalk:<slug>","@type":"CreativeWork",
     "name":"Crosswalk between <template> and <form>",
     "source":{"@id":"<template externalUrl>"},"target":{"@id":"metadatapp:form:<slug>"},
     "mappingStatus":"accepted","hasPart":[{"@id":"#field-..."}]}
    ```
  - REJECTED field crosswalks MUST NOT appear in the export.
  - `metadatapp:cde:<localKey>` slug derives from CommonDataElement.localKey;
    `metadatapp:form:<slug>` from a slug of CanonicalForm.title.

## API endpoints (all under /api; entity CRUD via #[ApiResource], actions via Controller/Api)

Entity #[ApiResource] uriTemplates (GetCollection/Get/Post/Patch as noted):
- ExternalTemplate:   GET/POST `/external-templates`, GET `/external-templates/{id}`
- ExternalForm:       GET/POST `/external-forms`, GET `/external-forms/{id}`
- CommonDataElement:  GET/POST `/cdes`, GET `/cdes/{id}`
- CanonicalForm:      GET/POST `/canonical-forms`, GET `/canonical-forms/{id}` (helper, optional)
- TemplateFormCrosswalk: GET/POST `/template-crosswalks`, GET + PATCH `/template-crosswalks/{id}`

Action controllers (Symfony #[Route], `#[IsGranted('ROLE_USER')]`, tenant-checked):
- POST `/api/external-templates/import-eln`   (multipart `file`, .eln) → ExternalTemplate JSON
- POST `/api/external-templates/import-url`    (JSON `{url}`) → ExternalTemplate JSON
- POST `/api/external-forms/import`            (JSON `{provider, externalId}` or `{payload}`) → ExternalForm
- POST `/api/cdes/import`                      (JSON `{provider, externalId}` or `{payload}`) → CDE(s)
- POST `/api/template-crosswalks/{id}/suggest-mappings` → {suggestions:[...]}
- POST `/api/template-crosswalks/{id}/validate`         → ValidationReport JSON
- POST `/api/template-crosswalks/{id}/export-ro-crate`  → enriched RO-Crate JSON
- POST `/api/template-crosswalks/{id}/export-jsonld`    → JSON-LD graph
- POST `/api/field-crosswalks/{id}/accept`              → updated FieldCrosswalk (status ACCEPTED)
- POST `/api/field-crosswalks/{id}/reject`              → updated FieldCrosswalk (status REJECTED)

All action controllers verify the resource's `account` equals the current user's account
(see RoCrateExportController for the pattern) and return 403 otherwise, 404 when not found.

## Fixtures / seed (api/src/DataFixtures or a dedicated demo command)
Add Foundry factories for each top-level entity, and a demo crosswalk seeded from ELN.community
record `https://eln.community/record/019bff9f-df44-71a0-9d3b-11a62730d34c` (offline — embed a small
fixture RO-Crate JSON; do NOT hit the network). The demo: imports the template metadata, extracts a
few fields, creates local Metadatapp CDEs (molecular weight, sample identifier, measurement
technique, instrument, license, dataset creator, file format), maps fields→CDEs, and can produce an
enriched JSON-LD export. Place a tiny sample `.eln`/ro-crate fixture under
`api/tests/Fixtures/eln/` for tests to reuse.

## Tests (api/tests/)
Unit (no DB) under `api/tests/Unit/Crosswalk/`:
- parse a valid `.eln` ZIP, extract ro-crate-metadata.json
- extract PropertyValue fields; extract DefinedTerm/ontology-linked fields
- MappingSuggestionEngine: molecularWeight→molecular_weight EXACT ~0.94
- CrosswalkJsonLdExporter: enriched node shapes; original metadata preserved; rejected excluded
- CrosswalkValidator: missing-CDE report; missing-unit report; completeness score
Api (DB, extends ApiTestCase + Factories + ResetDatabase) under `api/tests/Api/`:
- create ExternalTemplate, import-eln endpoint, create CanonicalForm from extracted fields
- import a local CDE, map one field to one CDE, map whole template to a form
- export enriched JSON-LD endpoint, validate endpoint
- NIH provider tests use a MOCKED HttpClient (Symfony MockHttpClient) — no external calls.
Build a tiny valid `.eln` zip on the fly in tests (ZipArchive) or load from `api/tests/Fixtures/eln/`.

## Frontend (osoma/src/features/template-crosswalks/)
- `TemplateCrosswalkStudioPage.tsx` — three-panel layout (left: imported ELN template structure /
  extracted fields / JSON-LD paths / values / units / ontology terms; middle: canonical form, mapping
  status, validation, missing fields, accept/reject; right: candidate CDE Forms / CDEs / definitions /
  permissible values / ontology mappings / provenance). Each field row: map ELN field→CDE, map
  template→Form, choose mapping type (exact/close/broader/narrower/related/no_match), accept/reject,
  curator notes, datatype mapping, unit mapping, value mapping, "create new Metadatapp CDE".
- `template-crosswalks.api.ts` — `@tanstack/react-query` hooks over `apiFetch` for the endpoints above
  (list/import/suggest/validate/accept/reject/export). Types mirror the JSON shapes.
- Register a lazy route `/template-crosswalks` in `osoma/src/app/router.tsx` (wrap with the same guard
  pattern peers use; under `AppLayout`). Add a Vitest integration test
  `TemplateCrosswalkStudioPage.test.tsx` using the existing MSW/testing setup.
- Frontend talks only to Metadatapp API (`apiFetch`), never to external services directly.

## Documentation
`docs/template-crosswalk-studio.md`: why ELN templates and CDE Forms are complementary; how
Metadatapp links both; what a crosswalk is; importing `.eln`; importing/creating CDEs; mapping fields;
validating semantic completeness; exporting enriched RO-Crate / JSON-LD; known limitations.

## Design rules (MUST hold)
CDEs are versioned, source-aware, semantic (not labels). Never overwrite original ELN metadata.
Always preserve provenance + raw payloads. Allow partial mappings. Allow many CDE Forms per ELN
template and one Form across many templates (crosswalk is the join). Human review required before a
mapping is `accepted`. NIH CDE is ONE provider, not the foundation. Canonical form model is
source-agnostic.
