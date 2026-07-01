import type {
  JsonLdDocument,
  JsonLdNode,
  JsonLdValue,
  OntologyDepthExpectations,
  OntologyDepthResult,
  OntologyVerdict,
} from '../types.js'

/**
 * Per-domain checks: for each scientific concept Metadatapp claims to model
 * (species, unit, procedure typing, genotype), assert that the @graph carries
 * the expected ontology IRIs rather than free strings or sc:Thing fallbacks.
 */
export function checkOntologyDepth(
  doc: unknown,
  expectations: OntologyDepthExpectations = {
    species: true,
    units: true,
    procedureTyping: true,
    genotype: true,
  },
): OntologyDepthResult {
  if (!isObject(doc)) {
    return {
      verdicts: [
        { check: 'document_shape', passed: false, evidence: 'Document is not an object.' },
      ],
      passed: 0,
      failed: 1,
    }
  }

  const nodes = collectNodes(doc as JsonLdDocument)
  const verdicts: OntologyVerdict[] = []

  if (expectations.species) verdicts.push(checkSpecies(nodes))
  if (expectations.units) verdicts.push(checkUnits(nodes))
  if (expectations.procedureTyping) verdicts.push(checkProcedureTyping(nodes))
  if (expectations.genotype) verdicts.push(checkGenotype(nodes))

  const passed = verdicts.filter((v) => v.passed).length
  return { verdicts, passed, failed: verdicts.length - passed }
}

function collectNodes(doc: JsonLdDocument): JsonLdNode[] {
  const graph = doc['@graph']
  if (Array.isArray(graph)) return graph as JsonLdNode[]
  // Compact form: doc itself is a node.
  return [doc as unknown as JsonLdNode]
}

function nodeTypes(node: JsonLdNode): string[] {
  const t = node['@type']
  if (!t) return []
  return Array.isArray(t) ? t : [t]
}

function isSubjectLike(node: JsonLdNode): boolean {
  const types = nodeTypes(node)
  return types.some(
    (t) =>
      t === 'bioschemas:BioSample' ||
      t === 'BioSample' ||
      t === 'sc:BioChemEntity' ||
      t === 'schema:BioChemEntity' ||
      /Subject/i.test(t),
  )
}

function isMeasurementLike(node: JsonLdNode): boolean {
  const types = nodeTypes(node)
  if (types.some((t) => /Measurement|Quantity|TimeSeries|HcmMeasure|Observation/i.test(t))) {
    return true
  }
  // Property-based detection — covers the Metadatapp pattern of bare
  // schema:Thing nodes that carry a numeric value.
  const valueKeys = ['value', 'schema:value', 'sc:value', 'qudt:value', 'measuredValue']
  if (valueKeys.some((k) => k in node)) {
    return true
  }
  return false
}

function isProcedureLike(node: JsonLdNode): boolean {
  const types = nodeTypes(node)
  // The audit calls out procedures (Behavior, Surgery, Treatment) being typed
  // as bare schema:Thing. So we look for those concepts via @id or property
  // shape rather than only @type.
  if (
    types.some((t) =>
      /Procedure|Surgery|Treatment|Behavior|Behaviour|LabProtocol|Protocol/i.test(t),
    )
  ) {
    return true
  }
  const id = node['@id']
  if (typeof id === 'string' && /procedure|surgery|treatment|behavior|protocol/i.test(id)) {
    return true
  }
  return false
}

function checkSpecies(nodes: JsonLdNode[]): OntologyVerdict {
  const subjects = nodes.filter(isSubjectLike)
  if (subjects.length === 0) {
    return {
      check: 'species_iri',
      passed: true,
      details: 'No subject-like nodes found; check skipped.',
    }
  }

  const passing = subjects.filter((n) => hasSpeciesIri(n))
  const ratio = passing.length / subjects.length
  if (ratio === 1) {
    return {
      check: 'species_iri',
      passed: true,
      evidence: `${subjects.length}/${subjects.length} subject nodes carry an NCBITaxon IRI or bioschemas:taxonomicRange.`,
    }
  }
  const missing = subjects.find((n) => !hasSpeciesIri(n))
  return {
    check: 'species_iri',
    passed: false,
    evidence: `Only ${passing.length}/${subjects.length} subjects carry an NCBITaxon IRI.`,
    details: missing?.['@id']
      ? `Example without IRI: ${missing['@id']}`
      : 'Add `sameAs: ncbitaxon:10090` (Mus musculus) or set `bioschemas:taxonomicRange` to a NCBITaxon URL.',
  }
}

