import type { Assay as DomainAssay, Dataset as DomainDataset, Sample as DomainSample, Study as DomainStudy, Subject as DomainSubject, User as DomainUser } from '@/domain/resources.ts'
import { getDefaultAccessAreas } from '@/lib/user-access.ts'
import type { Investigation, InvestigationCreateInput, InvestigationOperator, InvestigationUpdateInput } from '@/features/core/investigations/investigation.types.ts'
import type { CageDetail, CageSummary } from '@/features/core/cages/cage.types.ts'
import { computeFairCriteria, computeFairScore } from '@/lib/fair.ts'
import type { MetaInvestigation, MetaStudy, MetaSubject, MetaUser, MetaCage, MetaAssay, MetaDataset, MetaWeightMeasurement } from './types.ts'
import { asArray, getIdFromIri, toIri } from './iri.ts'

const withInvestigationFairScore = (resource: Omit<Investigation, 'fairScore'>): Investigation => ({
  ...resource,
  fairScore: computeFairScore(computeFairCriteria(resource as Parameters<typeof computeFairCriteria>[0])),
}) as Investigation

const withStudyFairScore = (resource: Omit<DomainStudy, 'fairScore'>): DomainStudy => ({
  ...resource,
  fairScore: computeFairScore(computeFairCriteria(resource)),
})

const withDatasetFairScore = (resource: Omit<DomainDataset, 'fairScore'>): DomainDataset => ({
  ...resource,
  fairScore: computeFairScore(computeFairCriteria(resource)),
})

const nowIso = () => new Date().toISOString()

const deriveStudyStatus = (type?: string, startAt?: string, endAt?: string | null): DomainStudy['status'] => {
  if (type && ['draft', 'running', 'paused', 'completed', 'failed'].includes(type)) {
    return type as DomainStudy['status']
  }
  if (endAt) return 'completed'
  if (startAt) return 'running'
  return 'draft'
}

const deriveAccessLevel = (roles?: string[], role?: string | string[]): DomainUser['accessLevel'] => {
  const rolePool = Array.isArray(role) ? role : role ? [role] : []
  const pool = [...(roles ?? []), ...rolePool]
  const combined = pool.join(' ').toLowerCase()
  if (combined.includes('admin')) return 'admin'
  if (combined.includes('edit') || combined.includes('write')) return 'editor'
  return 'viewer'
}

const resolveId = (id?: string | null, fallback?: string) => (id?.trim() ? id : fallback ?? '')

const asText = (value: unknown, fallback = ''): string => {
  if (typeof value === 'string') return value
  if (typeof value === 'number' || typeof value === 'boolean') return String(value)

  if (value && typeof value === 'object') {
    const record = value as Record<string, unknown>
    if (typeof record.value === 'string') return record.value
    if (typeof record.name === 'string') return record.name
    if (typeof record.id === 'string') return record.id
    if (typeof record['@id'] === 'string') return getIdFromIri(record['@id'])
  }

  return fallback
}

const getHydraId = (value: unknown): string => {
  if (!value || typeof value !== 'object') return ''
  const iri = (value as { '@id'?: unknown })['@id']
  return typeof iri === 'string' ? getIdFromIri(iri) : ''
}

const getNestedName = (value: unknown): string => {
  if (!value || typeof value !== 'object') return ''
  const record = value as Record<string, unknown>
  return asText(record.name, asText(record.label, getHydraId(record)))
}

export const mapMetaInvestigation = (input: MetaInvestigation): Investigation => {
  const id = resolveId(input.id, getHydraId(input))
  const startAt = input.startAt ?? nowIso()
  const endAt = input.endAt ?? undefined
  const operatorId = getIdFromIri(input.user)
  const assignedOperators: InvestigationOperator[] = operatorId
    ? [{ id: operatorId, name: operatorId, role: 'Owner' }]
    : []

  return withInvestigationFairScore({
    id,
    name: input.name ?? `Investigation ${id}`,
    description: input.description ?? input.goal ?? '',
    species: 'Mouse',
    startDate: startAt,
    endDate: endAt ?? undefined,
    startAt,
    endAt,
    assignedOperators,
    assignedAnimalIds: [],
    createdAt: startAt,
    updatedAt: endAt ?? startAt,
    tags: [],
  })
}

