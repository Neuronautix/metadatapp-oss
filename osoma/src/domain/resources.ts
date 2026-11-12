import type { AccessArea } from '@/lib/user-access.ts'

export type FAIRScore = {
  total: number
  findable: number
  accessible: number
  interoperable: number
  reusable: number
}

export type FAIRCriterion = {
  id: string
  label: string
  description: string
  pass: boolean
}

export type FAIRCriteria = {
  findable: FAIRCriterion[]
  accessible: FAIRCriterion[]
  interoperable: FAIRCriterion[]
  reusable: FAIRCriterion[]
}

export type ProvenanceEvent = {
  id: string
  at: string
  actor: string
  action: string
  detail?: string
}

export type ResourceIdentifier = {
  type: string
  value: string
  issuer?: string
}

export type InvestigationStatus = 'planning' | 'active' | 'analysis' | 'archived'
export type StudyStatus = 'draft' | 'running' | 'completed' | 'paused' | 'failed'
export type SampleStatus = 'collected' | 'processing' | 'stored' | 'consumed'
export type QcStatus = 'pass' | 'review' | 'fail'

export type Investigation = {
  id: string
  code: string
  name: string
  status: InvestigationStatus
  lead: string
  organization: string
  startDate: string
  targetEndDate?: string
  description: string
  researchArea: string
  studies: number
  samples: number
  datasets: number
  fairScore: FAIRScore
  identifiers: ResourceIdentifier[]
  provenance: ProvenanceEvent[]
  createdAt: string
  updatedAt: string
}

export type Study = {
  id: string
  name: string
  elaftwExternalId?: string
  investigationId: string
  investigationName: string
  assayId: string
  assayName: string
  status: StudyStatus
  startedAt: string
  completedAt?: string
  runCount: number
  sampleCount: number
  dataVolumeGb: number
  qcStatus: QcStatus
  isSigned?: boolean
  signedBy?: string
  signedAt?: string
  fairScore: FAIRScore
  identifiers: ResourceIdentifier[]
  provenance: ProvenanceEvent[]
  createdAt: string
  updatedAt: string
}

export type Sample = {
  id: string
  label: string
  subjectId: string
  subjectLabel: string
  investigationId: string
  investigationName: string
  type: string
  status: SampleStatus
  collectedAt: string
  storageLocation: string
  qcStatus: QcStatus
  derivedFrom?: string
  identifiers: ResourceIdentifier[]
  provenance: ProvenanceEvent[]
  createdAt: string
  updatedAt: string
}

export type Subject = {
  id: string
  label: string
  species: string
  sex?: string
  strain?: string
  birthAt?: string
  cohort: string
  consent: 'granted' | 'restricted' | 'withdrawn'
  status: 'active' | 'completed' | 'archived'
  cageId?: string
  cageLabel?: string
  cageRoom?: string
  cageRack?: string
  createdAt: string
  updatedAt: string
}

export type Assay = {
  id: string
  name: string
  elaftwExternalId?: string
  version: string
  method: string
  owner: string
  reviewStatus: 'current' | 'review-due' | 'retired'
  lastReviewedAt: string
  createdAt: string
  updatedAt: string
}

export type Dataset = {
  id: string
  title: string
  investigationId: string
  investigationName: string
  format: string
  sizeGb: number
  status: 'draft' | 'published' | 'restricted'
  doi?: string
  fairScore: FAIRScore
  createdAt: string
  updatedAt: string
}

export type User = {
  id: string
  name: string
  email?: string
  role: string
  lab: string
  accessLevel: 'viewer' | 'editor' | 'admin'
  accessAreas?: AccessArea[]
  status: 'active' | 'invited' | 'suspended'
  lastActive: string
}

export type Organization = {
  id: string
  name: string
  type: 'university' | 'biotech' | 'hospital' | 'consortium'
  location: string
  labs: number
  activeInvestigations: number
  createdAt: string
  updatedAt: string
}