function hasSpeciesIri(node: JsonLdNode): boolean {
  const candidates = collectStringValues(node, [
    'sameAs',
    'schema:sameAs',
    'sc:sameAs',
    'taxonomicRange',
    'bioschemas:taxonomicRange',
    '@id',
  ])
  return candidates.some(
    (v) =>
      v.includes('NCBITaxon_') ||
      v.includes('NCBITAXON/') ||
      v.startsWith('ncbitaxon:'),
  )
}

function checkUnits(nodes: JsonLdNode[]): OntologyVerdict {
  const measurements = nodes.filter(isMeasurementLike)
  if (measurements.length === 0) {
    return {
      check: 'qudt_units',
      passed: true,
      details: 'No measurement-like nodes found; check skipped.',
    }
  }

  const passing = measurements.filter(hasUnit)
  const ratio = passing.length / measurements.length
  if (ratio === 1) {
    return {
      check: 'qudt_units',
      passed: true,
      evidence: `${measurements.length}/${measurements.length} measurement nodes carry a qudt:hasUnit / unitText.`,
    }
  }
  return {
    check: 'qudt_units',
    passed: false,
    evidence: `Only ${passing.length}/${measurements.length} measurement nodes carry a unit.`,
    details:
      'Add `qudt:hasUnit unit:KiloGM` (or appropriate IRI) on each numeric measurement node.',
  }
}

function hasUnit(node: JsonLdNode): boolean {
  if ('qudt:hasUnit' in node) return true
  if ('qudt:unit' in node) return true
  if ('schema:unitText' in node) return true
  if ('unitText' in node) return true
  if ('schema:unitCode' in node) return true
  return false
}

function checkProcedureTyping(nodes: JsonLdNode[]): OntologyVerdict {
  const procedures = nodes.filter(isProcedureLike)
  if (procedures.length === 0) {
    return {
      check: 'procedure_typing',
      passed: true,
      details: 'No procedure-like nodes found; check skipped.',
    }
  }
  const generic = procedures.filter((n) => {
    const types = nodeTypes(n)
    return (
      types.length === 0 ||
      types.every((t) => t === 'schema:Thing' || t === 'sc:Thing' || t === 'Thing')
    )
  })
  if (generic.length === 0) {
    return {
      check: 'procedure_typing',
      passed: true,
      evidence: `${procedures.length} procedure-like nodes are typed beyond schema:Thing.`,
    }
  }
  return {
    check: 'procedure_typing',
    passed: false,
    evidence: `${generic.length}/${procedures.length} procedure-like nodes are typed as bare schema:Thing.`,
    details:
      'Use bioschemas:LabProtocol or an OBI procedure IRI (e.g. obi:OBI_0000091 for stereotaxic surgery).',
  }
}

function checkGenotype(nodes: JsonLdNode[]): OntologyVerdict {
  const candidates = nodes
    .map((n) => collectStringValues(n, ['genotype', 'schema:genotype', 'bioschemas:genotype']))
    .flat()
  if (candidates.length === 0) {
    return {
      check: 'genotype_iri',
      passed: true,
      details: 'No genotype property found; check skipped.',
    }
  }
  const withIri = candidates.filter(
    (v) => v.startsWith('mgi:') || v.startsWith('http://www.informatics.jax.org/'),
  )
  if (withIri.length === candidates.length) {
    return {
      check: 'genotype_iri',
      passed: true,
      evidence: `${candidates.length} genotype values resolved to MGI IRIs.`,
    }
  }
  return {
    check: 'genotype_iri',
    passed: false,
    evidence: `${candidates.length - withIri.length}/${candidates.length} genotype values are free strings.`,
    details:
      'Resolve genotypes to MGI IRIs (e.g. mgi:MGI:107889 for Shank3) instead of free-text strings.',
  }
}

function collectStringValues(node: JsonLdNode, keys: string[]): string[] {
  const out: string[] = []
  for (const k of keys) {
    const v = (node as Record<string, JsonLdValue | undefined>)[k]
    if (typeof v === 'string') out.push(v)
    else if (Array.isArray(v)) for (const item of v) if (typeof item === 'string') out.push(item)
    else if (isObject(v) && typeof v['@id'] === 'string') out.push(v['@id'] as string)
  }
  return out
}

function isObject(x: unknown): x is Record<string, unknown> {
  return typeof x === 'object' && x !== null && !Array.isArray(x)
}
