import type { ConditionReadings, ConditionSnapshot, ConditionThreshold } from '../tecniplast.types.ts'
import { buildQuery, tpFetch } from '../tp.api.ts'

type ScenarioQuery = { at?: string; scenarioId?: string }

export const getConditionSnapshot = (scopeType: string, scopeId: string, options?: ScenarioQuery) =>
  tpFetch<ConditionSnapshot>(`/conditions/snapshot${buildQuery({ scopeType, scopeId, ...options })}`)

export const getConditionReadings = (params: {
  scopeType: string
  scopeId: string
  metric: string
  from: string
  to: string
  interval: string
  at?: string
  scenarioId?: string
}) => tpFetch<ConditionReadings>(`/conditions/readings${buildQuery(params)}`)

export const getConditionThresholds = (scopeType: string, scopeId: string, options?: ScenarioQuery) =>
  tpFetch<ConditionThreshold[]>(`/conditions/thresholds${buildQuery({ scopeType, scopeId, ...options })}`)

export const updateConditionThresholds = (scopeType: string, scopeId: string, thresholds: ConditionThreshold[]) =>
  tpFetch<ConditionThreshold[]>(`/conditions/thresholds${buildQuery({ scopeType, scopeId })}`, {
    method: 'PUT',
    body: JSON.stringify({ thresholds }),
  })
