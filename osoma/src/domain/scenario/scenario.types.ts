import type { ScopeRef, ScopeType } from '../scope.ts'
import type {
  Alarm,
  ConditionMetric,
  ConditionSnapshotMetric,
  ConditionThreshold,
} from '@/features/integrations/tecniplast/tecniplast.types.ts'

export type ScenarioOverride = {
  thresholds?: Partial<Record<ConditionMetric, { min?: number; max?: number }>>
  metricAdjustments?: Partial<Record<ConditionMetric, number>>
  suppressAlarms?: string[]
  scheduleOffsetMinutes?: number
  note?: string
}

export type Scenario = {
  id: string
  label: string
  baseTimestamp: string
  scope: ScopeRef
  overrides: ScenarioOverride
  createdBy: string
  createdAt: string
}

export type ScenarioSnapshot = {
  at: string
  collectedAt: string
  scopeType: ScopeType
  scopeId: string
  thresholds: ConditionThreshold[]
  metrics: ConditionSnapshotMetric[]
  alarms: Alarm[]
  mode: 'reality' | 'scenario'
  scenarioId?: string
  scenarioLabel?: string
}

export type ScenarioDelta = {
  id: string
  type: 'alarm' | 'metric' | 'threshold'
  title: string
  detail: string
  impact: 'positive' | 'negative' | 'neutral'
}

export type ReplayRequest = {
  scopeType: ScopeType
  scopeId: string
  at: string
  scenario?: Scenario
}

export type ReplayResult = {
  reality: ScenarioSnapshot
  scenario: ScenarioSnapshot
  deltas: ScenarioDelta[]
}
