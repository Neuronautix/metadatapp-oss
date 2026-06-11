import type { AiSegmentLabel, DvcEventType, VcgMatchMode, WelfareSeverity } from './dvc.enums.ts'

export type Pid = `urn:os:${string}`

export interface Dataset {
  id: string
  pid: Pid
  name: string
  description: string
  species: string
  strain: string
  sex: 'male' | 'female' | 'mixed'
  ageWeeks: number
  sanitaryStatus: string
  facility: string
  room: string
  environment: string
  beddingBatch: string
  beddingMaterial: string
  diet: string
  device: string
  pipelineVersion: string
  createdAt: string
  tags: string[]
}

export interface CageContextSnapshot {
  cageId: string
  pid: Pid
  room: string
  rack: string
  cageType: string
  facility: string
  beddingBatch: string
  beddingMaterial: string
  sanitaryStatus: string
  diet: string
  water: string
  device: string
  firmware: string
  pipelineVersion: string
  lastChangeout: string
}

export interface DvcActivityPoint {
  ts: string
  value: number
}

export interface DvcActivitySeries {
  cageId: string
  pid: Pid
  ts: string[]
  activity: number[]
}

export interface EnvironmentRecord {
  scopeType: 'room' | 'cage'
  scopeId: string
  pid: Pid
  ts: string[]
  temp: number[]
  humidity: number[]
  light: number[]
  noise: number[]
  co2: number[]
  ammonia: number[]
}

export interface CageEvent {
  id: string
  cageId: string
  ts: string
  type: DvcEventType
  payload: {
    fromRoom?: string
    toRoom?: string
    beddingBatch?: string
    notes?: string
  }
}

export interface WelfareAlarm {
  id: string
  cageId: string
  startTs: string
  endTs?: string
  severity: WelfareSeverity
  status: 'open' | 'ack' | 'resolved'
  reasonCode: string
  explanation: string
  actions: string[]
}

export interface AIBehaviorOutput {
  id: string
  cageId: string
  windowStart: string
  windowEnd: string
  sleepScore: number
  circadianStability: number
  fragmentation: number
  anomalyScore: number
  label: AiSegmentLabel
  explanation: string
}

export interface QualitySignals {
  cageId: string
  pid: Pid
  missingDataPct: number
  lastSeen: string
  sensorHealth: 'good' | 'warn' | 'offline'
  pipelineVersion: string
}

export interface VcgCandidate {
  datasetId: string
  pid: Pid
  name: string
  similarityScore: number
  matchNotes: string[]
  confounders: string[]
  metadata: {
    strain: string
    sex: string
    ageWeeks: number
    sanitaryStatus: string
    environment: string
    beddingBatch: string
    beddingMaterial: string
    facility: string
    room: string
    device: string
    pipelineVersion: string
  }
}

export interface VcgSelection {
  id: string
  targetDatasetId: string
  mode: VcgMatchMode
  generatedAt: string
  candidates: VcgCandidate[]
  explanation: string
  weighting: Record<string, number>
}

export type {
  DvcMetric,
  DvcRfidInterval,
  DvcGlobResult,
  DvcGlobSearch,
  DvcGlobSearchResponse,
  DvcTaskSubmission,
  DvcTaskSubmissionResponse,
  DvcTaskState,
  DvcTestApiKeyResponse,
} from './dvc.contract.ts'

export type DvcAggrType = 'HOUR' | 'MINUTE'
export type DvcResourceType = 'CAGE' | 'ANIMAL'
export type DvcPartitionType = 'SINGLE_FILE' | 'SINGLE_FILE_BY_DAY' | 'SINGLE_FILE_BY_CAGE'
export type DvcTaskStateValue = 'SUBMITTED' | 'RUNNING' | 'COMPLETED' | 'FAILED'
