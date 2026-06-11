import type { ValidationIssue } from '../types.js'

const META_KEYS = new Set(['@context', '@id', '@type', 'id', 'originId'])

type JsonObject = Record<string, unknown>

type CedarChild = {
  key: string
  type: string
  name: string
  children?: CedarChild[]
}

type CedarTemplate = {
  type: 'template'
  name: string
  description: string
  children: CedarChild[]
}

type CedarCompatibilityResult = {
  valid: boolean
  issues: ValidationIssue[]
  summary: {
    metadatappFieldCount: number
    cedarFieldCount: number
    mappedFieldCount: number
    coverageRatio: number
  }
  cedarTemplate?: CedarTemplate
}

const isObject = (value: unknown): value is JsonObject =>
  Boolean(value && typeof value === 'object' && !Array.isArray(value))

const isDateLike = (value: string): boolean =>
  /^\d{4}-\d{2}-\d{2}(T.*)?$/.test(value)

const isIriLike = (value: string): boolean =>
  value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')

const sanitizeKey = (key: string): string => {
  const cleaned = key
    .replace(/^@/, '')
    .replace(/^[^a-zA-Z0-9]+/, '')
    .replace(/[^a-zA-Z0-9 _:-]/g, '')
    .trim()
  return cleaned.length > 0 ? cleaned : 'field'
}

const inferCedarType = (value: unknown): string => {
  if (typeof value === 'boolean') return 'checkbox-field'
  if (typeof value === 'number') return 'numeric-field'
  if (typeof value === 'string') {
    if (isDateLike(value)) return 'temporal-field'
    if (isIriLike(value)) return 'link-field'
    return 'text-field'
  }
  return 'text-field'
}

const buildChildren = (node: JsonObject): CedarChild[] =>
  Object.entries(node)
    .filter(([key]) => !META_KEYS.has(key))
    .map(([key, value]) => {
      if (isObject(value)) {
        const nestedChildren = buildChildren(value)
        return {
          key: sanitizeKey(key),
          type: 'element',
          name: sanitizeKey(key),
          ...(nestedChildren.length > 0 ? { children: nestedChildren } : {}),
        }
      }

      if (Array.isArray(value)) {
        const firstObject = value.find(isObject)
        if (firstObject) {
          const nestedChildren = buildChildren(firstObject)
          return {
            key: sanitizeKey(key),
            type: 'element',
            name: sanitizeKey(key),
            ...(nestedChildren.length > 0 ? { children: nestedChildren } : {}),
          }
        }
      }

      return {
        key: sanitizeKey(key),
        type: inferCedarType(value),
        name: sanitizeKey(key),
      }
    })

const mergeGraphNodes = (doc: JsonObject): { node: JsonObject; fromGraph: boolean } => {
  if (!Array.isArray(doc['@graph'])) {
    return { node: doc, fromGraph: false }
  }

  const merged = doc['@graph'].filter(isObject).reduce<JsonObject>((acc, item) => ({ ...acc, ...item }), {})
  return { node: merged, fromGraph: true }
}

const flattenChildren = (children: CedarChild[]): number =>
  children.reduce((count, child) => count + 1 + (child.children ? flattenChildren(child.children) : 0), 0)

export function validateCedarSchemaCompatibility(input: unknown): CedarCompatibilityResult {
  if (!isObject(input)) {
    return {
      valid: false,
      issues: [
        {
          level: 'error',
          code: 'CEDAR_001',
          message: 'Input must be a JSON object (compact JSON-LD or @graph document).',
          path: '$',
        },
      ],
      summary: {
        metadatappFieldCount: 0,
        cedarFieldCount: 0,
        mappedFieldCount: 0,
        coverageRatio: 0,
      },
    }
  }

  const { node, fromGraph } = mergeGraphNodes(input)
  const warningPath = fromGraph ? '@graph' : '$'
  const metadatappFieldCount = Object.keys(node).filter((k) => !META_KEYS.has(k)).length
  const children = buildChildren(node)
  const cedarFieldCount = flattenChildren(children)
  const mappedFieldCount = children.filter((child) => child.type !== 'element').length
  const coverageRatio = metadatappFieldCount > 0 ? mappedFieldCount / metadatappFieldCount : 0

  const issues: ValidationIssue[] = []

  if (0 === metadatappFieldCount) {
    issues.push({
      level: 'warning',
      code: 'CEDAR_002',
      message: 'No metadata fields found to map into a CEDAR template.',
      path: warningPath,
    })
  }

  if (coverageRatio < 1) {
    issues.push({
      level: 'warning',
      code: 'CEDAR_003',
      message: 'Some metadata fields could not be mapped at top level and were flattened as nested CEDAR elements.',
      path: warningPath,
    })
  }

  const templateName =
    (typeof node['schema:name'] === 'string' && node['schema:name'].trim().length > 0
      ? node['schema:name']
      : undefined) ??
    (typeof node['name'] === 'string' && node['name'].trim().length > 0 ? node['name'] : undefined) ??
    'Metadatapp Export Template'

  const cedarTemplate: CedarTemplate = {
    type: 'template',
    name: templateName,
    description:
      'Generated CEDAR template skeleton from Metadatapp JSON-LD to support cedar-rest-mcp import/export workflows.',
    children,
  }

  return {
    valid: issues.every((i) => i.level !== 'error'),
    issues,
    summary: {
      metadatappFieldCount,
      cedarFieldCount,
      mappedFieldCount,
      coverageRatio,
    },
    cedarTemplate,
  }
}
