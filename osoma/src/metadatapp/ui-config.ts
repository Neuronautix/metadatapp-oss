import type { SchemaRef } from './openapi-types.ts'
import type { SchemaField } from './schema.ts'
import { getPreferredListFields, getSchemaFields } from './schema.ts'
import type { ResourceRegistryEntry } from './registry.ts'

export type UiInputType =
  | 'text'
  | 'number'
  | 'textarea'
  | 'json'
  | 'select'
  | 'boolean'
  | 'date'
  | 'datetime'
  | 'iri'
  | 'tags'

export type UiLookupSource = 'orcid' | 'ror' | 'ols_ncbitaxon' | 'ols_efo'
export type UiLookupResolver = 'strain'

export type UiLookupConfig = {
  source: UiLookupSource
  mapTo?: 'value' | 'label'
  resolve?: UiLookupResolver
  minLength?: number
  searchWithFormValues?: string[]
  requireEnumValue?: boolean
}

export type UiField = SchemaField & {
  label: string
  input: UiInputType
  placeholder?: string
  helper?: string
  readOnly?: boolean
  hidden?: boolean
  lookup?: UiLookupConfig
}

type AnySchemaRef = SchemaRef | string

export type UiFieldOverride = Partial<Pick<UiField, 'label' | 'input' | 'placeholder' | 'helper' | 'readOnly' | 'hidden' | 'lookup'>>

export type UiResourceConfig<Ref extends AnySchemaRef> = {
  schemaRef: Ref
  list?: string[]
  form?: string[]
  detail?: string[]
  fields?: Partial<Record<string, UiFieldOverride>>
}

type ResourceLabelForm = 'singular' | 'plural'

const resourceTerminology: Record<string, Record<ResourceLabelForm, string>> = {
  projects: { singular: 'Investigation', plural: 'Investigations' },
  experiments: { singular: 'Study', plural: 'Studies' },
  procedures: { singular: 'Assay', plural: 'Assays' },
}

export const getResourceDisplayLabel = (
  entry: Pick<ResourceRegistryEntry, 'key' | 'label'> | undefined,
  form: ResourceLabelForm = 'plural'
) => {
  if (!entry) return ''
  return resourceTerminology[entry.key]?.[form] ?? entry.label
}

const defineUiConfig = <Ref extends AnySchemaRef>(config: UiResourceConfig<Ref>) => config

