import type { ScopeType } from '../scope.ts'
import type { Alarm, ConditionMetric, ConditionSnapshotMetric, ConditionThreshold } from '@/features/integrations/tecniplast/tecniplast.types.ts'
import type { ScenarioOverride, ScenarioSnapshot } from './scenario.types.ts'

const metricDefaults: Record<ConditionMetric, { base: number; range: number }> = {
  temperature: { base: 23, range: 2.4 },
  humidity: { base: 52, range: 8 },
  pressure: { base: 1008, range: 10 },
  co2: { base: 620, range: 180 },
  light: { base: 90, range: 50 },
  noise: { base: 45, range: 12 },
}

const seedValue = (seed: string) => {
  let value = 0
  for (let i = 0; i < seed.length; i += 1) {
    value += seed.charCodeAt(i) * (i + 1)
  }
  return value
}

export const computeMetricValue = (
  metric: ConditionMetric,
  scopeId: string,
  at: number,
  opts?: { outOfRangeScopes?: Set<string>; metricAdjustments?: Partial<Record<ConditionMetric, number>> }
) => {
  const base = metricDefaults[metric]
  const seed = seedValue(`${scopeId}-${metric}`)
  const wave = Math.sin(at / 3600000 + (seed % 10))
  const noise = Math.cos(at / 1800000 + (seed % 7))
  let value = base.base + base.range * wave + (base.range / 3) * noise

  if (opts?.outOfRangeScopes?.has(scopeId) && metric === 'temperature') {
    value += 3.5
  }
  if (opts?.outOfRangeScopes?.has(scopeId) && metric === 'humidity') {
    value -= 8
  }
  if (opts?.metricAdjustments?.[metric]) {
    value += opts.metricAdjustments[metric] ?? 0
  }

  return Number(value.toFixed(2))
}

export const applyThresholdOverrides = (
  thresholds: ConditionThreshold[],
  overrides?: ScenarioOverride['thresholds']
) => {
  if (!overrides) return thresholds
  return thresholds.map((threshold) => {
    const override = overrides[threshold.metric]
    if (!override) return threshold
    return {
      ...threshold,
      min: override.min ?? threshold.min,
      max: override.max ?? threshold.max,
    }
  })
}

const classifySeverity = (delta: number, span: number) => {
  if (delta <= 0) return 'low' as const
  const ratio = span > 0 ? delta / span : delta
  if (ratio > 0.6) return 'critical' as const
  if (ratio > 0.35) return 'high' as const
  if (ratio > 0.2) return 'medium' as const
  return 'low' as const
}

export const buildMetricSnapshot = (args: {
  scopeId: string
  at: number
  thresholds: ConditionThreshold[]
  outOfRangeScopes?: Set<string>
  overrides?: ScenarioOverride
}): ConditionSnapshotMetric[] => {
  const { scopeId, at, thresholds, outOfRangeScopes, overrides } = args
  return thresholds.map((threshold) => {
    const value = computeMetricValue(threshold.metric, scopeId, at, {
      outOfRangeScopes,
      metricAdjustments: overrides?.metricAdjustments,
    })
    const status = value < threshold.min || value > threshold.max ? 'out-of-range' : 'ok'
    return {
      ...threshold,
      value,
      status,
    }
  })
}

export const deriveAlarmsFromMetrics = (args: {
  metrics: ConditionSnapshotMetric[]
  thresholds: ConditionThreshold[]
  baseAlarms?: Alarm[]
  scopeType: ScopeType
  scopeId: string
  scopeLabel?: string
  at: string
  overrides?: ScenarioOverride
}): Alarm[] => {
  const { metrics, thresholds, baseAlarms, scopeType, scopeId, at, overrides, scopeLabel } = args
  const suppressed = new Set(overrides?.suppressAlarms ?? [])
  const alarmByMetric = new Map<ConditionMetric, Alarm>()

  baseAlarms
    ?.filter((alarm) => alarm.scopeId === scopeId && alarm.scopeType === scopeType)
    .forEach((alarm) => {
      if (!suppressed.has(alarm.id)) {
        alarmByMetric.set(alarm.sensorType as ConditionMetric, { ...alarm })
      }
    })

  metrics.forEach((metric) => {
    const threshold = thresholds.find((item) => item.metric === metric.metric) ?? {
      min: metric.value - 1,
      max: metric.value + 1,
      unit: '',
      metric: metric.metric,
    }
    const delta = metric.status === 'out-of-range' ? Math.max(0, metric.value - threshold.max, threshold.min - metric.value) : 0
    const severity = classifySeverity(delta, threshold.max - threshold.min)
    const existing = alarmByMetric.get(metric.metric)
    if (metric.status === 'out-of-range') {
      const newAlarm: Alarm = existing
        ? {
            ...existing,
            severity,
            status: 'active',
            message: `${metric.metric} ${metric.value}${threshold.unit} outside ${threshold.min}-${threshold.max}${threshold.unit}`,
            updatedAt: at,
            resolvedAt: undefined,
          }
        : {
            id: `ALM-SIM-${scopeId}-${metric.metric}`,
            severity,
            status: 'active',
            scopeType,
            scopeId,
            scopeLabel: scopeLabel ?? scopeId,
            sensorType: metric.metric,
            message: `${metric.metric} ${metric.value}${threshold.unit} outside ${threshold.min}-${threshold.max}${threshold.unit}`,
            createdAt: at,
            updatedAt: at,
            correlationId: `CORR-SIM-${scopeId}-${metric.metric}`,
          }
      alarmByMetric.set(metric.metric, newAlarm)
    } else if (existing) {
      alarmByMetric.set(metric.metric, {
        ...existing,
        status: 'resolved',
        resolvedAt: at,
        updatedAt: at,
      })
    }
  })

  return Array.from(alarmByMetric.values()).sort((a, b) => new Date(b.updatedAt).getTime() - new Date(a.updatedAt).getTime())
}

export const buildScenarioSnapshot = (args: {
  scopeType: ScopeType
  scopeId: string
  scopeLabel?: string
  at: string
  thresholds: ConditionThreshold[]
  baseAlarms?: Alarm[]
  outOfRangeScopes?: Set<string>
  overrides?: ScenarioOverride
  mode: 'reality' | 'scenario'
  scenarioId?: string
  scenarioLabel?: string
}): ScenarioSnapshot => {
  const { scopeType, scopeId, scopeLabel, at, thresholds, baseAlarms, outOfRangeScopes, overrides, mode, scenarioId, scenarioLabel } =
    args
  const time = new Date(at).getTime()
  const metrics = buildMetricSnapshot({ scopeId, at: time, thresholds, outOfRangeScopes, overrides })
  const alarms = deriveAlarmsFromMetrics({
    metrics,
    thresholds,
    baseAlarms,
    scopeType,
    scopeId,
    scopeLabel,
    at,
    overrides,
  })

  return {
    at,
    collectedAt: at,
    scopeType,
    scopeId,
    thresholds,
    metrics,
    alarms,
    mode,
    scenarioId,
    scenarioLabel,
  }
}
