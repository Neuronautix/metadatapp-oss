import type { ScopeType } from '../scope.ts'
import type { Alarm, ConditionThreshold } from '@/features/integrations/tecniplast/tecniplast.types.ts'
import type { ReplayResult, Scenario } from './scenario.types.ts'
import { applyThresholdOverrides, buildScenarioSnapshot } from './state-snapshot.ts'
import { diffSnapshots } from './rule-evaluator.ts'

export const replayScenario = (args: {
  scopeType: ScopeType
  scopeId: string
  scopeLabel?: string
  at: string
  thresholds: ConditionThreshold[]
  baseAlarms?: Alarm[]
  outOfRangeScopes?: Set<string>
  scenario?: Scenario | null
}): ReplayResult => {
  const { scopeType, scopeId, scopeLabel, thresholds, baseAlarms, outOfRangeScopes, scenario } = args
  const effectiveAt = args.at || scenario?.baseTimestamp || new Date().toISOString()
  const scenarioAt = scenario?.overrides?.scheduleOffsetMinutes
    ? new Date(new Date(effectiveAt).getTime() + scenario.overrides.scheduleOffsetMinutes * 60000).toISOString()
    : effectiveAt

  const realitySnapshot = buildScenarioSnapshot({
    scopeType,
    scopeId,
    scopeLabel,
    at: effectiveAt,
    thresholds,
    baseAlarms,
    outOfRangeScopes,
    overrides: undefined,
    mode: 'reality',
  })

  const scenarioSnapshot = buildScenarioSnapshot({
    scopeType,
    scopeId,
    scopeLabel,
    at: scenarioAt,
    thresholds: applyThresholdOverrides(thresholds, scenario?.overrides?.thresholds),
    baseAlarms,
    outOfRangeScopes,
    overrides: scenario?.overrides,
    mode: 'scenario',
    scenarioId: scenario?.id,
    scenarioLabel: scenario?.label,
  })

  const deltas = diffSnapshots(realitySnapshot, scenarioSnapshot)

  return { reality: realitySnapshot, scenario: scenarioSnapshot, deltas }
}
