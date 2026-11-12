/**
 * Vocabularies that Metadatapp's FAIR² export declares in @context.
 * Each entry maps a canonical prefix to its namespace IRI(s).
 * The validator uses this to identify whether a declared namespace
 * is actually exercised in the @graph payload.
 */
export interface KnownVocabulary {
  prefix: string
  namespaces: string[]
  description: string
  /** Examples of types/properties commonly used from this vocab. */
  examples: string[]
}

export const KNOWN_VOCABULARIES: Readonly<KnownVocabulary[]> = [
  {
    prefix: 'schema',
    namespaces: ['https://schema.org/', 'http://schema.org/'],
    description: 'schema.org — generic types (Thing, Person, Dataset, …).',
    examples: ['schema:Person', 'schema:Dataset', 'schema:identifier'],
  },
  {
    prefix: 'sc',
    namespaces: ['https://schema.org/', 'http://schema.org/'],
    description: 'Alias of schema.org used in some Metadatapp graphs.',
    examples: ['sc:Person', 'sc:Thing'],
  },
  {
    prefix: 'bioschemas',
    namespaces: ['https://bioschemas.org/'],
    description: 'BioSchemas profiles (Study, BioSample, LabProtocol, …).',
    examples: ['bioschemas:Study', 'bioschemas:BioSample', 'bioschemas:LabProtocol'],
  },
  {
    prefix: 'obi',
    namespaces: ['http://purl.obolibrary.org/obo/OBI_', 'http://purl.obolibrary.org/obo/'],
    description: 'Ontology for Biomedical Investigations.',
    examples: ['obi:OBI_0100026 (organism)', 'obi:OBI_0000091 (stereotaxic surgery)'],
  },
  {
    prefix: 'qudt',
    namespaces: ['http://qudt.org/schema/qudt/'],
    description: 'QUDT — units, quantities and dimensions.',
    examples: ['qudt:hasUnit', 'qudt:Quantity', 'qudt:value'],
  },
  {
    prefix: 'unit',
    namespaces: ['http://qudt.org/vocab/unit/'],
    description: 'QUDT unit IRIs.',
    examples: ['unit:KiloGM', 'unit:DEG_C', 'unit:MIN'],
  },
  {
    prefix: 'ncbitaxon',
    namespaces: [
      'http://purl.obolibrary.org/obo/NCBITaxon_',
      'http://purl.bioontology.org/ontology/NCBITAXON/',
    ],
    description: 'NCBI Taxonomy IRIs (species and lineage).',
    examples: ['ncbitaxon:10090 (Mus musculus)', 'ncbitaxon:10116 (Rattus norvegicus)'],
  },
  {
    prefix: 'mgi',
    namespaces: ['http://www.informatics.jax.org/marker/MGI:'],
    description: 'Mouse Genome Informatics — genotypes / alleles / strains.',
    examples: ['mgi:MGI:107889 (Shank3)'],
  },
  {
    prefix: 'pato',
    namespaces: ['http://purl.obolibrary.org/obo/PATO_'],
    description: 'Phenotype And Trait Ontology — qualities (sex, behaviour, …).',
    examples: ['pato:PATO_0000384 (male)', 'pato:PATO_0000383 (female)'],
  },
  {
    prefix: 'prov',
    namespaces: ['http://www.w3.org/ns/prov#'],
    description: 'PROV-O — provenance graph.',
    examples: ['prov:wasDerivedFrom', 'prov:Activity', 'prov:wasGeneratedBy'],
  },
  {
    prefix: 'dcterms',
    namespaces: ['http://purl.org/dc/terms/'],
    description: 'Dublin Core Terms (license, modified, version).',
    examples: ['dcterms:license', 'dcterms:modified', 'dcterms:version'],
  },
  {
    prefix: 'dcat',
    namespaces: ['http://www.w3.org/ns/dcat#'],
    description: 'DCAT — datasets and distributions.',
    examples: ['dcat:Dataset', 'dcat:distribution'],
  },
  {
    prefix: 'cr',
    namespaces: ['http://mlcommons.org/croissant/'],
    description: 'MLCommons Croissant — ML-ready dataset metadata.',
    examples: ['cr:conformsTo', 'cr:RecordSet'],
  },
  {
    prefix: 'fair2',
    namespaces: ['https://w3id.org/fair2/'],
    description: 'Metadatapp FAIR² profile (project-internal).',
    examples: ['fair2:Investigation'],
  },
] as const

/**
 * Returns every prefix that points at the given namespace IRI (or its prefix).
 */
export function prefixesForNamespace(namespace: string): string[] {
  return KNOWN_VOCABULARIES
    .filter((v) => v.namespaces.some((ns) => namespace === ns || namespace.startsWith(ns)))
    .map((v) => v.prefix)
}

/**
 * Returns the canonical KnownVocabulary by prefix, or undefined.
 */
export function vocabByPrefix(prefix: string): KnownVocabulary | undefined {
  return KNOWN_VOCABULARIES.find((v) => v.prefix === prefix)
}
