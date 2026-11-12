import { metadatappSpec } from './spec.ts'
import type { SchemaRef } from './openapi-types.ts'
import { resolveSchema, type OpenApiSchema } from './schema.ts'

export type ResourceRegistryEntry = {
  key: string
  label: string
  collectionPath: string
  itemPath?: string
  listSchemaRef?: SchemaRef
  itemSchemaRef?: SchemaRef
  createSchemaRef?: SchemaRef
  updateSchemaRef?: SchemaRef
  supports: {
    list: boolean
    create: boolean
    read: boolean
    update: boolean
    delete: boolean
    updateMethod?: 'PATCH' | 'PUT'
  }
}

const titleCase = (value: string) =>
  value
    .split(/[_-]/g)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')

const normalizeRef = (ref?: string): SchemaRef | undefined => {
  if (!ref) return undefined
  return ref.replace('#/components/schemas/', '') as SchemaRef
}

const getSchemaRef = (schema: unknown): SchemaRef | undefined => {
  const ref = (schema as OpenApiSchema | undefined)?.$ref
  return typeof ref === 'string' ? normalizeRef(ref) : undefined
}

const getResponseSchema = (node: Record<string, unknown> | undefined): OpenApiSchema | undefined =>
  (node?.responses as Record<string, Record<string, Record<string, Record<string, unknown>>>> | undefined)?.['200']?.content?.[
    'application/ld+json'
  ]?.schema as OpenApiSchema | undefined

const extractListSchemaRef = (node: Record<string, unknown> | undefined): SchemaRef | undefined => {
  const schema = resolveSchema(getResponseSchema(node))
  const members = (schema?.properties as Record<string, OpenApiSchema> | undefined)?.['hydra:member']
  return getSchemaRef(members?.items)
}

const extractItemSchemaRef = (node: Record<string, unknown> | undefined): SchemaRef | undefined => {
  return getSchemaRef(getResponseSchema(node))
}

const extractRequestSchemaRef = (node: Record<string, unknown> | undefined): SchemaRef | undefined => {
  const content = (node?.requestBody as Record<string, Record<string, Record<string, unknown>>> | undefined)?.content
  const schema =
    (content?.['application/ld+json']?.schema as OpenApiSchema | undefined) ??
    (content?.['application/merge-patch+json']?.schema as OpenApiSchema | undefined) ??
    (content?.['application/json']?.schema as OpenApiSchema | undefined)
  return getSchemaRef(schema)
}

const resourceLabelOverrides: Record<string, string> = {
  projects: 'Investigations',
  experiments: 'Studies',
  procedures: 'Assays',
}

const buildRegistry = (): ResourceRegistryEntry[] => {
  const paths = metadatappSpec.paths ?? {}
  const collectionPaths = Object.keys(paths).filter((path) => !path.includes('{'))

  return collectionPaths.map((path) => {
    const key = path.replace(/^\//, '')
    const itemPath = `${path}/{id}`
    const collectionNode = paths[path] ?? {}
    const itemNode = paths[itemPath] ?? {}

    const listSchemaRef = extractListSchemaRef(collectionNode.get as Record<string, unknown> | undefined)
    const itemSchemaRef = extractItemSchemaRef(itemNode.get as Record<string, unknown> | undefined)
    const createSchemaRef = extractRequestSchemaRef(collectionNode.post as Record<string, unknown> | undefined)
    const patchSchemaRef = extractRequestSchemaRef(itemNode.patch as Record<string, unknown> | undefined)
    const putSchemaRef = extractRequestSchemaRef(itemNode.put as Record<string, unknown> | undefined)

    const updateSchemaRef = patchSchemaRef ?? putSchemaRef
    const updateMethod = itemNode.patch ? 'PATCH' : itemNode.put ? 'PUT' : undefined
    const label = resourceLabelOverrides[key] ?? titleCase(key)

    return {
      key,
      label,
      collectionPath: path,
      itemPath: paths[itemPath] ? itemPath : undefined,
      listSchemaRef,
      itemSchemaRef,
      createSchemaRef,
      updateSchemaRef,
      supports: {
        list: Boolean(collectionNode.get),
        create: Boolean(collectionNode.post),
        read: Boolean(itemNode.get),
        update: Boolean(itemNode.patch || itemNode.put),
        delete: Boolean(itemNode.delete),
        updateMethod,
      },
    }
  })
}

export const resourceRegistry = buildRegistry().sort((a, b) => a.label.localeCompare(b.label))

export const getResourceEntry = (key: string) => resourceRegistry.find((entry) => entry.key === key)