export const uiConfigByResource = {
  investigations: defineUiConfig({
    schemaRef: 'Investigation.jsonld',
    list: ['name', 'goal', 'startAt', 'endAt'],
    form: [
      'name',
      'goal',
      'startAt',
      'endAt',
      'description',
      'repositoryLink',
      'laboratories',
      'studies',
      'externalId',
      'elaftwExternalId',
      'fair3rExternalId',
    ],
    fields: {
      name: { label: 'Investigation Name', placeholder: 'e.g. Neural imaging study' },
      goal: { input: 'textarea', placeholder: 'Short investigation objective' },
      startAt: { input: 'date' },
      endAt: { input: 'date' },
      description: { input: 'textarea' },
      repositoryLink: { label: 'Repository', placeholder: 'https://…' },
      laboratories: { input: 'tags', placeholder: '/laboratories/{id}' },
      studies: { input: 'tags', placeholder: '/procedures/{id}' },
    },
  }),
  experiments: defineUiConfig({
    schemaRef: 'Study.jsonld-study.read_investigation.read_fair3rDatastore_enum.read_read.nested',
    list: ['name', 'type', 'startAt', 'endAt', 'elaftwExternalId'],
    form: [
      'name',
      'type',
      'goal',
      'protocol',
      'startAt',
      'endAt',
      'description',
      'elaftwExternalId',
      'elabftwMetadata',
      'investigation',
      'repositoryLink',
      'assays',
      'records',
    ],
    fields: {
      name: { label: 'Study Name' },
      type: { label: 'Study Type' },
      goal: { input: 'textarea' },
      protocol: { input: 'textarea' },
      startAt: { input: 'datetime' },
      endAt: { input: 'datetime' },
      description: { input: 'textarea' },
      elaftwExternalId: { label: 'ElabFTW Sync ID', readOnly: true },
      elabftwMetadata: {
        label: 'eLabFTW Extra Fields',
        input: 'json',
        helper: 'Raw eLabFTW metadata payload. Changes here are pushed during study synchronization.',
      },
      investigation: { input: 'iri', placeholder: '/experiments/{id}' },
      assays: { input: 'tags', placeholder: 'api/nam/datasets/{id}' },
      records: { input: 'json', helper: 'JSON object or list of key/value records.' },
    },
  }),
  subjects: defineUiConfig({
    schemaRef: 'Subject.jsonld-subject.read_investigation.read_enum.read_read.nested',
    list: ['name', 'species', 'sex', 'birthAt'],
    form: [
      'name',
      'species',
      'sex',
      'birthAt',
      'strain',
      'genotype',
      'cage',
      'weight',
      'healthStatus',
      'isPregnant',
      'assays',
      'externalId',
      'account',
    ],
    fields: {
      species: {
        input: 'text',
        placeholder: 'Search NCBI taxonomy…',
        helper: 'Suggestions are mapped to the species values currently supported by Metadatapp.',
        lookup: { source: 'ols_ncbitaxon', requireEnumValue: true },
      },
      birthAt: { input: 'date' },
      genotype: { input: 'textarea' },
      strain: {
        input: 'text',
        placeholder: 'Search strain or line…',
        helper: 'Selecting a suggestion resolves it to a local strain record automatically.',
        lookup: { source: 'ols_efo', resolve: 'strain', minLength: 2 },
      },
      cage: { input: 'iri', placeholder: '/cages/{id}' },
      assays: { input: 'tags', placeholder: '/assays/{id}' },
      account: { input: 'iri', placeholder: '/accounts/{id}' },
      healthStatus: { label: 'Health Status' },
      isPregnant: { label: 'Pregnant', input: 'boolean' },
    },
  }),
  users: defineUiConfig({
    schemaRef: 'User.jsonld',
    list: ['name', 'email', 'role', 'account'],
    form: ['firstName', 'lastName', 'name', 'email', 'role', 'roles', 'account', 'orcid', 'connectedApps'],
    fields: {
      firstName: { label: 'First Name' },
      lastName: { label: 'Last Name' },
      email: { placeholder: 'user@example.com' },
      role: { label: 'Role', input: 'tags' },
      roles: { label: 'Roles', input: 'tags' },
      account: { input: 'iri', placeholder: '/accounts/{id}' },
      connectedApps: { input: 'tags', placeholder: '/connected_apps/{id}' },
      orcid: {
        label: 'ORCID',
        placeholder: 'Search author name or ORCID…',
        helper: 'Use the ORCID public expanded-search API to select an existing researcher identifier.',
        lookup: { source: 'orcid', searchWithFormValues: ['firstName', 'lastName'] },
      },
    },
  }),
  laboratories: defineUiConfig({
    schemaRef: 'Laboratory.jsonld-laboratory.read_investigation.read_nested.read_enum.read',
    list: ['name'],
    form: ['name', 'account', 'investigations'],
    fields: {
      name: {
        label: 'Laboratory Name',
        placeholder: 'Search institution…',
        helper: 'Suggestions come from the ROR organization registry.',
        lookup: { source: 'ror', mapTo: 'label', minLength: 2 },
      },
      account: { input: 'iri', placeholder: '/accounts/{id}' },
      investigations: { input: 'tags', placeholder: '/investigations/{id}' },
    },
  }),
  cages: defineUiConfig({
    schemaRef: 'Cage.jsonld',
    list: ['type', 'format', 'beddingType', 'animalFacility'],
    form: [
      'name',
      'type',
      'format',
      'beddingType',
      'enrichmentType',
      'hasEnrichments',
      'water',
      'food',
      'animalFacility',
      'environmentHousing',
      'homeCageMonitoring',
      'subjects',
      'account',
      'externalId',
    ],
    fields: {
      name: { label: 'Cage Name' },
      animalFacility: { input: 'iri', placeholder: '/animal_facilities/{id}' },
      environmentHousing: { input: 'iri', placeholder: '/environment_housings/{id}' },
      homeCageMonitoring: { input: 'iri', placeholder: '/home_cage_monitorings/{id}' },
      subjects: { input: 'tags', placeholder: '/subjects/{id}' },
      account: { input: 'iri', placeholder: '/accounts/{id}' },
      hasEnrichments: { label: 'Has Enrichments', input: 'boolean' },
      water: { input: 'boolean' },
      food: { input: 'boolean' },
    },
  }),
  connected_apps: defineUiConfig({
    schemaRef: 'ConnectedApp.jsonld-connected_app.read_enum.read',
    list: ['name', 'code', 'active', 'lastSyncAt'],
    form: ['name', 'code', 'active', 'externalUrl', 'lastSyncAt', 'description'],
    fields: {
      code: { label: 'Provider Code' },
      active: { input: 'boolean' },
      lastSyncAt: { label: 'Last Sync', input: 'datetime', readOnly: true },
      description: { input: 'textarea' },
    },
  }),
  accounts: defineUiConfig({
    schemaRef: 'Account.jsonld',
    list: ['name'],
    form: ['name', 'users', 'connectedApps'],
    fields: {
      users: { input: 'tags', placeholder: '/users/{id}' },
      connectedApps: { input: 'tags', placeholder: '/connected_apps/{id}' },
    },
  }),
}

