import type { JsonLdDocument, JsonLdNode, JsonLdValue, ValidationIssue } from '../types.js'

/**
 * Cheap structural checks on persistent identifiers commonly emitted by
 * Metadatapp's exporters: ORCID, DOI, ROR, NCBITaxon IRIs.
 *
 * The intent is not full IRI validation — just to catch obvious mistakes
 * (orcid given as a name, doi without the 10. prefix, etc.).
 */
export function checkIdentifierShapes(doc: unknown): ValidationIssue[] {
  if (!isObject(doc)) return []
  const issues: ValidationIssue[] = []
  walk(doc as JsonLdDocument, (path, node) => {
    if (!isObject(node)) return
    checkOrcid(node, path, issues)
    checkDoi(node, path, issues)
    checkRor(node, path, issues)
    checkNcbiTaxon(node, path, issues)
  })
  return issues
}

function checkOrcid(node: JsonLdNode, path: string, issues: ValidationIssue[]): void {
  const orcidKeys = ['identifier', 'schema:identifier', '@id']
  for (const k of orcidKeys) {
    const v = (node as Record<string, JsonLdValue | undefined>)[k]
    if (typeof v !== 'string') continue
    if (v.includes('orcid.org')) {
      // Should match https://orcid.org/0000-0000-0000-0000
      if (!/orcid\.org\/\d{4}-\d{4}-\d{4}-\d{3}[\dX]/.test(v)) {
        issues.push({
          level: 'warning',
          code: 'FAIR_020',
          message: `ORCID-like identifier "${v}" does not match the canonical 0000-0000-0000-000X form.`,
          path,
        })
      }
    }
  }
}

function checkDoi(node: JsonLdNode, path: string, issues: ValidationIssue[]): void {
  for (const [k, v] of Object.entries(node)) {
    if (typeof v !== 'string') continue
    if (v.startsWith('doi:') && !/^doi:10\..+\/.+/.test(v)) {
      issues.push({
        level: 'warning',
        code: 'FAIR_021',
        message: `DOI "${v}" at "${k}" is missing the 10.<registrant>/<suffix> shape.`,
        path: `${path}/${k}`,
      })
    }
    if (v.includes('doi.org/') && !/doi\.org\/10\./.test(v)) {
      issues.push({
        level: 'warning',
        code: 'FAIR_021',
        message: `DOI URL "${v}" at "${k}" does not contain the 10. prefix.`,
        path: `${path}/${k}`,
      })
    }
  }
}

function checkRor(node: JsonLdNode, path: string, issues: ValidationIssue[]): void {
  for (const [k, v] of Object.entries(node)) {
    if (typeof v !== 'string') continue
    if (v.includes('ror.org/') && !/ror\.org\/[a-z0-9]{9}/.test(v)) {
      issues.push({
        level: 'warning',
        code: 'FAIR_022',
        message: `ROR identifier "${v}" at "${k}" does not match the canonical 9-character form.`,
        path: `${path}/${k}`,
      })
    }
  }
}

function checkNcbiTaxon(node: JsonLdNode, path: string, issues: ValidationIssue[]): void {
  for (const [k, v] of Object.entries(node)) {
    if (typeof v !== 'string') continue
    if (v.startsWith('ncbitaxon:') && !/^ncbitaxon:\d+$/.test(v)) {
      issues.push({
        level: 'warning',
        code: 'FAIR_023',
        message: `NCBITaxon CURIE "${v}" at "${k}" is malformed; expected ncbitaxon:<digits>.`,
        path: `${path}/${k}`,
      })
    }
  }
}

function walk(
  value: JsonLdValue | JsonLdDocument,
  visit: (path: string, node: JsonLdNode) => void,
  path = '$',
): void {
  if (value === null || value === undefined) return
  if (Array.isArray(value)) {
    value.forEach((v, i) => walk(v, visit, `${path}[${i}]`))
    return
  }
  if (isObject(value)) {
    visit(path, value as unknown as JsonLdNode)
    for (const [k, v] of Object.entries(value)) {
      if (k === '@context') continue
      walk(v as JsonLdValue, visit, `${path}/${k}`)
    }
  }
}

function isObject(x: unknown): x is Record<string, unknown> {
  return typeof x === 'object' && x !== null && !Array.isArray(x)
}
