import type { Scenario, ScenarioOverride } from '@/domain/scenario/scenario.types.ts'

const scenarioStore: Scenario[] = [
  {
    id: 'scn-reset-room201',
    label: 'HVAC tweak before spike',
    baseTimestamp: new Date(Date.now() - 90 * 60000).toISOString(),
    scope: { type: 'room', id: 'RM-201', label: 'Room 201' },
    overrides: {
      metricAdjustments: { temperature: -2, humidity: 4 },
      thresholds: { temperature: { max: 27 }, humidity: { min: 38 } },
      suppressAlarms: ['ALM-1001'],
      note: 'Pre-emptive venting lowers temp and humidity.',
    },
    createdBy: 'Scenario Seed',
    createdAt: new Date().toISOString(),
  },
]

const randomId = () => `scn-${Math.random().toString(36).slice(2, 8)}`

export const listScenarios = () => [...scenarioStore]

export const findScenario = (id: string) => scenarioStore.find((scenario) => scenario.id === id) ?? null

export const createScenario = (input: {
  label: string
  baseTimestamp: string
  scope: Scenario['scope']
  overrides?: ScenarioOverride
  createdBy?: string
}): Scenario => {
  const scenario: Scenario = {
    id: randomId(),
    label: input.label || 'Scenario',
    baseTimestamp: input.baseTimestamp,
    scope: input.scope,
    overrides: input.overrides ?? {},
    createdBy: input.createdBy ?? 'Scenario Builder',
    createdAt: new Date().toISOString(),
  }
  scenarioStore.unshift(scenario)
  return scenario
}
