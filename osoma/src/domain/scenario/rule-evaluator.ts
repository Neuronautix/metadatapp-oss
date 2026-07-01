import type { ScenarioDelta, ScenarioSnapshot } from './scenario.types.ts'

export const diffSnapshots = (reality: ScenarioSnapshot, scenario: ScenarioSnapshot): ScenarioDelta[] => {
  const deltas: ScenarioDelta[] = []

  const metricMap = new Map(reality.metrics.map((metric) => [metric.metric, metric]))
  scenario.metrics.forEach((metric) => {
    const baseline = metricMap.get(metric.metric)
    if (!baseline) return
    if (baseline.status === 'out-of-range' && metric.status === 'ok') {
      deltas.push({
        id: `metric-${metric.metric}-resolved`,
        type: 'metric',
        title: `${metric.metric} stabilized`,
        detail: `${metric.metric} moved back inside range (${metric.value}${metric.unit}).`,
        impact: 'positive',
      })
    } else if (baseline.status === 'ok' && metric.status === 'out-of-range') {
      deltas.push({
        id: `metric-${metric.metric}-degraded`,
        type: 'metric',
        title: `${metric.metric} drifted out`,
        detail: `${metric.metric} now outside range (${metric.value}${metric.unit}).`,
        impact: 'negative',
      })
    }
  })

  const alarmMap = new Map(reality.alarms.map((alarm) => [alarm.sensorType, alarm]))
  scenario.alarms.forEach((alarm) => {
    const baseline = alarmMap.get(alarm.sensorType)
    if (!baseline && alarm.status === 'active') {
      deltas.push({
        id: `alarm-${alarm.sensorType}-new`,
        type: 'alarm',
        title: `New alarm: ${alarm.sensorType}`,
        detail: alarm.message,
        impact: 'negative',
      })
    } else if (baseline && baseline.status === 'active' && alarm.status === 'resolved') {
      deltas.push({
        id: `alarm-${alarm.sensorType}-resolved`,
        type: 'alarm',
        title: `Alarm cleared: ${alarm.sensorType}`,
        detail: `Resolved at ${alarm.updatedAt}`,
        impact: 'positive',
      })
    } else if (baseline && baseline.severity !== alarm.severity) {
      deltas.push({
        id: `alarm-${alarm.sensorType}-severity`,
        type: 'alarm',
        title: `${alarm.sensorType} severity changed`,
        detail: `Severity ${baseline.severity} → ${alarm.severity}`,
        impact: alarm.severity < baseline.severity ? 'positive' : 'negative',
      })
    }
  })

  scenario.thresholds.forEach((threshold) => {
    const baseline = reality.thresholds.find((item) => item.metric === threshold.metric)
    if (!baseline) return
    if (baseline.min !== threshold.min || baseline.max !== threshold.max) {
      deltas.push({
        id: `threshold-${threshold.metric}`,
        type: 'threshold',
        title: `${threshold.metric} thresholds adjusted`,
        detail: `Range ${baseline.min}-${baseline.max}${baseline.unit} → ${threshold.min}-${threshold.max}${threshold.unit}`,
        impact: 'neutral',
      })
    }
  })

  return deltas
}
