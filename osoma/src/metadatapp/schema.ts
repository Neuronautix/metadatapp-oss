import { metadatappSpec } from './spec.ts'
import type { SchemaRef } from './openapi-types.ts'

export type FieldKind = 'string' | 'number' | 'boolean' | 'array' | 'object' | 'enum' | 'iri'

export type SchemaField = {
  key: string
  kind: FieldKind
  required: boolean
  readOnly?: boolean
  writeOnly?: boolean
  nullable?: boolean
  enumValues?: string[]
  format?: string
  description?: string
  itemKind?: FieldKind
  itemFormat?: string
  itemNullable?: boolean
  itemRef?: SchemaRef | string
}

export type OpenApiSchema = Record<string, unknown>

const schemaRefPrefix = '#/components/schemas/'

const isObjectSchema = (value: unknown): value is OpenApiSchema =>
  Boolean(value && typeof value === 'object' && !Array.isArray(value))

const normalizeType = (value: unknown): string[] => {
  if (!value) return []
  if (Array.isArray(value)) return value.map(String)
  return [String(value)]
}

const normalizeSchemaRef = (ref: string): SchemaRef | string => ref.replace(schemaRefPrefix, '')

const mergeSchema = (target: OpenApiSchema, source: OpenApiSchema): OpenApiSchema => {
  const properties = {
    ...(isObjectSchema(target.properties) ? target.properties : {}),
    ...(isObjectSchema(source.properties) ? source.properties : {}),
  }
  const required = [
    ...(Array.isArray(target.required) ? target.required.map(String) : []),
    ...(Array.isArray(source.required) ? source.required.map(String) : []),
  ]

  return {
    ...target,
    ...source,
    ...(Object.keys(properties).length ? { properties } : {}),
    ...(required.length ? { required: Array.from(new Set(required)) } : {}),
  }
}

export const resolveSchema = (
  schema: unknown,
  seen = new Set<string>()
): OpenApiSchema | undefined => {
  if (!isObjectSchema(schema)) return undefined

  const ref = schema.$ref
  if (typeof ref === 'string') {
    if (!ref.startsWith(schemaRefPrefix) || seen.has(ref)) return schema
    const next = getSchemaByRef(ref)
    return resolveSchema(next, new Set([...seen, ref])) ?? schema
  }

  const allOf = schema.allOf
  if (Array.isArray(allOf)) {
    const merged = allOf.reduce<OpenApiSchema>((acc, item) => {
      const resolved = resolveSchema(item, seen)
      return resolved ? mergeSchema(acc, resolved) : acc
    }, {})

    const ownSchema = { ...schema }
    delete ownSchema.allOf
    return mergeSchema(merged, ownSchema)
  }

  return schema
}

const toKind = (property: Record<string, unknown> | undefined): FieldKind => {
  const resolved = resolveSchema(property) ?? property
  if (!resolved) return 'string'
  if (resolved.enum) return 'enum'
  if (resolved.format === 'iri-reference') return 'iri'
  if (resolved.items || normalizeType(resolved.type).includes('array')) return 'array'
  if (normalizeType(resolved.type).includes('boolean')) return 'boolean'
  if (normalizeType(resolved.type).includes('number') || normalizeType(resolved.type).includes('integer')) return 'number'
  if (normalizeType(resolved.type).includes('object') || resolved.properties || resolved.$ref) return 'object'
  return 'string'
}

const isNullable = (schema: OpenApiSchema | undefined): boolean =>
  Boolean(schema?.nullable) || normalizeType(schema?.type).includes('null')

const getItemRef = (schema: OpenApiSchema | undefined): SchemaRef | string | undefined => {
  const ref = schema?.$ref
  return typeof ref === 'string' && ref.startsWith(schemaRefPrefix) ? normalizeSchemaRef(ref) : undefined
}

export const getResolvedSchemaByRef = (ref?: SchemaRef | string) => {
  const schema = getSchemaByRef(ref)
  return resolveSchema(schema) ?? null
}

const getSchemaProperties = (schema: OpenApiSchema | null | undefined): Record<string, OpenApiSchema> => {
  const properties = schema?.properties
  if (!isObjectSchema(properties)) return {}
  return properties as Record<string, OpenApiSchema>
}

const getRequired = (schema: OpenApiSchema | null | undefined): Set<string> =>
  new Set<string>((Array.isArray(schema?.required) ? schema.required : []).map(String))

const toSchemaField = (key: string, property: OpenApiSchema, required: Set<string>): SchemaField => {
  const resolved = resolveSchema(property) ?? property
  const itemSchema = resolveSchema(resolved.items) ?? (isObjectSchema(resolved.items) ? resolved.items : undefined)
  const field: SchemaField = {
    key,
    kind: toKind(resolved),
    required: required.has(key),
    readOnly: Boolean(resolved.readOnly),
    writeOnly: Boolean(resolved.writeOnly),
    nullable: isNullable(resolved),
    format: resolved.format as string | undefined,
    description: resolved.description as string | undefined,
    itemKind: itemSchema ? toKind(itemSchema) : undefined,
    itemFormat: itemSchema?.format as string | undefined,
    itemNullable: isNullable(itemSchema),
    itemRef: getItemRef(isObjectSchema(resolved.items) ? resolved.items : undefined),
  }

  if (resolved.enum) {
    field.enumValues = (resolved.enum as unknown[]).map(String)
  }

  return field
}

export const getSchemaByRef = (ref?: SchemaRef | string) => {
  if (!ref) return null
  const key = normalizeSchemaRef(ref)
  return metadatappSpec.components?.schemas?.[key] ?? null
}

export const getSchemaFields = (ref?: SchemaRef | string): SchemaField[] => {
  const schema = getResolvedSchemaByRef(ref)
  if (!schema) return []
  const required = getRequired(schema)
  const properties = getSchemaProperties(schema)
  const fields = Object.keys(properties)
    .filter((key) => !['@context', '@type'].includes(key))
    .map((key) => toSchemaField(key, properties[key], required))

  return fields.sort((a, b) => Number(b.required) - Number(a.required))
}

export const getPreferredListFields = (fields: SchemaField[]): SchemaField[] => {
  const priorities = ['id', 'name', 'label', 'title', 'code', 'type', 'species', 'startAt', 'endAt']
  const byKey = new Map(fields.map((field) => [field.key, field]))
  const selected: SchemaField[] = []

  priorities.forEach((key) => {
    const field = byKey.get(key)
    if (field) selected.push(field)
  })

  if (selected.length >= 4) return selected.slice(0, 4)

  fields.forEach((field) => {
    if (selected.length >= 4) return
    if (!selected.includes(field) && field.key !== '@id') {
      selected.push(field)
    }
  })

  return selected.slice(0, 4)
}