export const mapMetaStudy = (input: MetaStudy): DomainStudy => {
  const id = resolveId(input.id, getIdFromIri(input['@id']))
  const investigationId = getIdFromIri(input.investigation)
  const assayId = getIdFromIri(input.assay)
  const startAt = input.startAt ?? nowIso()
  const endAt = input.endAt ?? undefined
  const status = deriveStudyStatus(input.type, input.startAt, input.endAt)
  const elaftwExternalId = typeof input.elaftwExternalId === 'string' ? input.elaftwExternalId : undefined

  return withStudyFairScore({
    id,
    name: input.name ?? `Study ${id}`,
    elaftwExternalId,
    investigationId,
    investigationName: getNestedName(input.investigation) || 'Unassigned Investigation',
    assayId,
    assayName: getNestedName(input.assay) || 'Unassigned Assay',
    status,
    startedAt: startAt,
    completedAt: endAt,
    runCount: asArray(input.assays).length,
    sampleCount: asArray(input.records).length,
    dataVolumeGb: 0,
    qcStatus: 'review',
    isSigned: false,
    identifiers: [],
    provenance: [],
    createdAt: startAt,
    updatedAt: endAt ?? startAt,
  })
}

export const mapMetaSubject = (input: MetaSubject): DomainSubject => {
  const id = resolveId(input.id, getHydraId(input))
  const cageId = getIdFromIri(input.cage)
  const birthAt = input.birthAt ?? nowIso()
  const species = asText(input.species, 'Unknown')
  const cohort = asText(input.strain, asText(input.genotype, 'General'))

  const cageObj = input.cage && typeof input.cage === 'object' ? input.cage as Record<string, unknown> : null
  let cageLabel: string | undefined = cageId || undefined
  if (cageObj) {
    if (typeof cageObj.externalId === 'string' && cageObj.externalId) {
      cageLabel = cageObj.externalId
    } else {
      cageLabel = asText(cageObj.format, asText(cageObj.type)) || cageId || undefined
    }
  }

  const facilityObj = cageObj?.animalFacility && typeof cageObj.animalFacility === 'object'
    ? cageObj.animalFacility as Record<string, unknown>
    : null
  const cageRoom: string | undefined = facilityObj
    ? (typeof facilityObj.name === 'string' ? facilityObj.name : undefined)
    : undefined

  return {
    id,
    label: input.name ?? input.externalId ?? `Subject ${id}`,
    species,
    cohort,
    consent: 'granted',
    status: 'active',
    cageId: cageId || undefined,
    cageLabel,
    cageRoom,
    cageRack: undefined,
    createdAt: birthAt,
    updatedAt: birthAt,
  }
}

export const mapMetaSample = (input: MetaWeightMeasurement): DomainSample => {
  const id = resolveId(input.id, getHydraId(input))
  const subjectId = getIdFromIri(input.subject)
  const subjectLabel = getNestedName(input.subject) || subjectId || 'Unknown subject'
  const collectedAt = input.measuredAt ?? input.createdAt ?? nowIso()
  const externalId = input.externalId ?? input.elaftwExternalId ?? input.fair3rExternalId ?? input.softmouseExternalId

  return {
    id,
    label: externalId ?? `Weight sample ${id}`,
    subjectId,
    subjectLabel,
    investigationId: '',
    investigationName: 'Unassigned Investigation',
    type: 'body-weight',
    status: 'stored',
    collectedAt,
    storageLocation: 'Metadatapp registry',
    qcStatus: 'pass',
    identifiers: [
      { type: 'weight_measurement', value: id },
      ...(externalId ? [{ type: 'external', value: externalId }] : []),
    ],
    provenance: [
      {
        id: `collected-${id}`,
        at: collectedAt,
        actor: 'Metadatapp',
        action: 'Sample recorded',
        detail: input.weight !== undefined ? `Body weight ${input.weight}` : 'Body weight measurement recorded.',
      },
    ],
    createdAt: input.createdAt ?? collectedAt,
    updatedAt: input.updatedAt ?? collectedAt,
  }
}

export const mapMetaUser = (input: MetaUser): DomainUser => {
  const id = resolveId(input.id, getHydraId(input))
  const fallbackName = [input.firstName, input.lastName].filter(Boolean).join(' ')
  const name = input.name ?? (fallbackName || input.email || id)
  const accessLevel = input.accessLevel ?? deriveAccessLevel(input.roles, input.role)
  const primaryRole = Array.isArray(input.role) ? input.role[0] : input.role ?? input.roles?.[0] ?? 'Member'
  const accountId = getIdFromIri(input.account)

  return {
    id,
    name,
    email: input.email,
    role: primaryRole,
    lab: input.lab ?? (accountId || 'General'),
    accessLevel,
    accessAreas: input.accessAreas ?? getDefaultAccessAreas(accessLevel),
    status: input.status ?? 'active',
    lastActive: input.lastActive ?? nowIso(),
  }
}

export const mapMetaUserToInvestigationOperator = (input: MetaUser): InvestigationOperator => {
  const id = resolveId(input.id, getHydraId(input))
  const fallbackName = [input.firstName, input.lastName].filter(Boolean).join(' ')
  const name = input.name ?? (fallbackName || input.email || id)
  const role = Array.isArray(input.role) ? input.role[0] : input.role ?? input.roles?.[0] ?? 'Operator'
  return {
    id,
    name,
    role,
  }
}