type UiConfigKey = keyof typeof uiConfigByResource

type ResourceUiOverride = {
  list?: string[]
  form?: string[]
  fields?: Record<string, UiFieldOverride>
}

const resourceUiOverrides: Record<string, ResourceUiOverride> = {
  experiments: {
    list: ['name', 'startAt', 'endAt'],
    form: ['name', 'goal', 'startAt', 'endAt', 'description', 'investigation', 'protocol', 'assays', 'records', 'elaftwExternalId'],
    fields: {
      name: { label: 'Study Name' },
      goal: { input: 'textarea' },
      startAt: { input: 'datetime' },
      endAt: { input: 'datetime' },
      description: { input: 'textarea' },
      investigation: { input: 'iri', placeholder: '/projects/{id}' },
      assays: { input: 'tags', placeholder: '/procedures/{id}' },
      records: { input: 'json', helper: 'JSON object or list of key/value records.' },
      elaftwExternalId: { label: 'ElabFTW Sync ID', readOnly: true },
    },
  },
  procedures: {
    list: ['name', 'startAt', 'endAt'],
    form: ['name', 'startAt', 'endAt', 'protocol', 'state', 'experiment', 'elaftwExternalId'],
    fields: {
      name: { label: 'Assay Name' },
      startAt: { input: 'datetime' },
      endAt: { input: 'datetime' },
      protocol: { input: 'textarea' },
      experiment: { label: 'Study', input: 'iri', placeholder: '/experiments/{id}' },
      elaftwExternalId: { label: 'ElabFTW Sync ID', readOnly: true },
    },
  },
  projects: {
    list: ['name', 'startAt', 'endAt'],
    form: ['name', 'goal', 'startAt', 'endAt', 'description', 'repositoryLink', 'elaftwExternalId'],
    fields: {
      name: { label: 'Investigation Name' },
      goal: { input: 'textarea' },
      startAt: { input: 'date' },
      endAt: { input: 'date' },
      description: { input: 'textarea' },
      repositoryLink: { label: 'Repository', placeholder: 'https://…' },
      elaftwExternalId: { label: 'ElabFTW Sync ID', readOnly: true },
    },
  },
}

const getUiConfig = (key: string) => uiConfigByResource[key as UiConfigKey] ?? resourceUiOverrides[key]

const humanize = (value: string) =>
  value
    .replace(/[_-]+/g, ' ')
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/\b\w/g, (char) => char.toUpperCase())

const inferInput = (field: SchemaField): UiInputType => {
  if (field.kind === 'enum') return 'select'
  if (field.kind === 'boolean') return 'boolean'
  if (field.kind === 'number') return 'number'
  if (field.kind === 'iri') return 'iri'
  if (field.kind === 'object') return 'json'
  if (field.kind === 'array') {
    if (field.itemKind === 'string' || field.itemKind === 'iri' || field.itemKind === 'enum') {
      return 'tags'
    }
    return 'json'
  }
  if (field.format === 'date') return 'date'
  if (field.format === 'date-time') return 'datetime'
  return 'text'
}

const normalizeOverrides = (overrides?: UiResourceConfig<SchemaRef>['fields']) =>
  (overrides ?? {}) as Record<string, UiFieldOverride>

const createFallbackField = (key: string): SchemaField => ({
  key,
  kind: 'string',
  required: false,
})

