import type { Scenario, ScenarioOverride } from '@/domain/scenario/scenario.types.ts'
import type { ScopeType } from '@/domain/scope.ts'
import type { ReplayResult } from '@/domain/scenario/scenario.types.ts'
import { apiFetch } from '@/lib/api.ts'

export type ScenarioInput = {
  label: string
  baseTimestamp: string
  scope: { type: ScopeType; id: string; label?: string }
  overrides?: ScenarioOverride
  createdBy?: string
}

export const createScenario = async (payload: ScenarioInput) => {
  const response = await apiFetch<{ scenario: Scenario }>('/scenarios', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
  return response.scenario
}

export const listScenarios = async () => {
  const response = await apiFetch<{ data: Scenario[] }>('/scenarios')
  return response.data
}

export const getScenario = async (id: string) => {
  const response = await apiFetch<{ scenario: Scenario }>(`/scenarios/${id}`)
  return response.scenario
}

export const replayScenarioApi = async (id: string, at?: string) => {
  return apiFetch<ReplayResult>(`/scenarios/${id}/replay`, {
    method: 'POST',
    body: JSON.stringify({ at }),
  })
}
