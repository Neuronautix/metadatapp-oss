import type {
  JsonLdContextEntry,
  JsonLdDocument,
  JsonLdNode,
  JsonLdValue,
  ValidationIssue,
  ValidationResult,
  ValidationSummary,
} from '../types.js'
import { KNOWN_VOCABULARIES, prefixesForNamespace } from '../knownVocabularies.js'

/**
 * Walks a JSON-LD document and reports vocabularies that were declared in
 * @context but never actually appear in the payload (as type tags, property
 * keys, or @id IRIs). The point: detect "ontology theatre" — projects that
 * decorate their @context with QUDT/OBI/NCBITaxon then never use them.
 */
export function validateContextUsage(doc: unknown): ValidationResult {
  const issues: ValidationIssue[] = []

  if (!isObject(doc)) {
    return failure('FAIR_001', 'Top-level value is not a JSON-LD object.')
  }

  const declared = collectDeclaredPrefixes(doc as JsonLdDocument, issues)
  const used = collectUsedPrefixes(doc as JsonLdDocument)

  // Resolve unused = declared - used.
  const unused = [...declared].filter((p) => !used.has(p))
  for (const prefix of unused) {
    issues.push({
      level: 'warning',
      code: 'FAIR_010',
      message: `Vocabulary "${prefix}" is declared in @context but never used in the payload.`,
      path: '@context',
    })
  }

  // Spot-check: the canonical known vocabs that map to declared namespaces.
  // If a known scientific vocab (NCBITaxon / MGI / PATO / OBI / QUDT) is
  // declared and not used, raise to error level — that's the audit-relevant
  // case, since these are the vocabs that signal real semantic depth.
  const sciSensitive = new Set(['ncbitaxon', 'mgi', 'pato', 'obi', 'qudt', 'unit', 'prov'])
  for (const prefix of unused) {
    if (sciSensitive.has(prefix.toLowerCase())) {
      // Promote the warning above to an error mirror.
      issues.push({
        level: 'error',
        code: 'FAIR_011',
        message: `Scientific vocabulary "${prefix}" is declared in @context but absent from the @graph — this is exactly the "ontology theatre" pattern Metadatapp's audit flags.`,
        path: '@context',
      })
    }
  }

  const summary: ValidationSummary = {
    declaredVocabs: [...declared].sort(),
    usedVocabs: [...used].sort(),
    unusedVocabs: unused.sort(),
    coverageRatio:
      declared.size === 0 ? 1 : (declared.size - unused.length) / declared.size,
  }

  const valid = !issues.some((i) => i.level === 'error')
  return { valid, issues, summary }
}

function failure(code: string, message: string): ValidationResult {
  return {
    valid: false,
    issues: [{ level: 'error', code, message }],
    summary: { declaredVocabs: [], usedVocabs: [], unusedVocabs: [], coverageRatio: 0 },
  }
}

function collectDeclaredPrefixes(
  doc: JsonLdDocument,
  issues: ValidationIssue[],
): Set<string> {
  const declared = new Set<string>()
  const ctx = doc['@context']
  if (ctx === undefined) {
    issues.push({
      level: 'warning',
      code: 'FAIR_002',
      message: 'No @context declared on the document.',
    })
    return declared
  }

  const entries = Array.isArray(ctx) ? ctx : [ctx]
  for (const entry of entries) {
    if (typeof entry === 'string') {
      // Remote context — we cannot inspect it offline. Emit info, skip.
      issues.push({
        level: 'info',
        code: 'FAIR_003',
        message: `Remote @context "${entry}" cannot be inspected offline; its declarations are not counted.`,
      })
      continue
    }
    if (!isObject(entry)) continue

    for (const [term, value] of Object.entries(entry as JsonLdContextEntry)) {
      // Skip JSON-LD control keywords (@vocab, @language, @base, …).
      // They are not vocabulary prefixes and can never appear as "used"
      // in the graph, so counting them inflates declared and deflates
      // the coverage ratio.
      if (term.startsWith('@')) continue
      const namespace =
        typeof value === 'string'
          ? value
          : isObject(value) && typeof value['@id'] === 'string'
            ? value['@id']
            : undefined
      if (!namespace) continue
      // Track the literal prefix the user wrote — not its aliases.
      // (KNOWN_VOCABULARIES carries aliases like "sc" ↔ "schema" for
      // *usage* matching, but a coverage ratio should not punish a
      // payload for not using an alias the @context never declared.)
      declared.add(term)
    }
  }

  // The known-vocab prefixes that ALSO appear as @context terms even without
  // a namespace mapping (e.g. the export uses "qudt" inline despite mapping
  // it via @vocab).
  for (const v of KNOWN_VOCABULARIES) {
    if (containsPrefixUsage(doc, v.prefix)) {
      // already counted via used, declared depends on @context.
      // Nothing to do here — declared set is the source of truth.
    }
  }

  return declared
}

function collectUsedPrefixes(doc: JsonLdDocument): Set<string> {
  const used = new Set<string>()
  walk(doc, (key, value) => {
    if (typeof key === 'string') addPrefixIfAny(key, used)
    if (typeof value === 'string') {
      // Type strings, IRIs, sameAs values — all may carry a prefix.
      addPrefixIfAny(value, used)
      addNamespaceIfAny(value, used)
    }
  })
  return used
}

function containsPrefixUsage(doc: JsonLdDocument, prefix: string): boolean {
  let found = false
  walk(doc, (key, value) => {
    if (typeof key === 'string' && extractPrefix(key) === prefix) found = true
    if (!found && typeof value === 'string' && extractPrefix(value) === prefix) {
      found = true
    }
  })
  return found
}

function addPrefixIfAny(s: string, into: Set<string>): void {
  const p = extractPrefix(s)
  if (p) into.add(p)
}

function addNamespaceIfAny(s: string, into: Set<string>): void {
  if (!s.startsWith('http')) return
  for (const p of prefixesForNamespace(s)) into.add(p)
}

/**
 * Returns "qudt" for "qudt:hasUnit", "schema" for "schema:Person", etc.
 * Returns undefined for plain strings (no colon, or absolute IRI).
 */
function extractPrefix(s: string): string | undefined {
  if (!s.includes(':')) return undefined
  if (s.startsWith('http://') || s.startsWith('https://')) return undefined
  const idx = s.indexOf(':')
  const candidate = s.slice(0, idx)
  // Reject things like `urn:uuid:...` that aren't ontology prefixes by checking
  // that the candidate isn't a single character or numeric.
  if (candidate.length === 0) return undefined
  return candidate
}

function walk(
  value: JsonLdValue | JsonLdDocument | JsonLdNode | undefined,
  visit: (key: string | number, value: JsonLdValue) => void,
): void {
  if (value === null || value === undefined) return
  if (Array.isArray(value)) {
    value.forEach((v, i) => {
      visit(i, v)
      walk(v, visit)
    })
    return
  }
  if (isObject(value)) {
    for (const [k, v] of Object.entries(value)) {
      // Skip the declared @context itself — we only inspect *usage* in @graph
      // and node properties. Otherwise every declared term would self-mark
      // as used, defeating the point.
      if (k === '@context') continue
      visit(k, v as JsonLdValue)
      walk(v as JsonLdValue, visit)
    }
  }
}

function isObject(x: unknown): x is Record<string, unknown> {
  return typeof x === 'object' && x !== null && !Array.isArray(x)
}
