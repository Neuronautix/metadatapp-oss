# metadatapp-fair-validator

A small **MCP server** (Model Context Protocol) that audits Metadatapp's FAIR² JSON-LD exports and flags common semantic-depth gaps, most importantly the *"ontology theatre"* problem (vocabularies declared in `@context` but never used in the payload).

## Why

Metadatapp's `Fair2JsonLdBuilder` declares ten vocabularies (BioSchemas, OBI, QUDT, NCBITaxon, MGI, PATO, PROV-O, schema.org, Croissant, FAIR²). The audit found that several of those are declared but rarely *used*: procedures fall back to `schema:Thing`, measurements lack `qudt:hasUnit`, genotypes are free strings rather than MGI IRIs, etc. This validator detects those gaps mechanically so they cannot regress as the FAIR² builder evolves.

## What it exposes

Four MCP tools, callable from any MCP client (Claude Code, Claude Desktop, Cursor, custom clients).

### `validate_fair_jsonld`

Input: `{ jsonld: string | object }` — a FAIR² JSON-LD document or a JSON string.
Output: `{ valid, issues, summary: { declaredVocabs, usedVocabs, unusedVocabs, coverageRatio } }`.

Detects:

- Vocabularies declared in `@context` but never used in the `@graph` (warnings).
- Scientific vocabularies (NCBITaxon, MGI, PATO, OBI, QUDT, PROV) declared but unused — promoted to **errors** because that's the audit-relevant case.
- Malformed identifiers (ORCID, DOI, ROR, NCBITaxon CURIEs).

### `check_ontology_depth`

Input: `{ jsonld: string | object, expectations?: { species, units, procedureTyping, genotype } }`.
Output: `{ verdicts: [{ check, passed, evidence?, details? }], passed, failed }`.

Per-domain verdicts:

- `species_iri` — every subject-like node carries an NCBITaxon IRI or `bioschemas:taxonomicRange`.
- `qudt_units` — every measurement-like node carries `qudt:hasUnit` / `unitText`.
- `procedure_typing` — procedure-like nodes are typed beyond `schema:Thing`.
- `genotype_iri` — genotype values resolve to MGI IRIs.

### `list_known_vocabularies`

Returns the prefixes the validator knows about, with their canonical namespace IRIs and example types/properties. Useful as a self-documenting endpoint.

### `validate_cedar_schema_compatibility`

Input: `{ jsonld: string | object }`.
Output: `{ valid, issues, summary, cedarTemplate }`.

Builds a CEDAR template skeleton from Metadatapp JSON-LD and reports mapping diagnostics so the exported structure can be reviewed before passing it to CEDAR MCP workflows (`cedar-rest-mcp` import/export).

## Quick start

```bash
cd tools/metadatapp-fair-validator
pnpm install
pnpm build
pnpm test
```

Tests use `node --test` against the two fixtures shipped in `src/fixtures/`:

- `fair2-experiment.example.jsonld` — every declared vocab is used. Expected: zero issues.
- `fair2-experiment.broken.jsonld` — same `@context`, but procedures are `schema:Thing`, measurements lack units, genotype is `"Shank3 KO"` instead of an MGI IRI. Expected: at least one `FAIR_011` (scientific theatre) issue.

## Register the MCP server

### Claude Code or another MCP client

```json
{
  "mcpServers": {
    "metadatapp-fair": {
      "command": "node",
      "args": ["/absolute/path/to/metadatapp/tools/metadatapp-fair-validator/dist/server.js"]
    }
  }
}
```

### Claude Desktop (`claude_desktop_config.json`)

Same shape — the `mcpServers` key.

After a restart, the four tools (`validate_fair_jsonld`, `check_ontology_depth`, `list_known_vocabularies`, `validate_cedar_schema_compatibility`) should appear in the tool palette.

## Example invocation

Once registered, an agent can call:

```
@metadatapp-fair validate_fair_jsonld { jsonld: <paste a FAIR² export here> }
```

On the broken fixture, the result includes:

```json
{
  "valid": false,
  "summary": {
    "coverageRatio": 0.3,
    "unusedVocabs": ["mgi", "ncbitaxon", "obi", "pato", "prov", "qudt", "unit"]
  },
  "issues": [
    {
      "level": "error",
      "code": "FAIR_011",
      "message": "Scientific vocabulary \"qudt\" is declared in @context but absent from the @graph — this is exactly the \"ontology theatre\" pattern Metadatapp's audit flags.",
      "path": "@context"
    }
  ]
}
```

## Roadmap

These are deferred so the first version stays small and unambiguous:

- **ARRIVE Essential-10 validator** (audit MD-405) — given a study graph, check that every essential ARRIVE item has a corresponding node/property.
- **RO-Crate manifest conformance** — validate `ro-crate-metadata.json` `hasPart` covers every payload file.
- **Suggestion generator** — given a "broken" issue, emit a JSON Patch that fixes it (e.g. add `qudt:hasUnit` based on declared `unitText`).
- **Provenance graph completeness** — every entity has at least one `prov:wasGeneratedBy` / `prov:wasDerivedFrom` link.

## Limitations

- Offline-only: remote `@context` URLs are not fetched; declarations from external contexts are reported as `info`, not validated.
- The validator inspects the *compact* JSON-LD form Metadatapp emits today. A pre-expansion pass (via the official `jsonld` library) would catch more nuanced cases but adds a heavy dependency that's out of scope for v0.1.
- Identifier-shape checks are structural, not authoritative (e.g. ORCID checksum is not verified).

## Layout

```
tools/metadatapp-fair-validator/
├── package.json
├── tsconfig.json
├── README.md
├── src/
│   ├── server.ts                 MCP server (stdio transport)
│   ├── knownVocabularies.ts      Vocabulary registry
│   ├── types.ts
│   ├── validators/
│   │   ├── contextUsage.ts       Declared-but-unused detection
│   │   ├── cedarSchemaCompatibility.ts  CEDAR import/export mapping checks
│   │   ├── ontologyDepth.ts      Per-domain semantic checks
│   │   └── identifierShape.ts    ORCID/DOI/ROR/NCBITaxon shape checks
│   └── fixtures/
│       ├── fair2-experiment.example.jsonld    "good" payload
│       └── fair2-experiment.broken.jsonld     "ontology theatre" payload
└── test/
    ├── cedarSchemaCompatibility.test.ts
    ├── contextUsage.test.ts
    └── ontologyDepth.test.ts
```

## License

AGPL-3.0-or-later, same as the rest of Metadatapp.