export const buildUiField = (field: SchemaField, override?: UiFieldOverride): UiField => ({
  ...field,
  label: override?.label ?? humanize(field.key),
  input: override?.input ?? inferInput(field),
  placeholder: override?.placeholder,
  helper: override?.helper,
  readOnly: override?.readOnly ?? field.readOnly,
  hidden: override?.hidden,
  lookup: override?.lookup,
})

const orderFields = (fields: SchemaField[], preferred?: string[], appendRemaining = false) => {
  if (!preferred?.length) return fields
  const byKey = new Map(fields.map((field) => [field.key, field]))
  const selected: SchemaField[] = []

  preferred.forEach((key) => {
    const field = byKey.get(key)
    if (field) selected.push(field)
  })

  if (!appendRemaining) return selected

  fields.forEach((field) => {
    if (!selected.includes(field)) selected.push(field)
  })

  return selected
}

export const getListFieldsForEntry = (entry?: ResourceRegistryEntry): UiField[] => {
  if (!entry) return []
  const schemaRef = entry.listSchemaRef ?? entry.itemSchemaRef
  if (!schemaRef) return []
  const config = getUiConfig(entry.key)
  const schemaFields = getSchemaFields(schemaRef)
  const overrides = normalizeOverrides(config?.fields)

  if (!schemaFields.length && config?.list?.length) {
    // Some live MAPP resources exist in the runtime spec before they are
    // represented in the generated official schema helpers. Fall back to the
    // configured field order so those lists still render useful columns.
    return config.list
      .map((key) => buildUiField(createFallbackField(key), overrides[key]))
      .filter((field) => !field.hidden)
  }

  const ordered = config?.list?.length
    ? orderFields(schemaFields, config.list, false)
    : getPreferredListFields(schemaFields)
  return ordered.map((field) => buildUiField(field, overrides[field.key])).filter((field) => !field.hidden)
}

export const getFormFieldsForEntry = (
  entry?: ResourceRegistryEntry,
  schemaRef?: SchemaRef | string
): UiField[] => {
  if (!entry || !schemaRef) return []
  const config = getUiConfig(entry.key)
  const schemaFields = getSchemaFields(schemaRef)
  const overrides = normalizeOverrides(config?.fields)

  if (config?.form?.length) {
    const byKey = new Map(schemaFields.map((field) => [field.key, field]))
    const configured = config.form.flatMap((key) => {
      const field = byKey.get(key)
      if (field) return [field]

      const override = overrides[key]
      return override?.readOnly || override?.hidden ? [createFallbackField(key)] : []
    })
    const ordered = [...configured]

    schemaFields.forEach((field) => {
      if (!ordered.some((candidate) => candidate.key === field.key)) {
        ordered.push(field)
      }
    })

    return ordered.map((field) => buildUiField(field, overrides[field.key])).filter((field) => !field.hidden)
  }

  const ordered = schemaFields
  return ordered.map((field) => buildUiField(field, overrides[field.key])).filter((field) => !field.hidden)
}

export const getDetailFieldsForEntry = (
  entry?: ResourceRegistryEntry,
  schemaRef?: SchemaRef | string,
  data?: Record<string, unknown>
): UiField[] => {
  if (!entry) return []
  const config = getUiConfig(entry.key)
  if (schemaRef) {
    const schemaFields = getSchemaFields(schemaRef)
    if (schemaFields.length) {
      const ordered = config?.detail?.length
        ? orderFields(schemaFields, config.detail, true)
        : schemaFields
      const overrides = normalizeOverrides(config?.fields)
      return ordered.map((field) => buildUiField(field, overrides[field.key])).filter((field) => !field.hidden)
    }
  }

  if (!data) return []
  const overrides = normalizeOverrides(config?.fields)
  return Object.keys(data)
    .filter((key) => !['@context', '@type'].includes(key))
    .map((key) =>
      buildUiField(
        { key, kind: 'string', required: false },
        overrides[key]
      )
    )
    .filter((field) => !field.hidden)
}

export const extractReference = (value: unknown): string | null => {
  if (value === null || value === undefined) return null
  if (typeof value === 'string' || typeof value === 'number') return String(value)
  if (typeof value === 'object') {
    const raw = (value as Record<string, unknown>)['@id'] ?? (value as Record<string, unknown>).id
    if (raw) return String(raw)
  }
  return null
}
