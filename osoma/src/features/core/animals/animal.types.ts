export type AnimalStatus = 'active' | 'at-risk' | 'quarantine' | 'removed'

export type Animal = {
  id: string
  tagId: string
  nickname: string
  strain: string
  sex: 'female' | 'male'
  ageWeeks: number
  cage: string
  room: string
  cohort: string
  status: AnimalStatus
  baselineWeight: number
  lastWeight: number
  lastWeighedAt: string
  investigationId?: string
  assignedAt?: string
  source: 'Tick@Lab'
  badgeStatus?: 'ok' | 'warning' | 'critical'
  weightHistory?: number[]
  quickNote?: string
  isFavorite?: boolean
  cageColor?: 'emerald' | 'sky' | 'amber' | 'rose' | 'violet' | 'slate'
}

export type AnimalWeightEntry = {
  id: string
  animalId: string
  grams: number
  at: string
  source: 'scale' | 'manual'
}

export type AnimalWeightRange = {
  min: number
  max: number
  target: number
}

export type TreatmentEvent = {
  id: string
  animalId: string
  drug: string
  pathogen: string
  lot: string
  dose: number
  doseUnit: string
  operator: string
  administeredAt: string
  notes?: string
}

export type BloodSample = {
  id: string
  animalId: string
  investigationId: string
  sampleId: string
  collectedAt: string
  operator: string
  volume: number
  volumeUnit: string
  site: string
}

export type FlowResult = {
  id: string
  panel: string
  marker: string
  population: string
  value: number
  unit: string
  qcStatus: 'pass' | 'review' | 'fail'
}

export type PcrResult = {
  id: string
  target: string
  ct: number
  deltaCt: number
  qcStatus: 'pass' | 'review' | 'fail'
}

export type AntibodyResult = {
  id: string
  antigen: string
  titer: number
  unit: string
  qcStatus: 'pass' | 'review' | 'fail'
}

export type AnalysisTrend = {
  id: string
  label: string
  values: number[]
}

export type AnalysisResults = {
  flow: FlowResult[]
  pcr: PcrResult[]
  antibody: AntibodyResult[]
  trends: AnalysisTrend[]
  updatedAt: string
}

export type BehaviorPoint = {
  at: string
  activity: number
  phase: 'day' | 'night'
  anomaly?: boolean
}

export type BehaviorSeries = {
  points: BehaviorPoint[]
  baseline: number
  circadianPeak: string
  circadianLow: string
}

export type AnimalTimelineKind =
  | 'assignment'
  | 'weight'
  | 'treatment'
  | 'sample'
  | 'analysis'
  | 'behavior'
  | 'alert'

export type AnimalTimelineEvent = {
  id: string
  at: string
  kind: AnimalTimelineKind
  title: string
  detail?: string
  actor?: string
  value?: string
  actorAvatar?: {
    initials: string
    color: string
  }
}

export type AnimalsResponse = {
  data: Animal[]
}

export type AnimalWeightsResponse = {
  data: AnimalWeightEntry[]
}

export type TreatmentsResponse = {
  data: TreatmentEvent[]
}

export type SamplesResponse = {
  data: BloodSample[]
}