export const mapMetaCageSummary = (input: MetaCage): CageSummary => {
  const id = resolveId(input.id, getHydraId(input))
  const now = nowIso()

  return {
    id,
    label: input.externalId ?? input.name ?? asText(input.format, asText(input.type, `Cage ${id}`)),
    room: 'Unknown',
    rack: 'Unknown',
    bedding: asText(input.beddingType, 'Standard'),
    sanitary: 'Standard',
    lastSeen: now,
    alerts: 0,
  }
}

export const mapMetaCageDetail = (input: MetaCage): CageDetail => {
  const base = mapMetaCageSummary(input)
  const facilityId = getIdFromIri(input.animalFacility)

  return {
    ...base,
    cageType: asText(input.type, 'Standard'),
    facility: facilityId || 'Unknown',
    beddingBatch: asText(input.beddingType, 'Standard'),
    diet: asText(input.food, 'Standard'),
    water: asText(input.water, 'Standard'),
    device: input.homeCageMonitoring ? getIdFromIri(input.homeCageMonitoring) : 'N/A',
    firmware: 'N/A',
    pipelineVersion: 'N/A',
    quality: {
      missingDataPct: 0,
      sensorHealth: 'good',
    },
    events: [],
  }
}

export const mapInvestigationCreateInput = (input: InvestigationCreateInput): Partial<MetaInvestigation> => ({
  name: input.name,
  description: input.description,
  goal: input.description,
  startAt: input.startDate,
  endAt: input.endDate ?? undefined,
  user: input.assignedOperatorIds?.length ? toIri('users', input.assignedOperatorIds[0]) : undefined,
})

export const mapInvestigationUpdateInput = (input: InvestigationUpdateInput): Partial<MetaInvestigation> => ({
  ...(input.name ? { name: input.name } : {}),
  ...(input.description ? { description: input.description, goal: input.description } : {}),
  ...(input.startDate ? { startAt: input.startDate } : {}),
  ...(input.endDate ? { endAt: input.endDate } : {}),
  ...(input.assignedOperatorIds?.length ? { user: toIri('users', input.assignedOperatorIds[0]) } : {}),
})

export const mapStudyUpdateInput = (input: Partial<DomainStudy>): Partial<MetaStudy> => ({
  ...(input.name ? { name: input.name } : {}),
  ...(input.status ? { type: input.status } : {}),
  ...(input.startedAt ? { startAt: input.startedAt } : {}),
  ...(input.completedAt ? { endAt: input.completedAt } : {}),
  ...(input.investigationId ? { investigation: toIri('projects', input.investigationId) } : {}),
  ...(input.assayId ? { assay: toIri('api/nam/datasets', input.assayId) } : {}),
})

export const mapSubjectUpdateInput = (input: Partial<DomainSubject>): Partial<MetaSubject> => ({
  ...(input.label ? { name: input.label } : {}),
  ...(input.species === 'mouse' || input.species === 'rat' ? { species: input.species } : {}),
  ...(input.cohort ? { strain: input.cohort } : {}),
  ...(input.cageId ? { cage: toIri('cages', input.cageId) } : {}),
})

export const mapUserUpdateInput = (input: Partial<DomainUser>): Partial<MetaUser> => ({
  ...(input.name ? { name: input.name } : {}),
  ...(input.email ? { email: input.email } : {}),
  ...(input.role ? { role: [input.role] } : {}),
})

export const mapMetaAssay = (input: MetaAssay): DomainAssay => {
  const id = resolveId(input.id, getHydraId(input))
  const now = nowIso()
  const elaftwExternalId = typeof input.elaftwExternalId === 'string' ? input.elaftwExternalId : undefined

  return {
    id,
    name: input.name ?? `Assay ${id}`,
    elaftwExternalId,
    version: input.version ?? '1.0',
    method: input.method ?? 'Standard',
    owner: input.owner ?? 'Unknown',
    reviewStatus: input.reviewStatus === 'retired' ? 'retired' : input.reviewStatus === 'review-due' ? 'review-due' : 'current',
    lastReviewedAt: input.lastReviewedAt ?? now,
    createdAt: input.createdAt ?? now,
    updatedAt: input.updatedAt ?? now,
  }
}

export const mapMetaDataset = (input: MetaDataset): DomainDataset => {
  const id = resolveId(input.id, getHydraId(input))
  const investigationId = getIdFromIri(input.investigation)

  return withDatasetFairScore({
    id,
    title: input.title ?? `Dataset ${id}`,
    investigationId,
    investigationName: getNestedName(input.investigation) || 'Unassigned Investigation',
    format: input.format ?? 'json',
    sizeGb: input.sizeGb ?? 0,
    status: input.status === 'published' || input.status === 'restricted' ? input.status : 'draft',
    doi: input.doi ?? undefined,
    createdAt: input.createdAt ?? nowIso(),
    updatedAt: input.updatedAt ?? nowIso(),
  })
}
