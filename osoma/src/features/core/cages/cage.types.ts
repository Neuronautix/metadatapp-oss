import type { CageEvent } from '@/domain/dvc/dvc.types.ts'
import type { AnimalSubject } from '@/features/integrations/tecniplast/tecniplast.types.ts'

export type CageSummary = {
  id: string
  label: string
  room: string
  rack: string
  bedding: string
  sanitary: string
  lastSeen: string
  alerts: number
}

export type CageDetail = CageSummary & {
  cageType: string
  facility: string
  beddingBatch: string
  diet: string
  water: string
  device: string
  firmware: string
  pipelineVersion: string
  quality: {
    missingDataPct: number
    sensorHealth: 'good' | 'warn' | 'offline'
  }
  events: CageEvent[]
}

export type CageSubjectsResponse = {
  cageId: string
  subjects: AnimalSubject[]
}

export type CageHcmVariable = {
  id: string
  name: string
  externalId?: string | null
  tecniplastExternalId?: string | null
}

export type CageHcmMeasure = {
  id: string
  variable: CageHcmVariable
  dataType: string
  value: number
  recordedAt: string
  sourceTaskId?: string | null
  sourceAppCode?: string | null
}

export type CageHcmSinusoidMetadata = {
  id: string
  date: string
  amplitude: number
  mesor: number
  phase: number
  peakHour: number
  sourceTaskId?: string | null
  sourceAppCode?: string | null
}

export type CageHcmProvenance = {
  type: 'measure' | 'sinusoid' | string
  sourceAppCode?: string | null
  sourceTaskId?: string | null
  at: string
  label: string
}

export type CageHcmSystem = {
  id: string
  system: string
  sourceAppCode?: string | null
  sourceAppCodes: string[]
  lightsOnUtcHour?: number | null
  lightDurationHours?: number | null
  experiment: {
    id: string
    name: string
    protocol?: string | null
    project?: {
      id: string
      name: string
    } | null
  }
  environment: {
    temperature?: number | null
    humidity?: number | null
    lightIntensity?: number | null
    noise?: number | null
    lightCycle?: string | null
    humanMovementWorkday?: number | null
  }
  modalities: string[]
  variables: CageHcmVariable[]
  measures: CageHcmMeasure[]
  sinusoidMetadata: CageHcmSinusoidMetadata[]
  provenance: CageHcmProvenance[]
}

export type CageHcmResponse = {
  cageId: string
  systems: CageHcmSystem[]
}
