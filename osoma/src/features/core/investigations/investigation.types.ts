import type { FAIRScore } from '@/domain/resources.ts'
import type { PaginatedResponse } from '@/lib/types.ts'
import type { MetaInvestigation } from '@/metadatapp/types.ts'

export type InvestigationOperator = {
  id: string
  name: string
  role: string
  avatarColor?: string
}

export type Investigation = MetaInvestigation & {
  id: string
  name: string
  // UI and Derived fields
  species: 'Mouse'
  fairScore: FAIRScore
  assignedOperators: InvestigationOperator[]
  assignedAnimalIds: string[]
  createdAt: string
  updatedAt: string
  tags?: string[]
}

export type InvestigationCreateInput = {
  name: string
  description: string
  species: 'Mouse'
  startDate: string
  endDate?: string
  assignedOperatorIds: string[]
}

export type InvestigationUpdateInput = Partial<InvestigationCreateInput>

export type InvestigationsResponse = PaginatedResponse<Investigation>

export type InvestigationAtRiskAnimal = {
  id: string
  tagId: string
  nickname: string
  status: 'monitor' | 'critical'
  lastWeight: number
  lastWeighedAt: string
  reason: string
}

export type InvestigationGroupWeightPoint = {
  at: string
  average: number
  min: number
  max: number
}

export type InvestigationMarkerTrend = {
  id: string
  label: string
  value: number
  delta: number
  status: 'up' | 'down' | 'flat'
  history: number[]
}

export type InvestigationRecentAction = {
  id: string
  at: string
  title: string
  detail: string
  actor: string
}

export type InvestigationDashboard = {
  investigationId: string
  animalsAtRisk: InvestigationAtRiskAnimal[]
  groupWeights: InvestigationGroupWeightPoint[]
  markerTrends: InvestigationMarkerTrend[]
  recentActions: InvestigationRecentAction[]
}

export type InvestigationExportStatus = {
  investigationId: string
  lastExportAt?: string
  rowCount: number
}

export type InvestigationActivityItem = {
  id: string
  investigationId: string
  investigationName: string
  at: string
  title: string
  detail: string
  actor: string
}

export type InvestigationActivityResponse = {
  data: InvestigationActivityItem[]
}

export type KeyboardShortcut = {
  id: string
  keys: string
  label: string
}

export type KeyboardShortcutsResponse = {
  data: KeyboardShortcut[]
}
