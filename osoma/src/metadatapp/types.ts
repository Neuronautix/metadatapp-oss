import type { IriLike } from './iri.ts'
import type { AccessArea } from '@/lib/user-access.ts'

export type MetaInvestigation = Partial<{
  id: string
  '@id': string
  name: string
  description: string
  goal: string
  startAt: string
  endAt: string | null
  user: IriLike
  laboratories: IriLike[]
  studies: IriLike[]
  externalId: string
  elaftwExternalId: string
  fair3rExternalId: string
  [key: string]: unknown
}>

export type MetaStudy = Partial<{
  id: string
  '@id': string
  name: string
  goal: string
  type: string
  protocol: string
  startAt: string
  endAt: string | null
  description: string
  investigation: IriLike
  assay: IriLike
  assays: IriLike[]
  records: unknown[]
  elaftwExternalId: string
  elabftwMetadata: Record<string, unknown> | null
  [key: string]: unknown
}>

export type MetaAssay = Partial<{
  id: string
  '@id': string
  name: string
  version: string
  method: string
  owner: string
  reviewStatus: string
  lastReviewedAt: string
  createdAt: string
  updatedAt: string
  [key: string]: unknown
}>

export type MetaSubject = Partial<{
  id: string
  '@id': string
  name: string
  externalId: string
  species: string
  sex: string
  birthAt: string
  strain: IriLike | string
  genotype: string
  cage: IriLike | MetaCage
  weight: number
  healthStatus: string
  isPregnant: boolean
  assays: IriLike[]
  account: IriLike
  [key: string]: unknown
}>

export type MetaWeightMeasurement = Partial<{
  id: string
  '@id': string
  externalId: string
  elaftwExternalId: string
  fair3rExternalId: string
  subject: IriLike | MetaSubject
  measuredAt: string
  weight: number
  createdAt: string
  updatedAt: string
  [key: string]: unknown
}>

export type MetaUser = Partial<{
  id: string
  '@id': string
  firstName: string
  lastName: string
  name: string
  email: string
  role: string | string[]
  roles: string[]
  account: IriLike
  connectedApps: IriLike[]
  orcid: string
  lab: string
  accessLevel: 'viewer' | 'editor' | 'admin'
  accessAreas: AccessArea[]
  status: 'active' | 'invited' | 'suspended'
  lastActive: string
  [key: string]: unknown
}>

export type MetaCage = Partial<{
  id: string
  '@id': string
  name: string
  type: string
  format: string
  beddingType: string
  enrichmentType: string
  hasEnrichments: boolean
  water: string | boolean
  food: string | boolean
  animalFacility: IriLike
  environmentHousing: IriLike
  homeCageMonitoring: IriLike
  subjects: IriLike[]
  account: IriLike
  externalId: string
  [key: string]: unknown
}>

export type MetaDataset = Partial<{
  id: string
  '@id': string
  title: string
  investigation: IriLike
  format: string
  sizeGb: number
  status: string
  doi: string
  createdAt: string
  updatedAt: string
  [key: string]: unknown
}>
