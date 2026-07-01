import type { DvcActivitySeries, EnvironmentRecord, QualitySignals } from './dvc.types.ts'
import {
  buildPoints,
  computeCircadianStability,
  computeDayNightRatio,
  computeFragmentation,
  computeSleepScore,
} from './dvc.metrics.ts'

const median = (values: number[]) => {
  if (!values.length) return 0
  const sorted = [...values].sort((a, b) => a - b)
  const mid = Math.floor(sorted.length / 2)
  return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2
}

const mad = (values: number[]) => {
  if (!values.length) return 0
  const med = median(values)
  return median(values.map((value) => Math.abs(value - med)))
}

export interface AnomalyReport {
  anomalyScore: number
  risk: 'Low' | 'Medium' | 'High'
  reasons: string[]
  sleepScore: number
  circadianStability: number
  fragmentation: number
  dayNightRatio: number
}

export const computeAnomalies = (
  activity: DvcActivitySeries,
  environment: EnvironmentRecord,
  quality: QualitySignals,
  thresholds: {
    activityDropPct: number
    missingDataPct: number
    tempRange: { min: number; max: number }
    humidityRange: { min: number; max: number }
  }
): AnomalyReport => {
  const points = buildPoints(activity)
  const values = points.map((point) => point.value)
  const med = median(values)
  const deviation = mad(values)
  const dropThreshold = med - thresholds.activityDropPct * 0.01 * med

  const outliers = values.filter((value) => value < dropThreshold - deviation)
  const anomalyScore = Math.min(100, Math.round((outliers.length / Math.max(1, values.length)) * 200 + deviation))

  const reasons: string[] = []
  if (quality.missingDataPct > thresholds.missingDataPct) {
    reasons.push(`Missing data above ${thresholds.missingDataPct}%`)
  }

  const tempOut = environment.temp.filter(
    (value) => value < thresholds.tempRange.min || value > thresholds.tempRange.max
  )
  if (tempOut.length) {
    reasons.push('Temperature drift outside thresholds')
  }

  const humidityOut = environment.humidity.filter(
    (value) => value < thresholds.humidityRange.min || value > thresholds.humidityRange.max
  )
  if (humidityOut.length) {
    reasons.push('Humidity drift outside thresholds')
  }

  if (outliers.length / Math.max(1, values.length) > 0.12) {
    reasons.push('Activity drop sustained below baseline')
  }

  const dayNightRatio = computeDayNightRatio(activity)
  const circadianStability = computeCircadianStability(activity)
  const sleepScore = computeSleepScore(activity)
  const fragmentation = computeFragmentation(activity)

  if (dayNightRatio < 1.1 || dayNightRatio > 2.4) {
    reasons.push('Circadian ratio outside expected band')
  }

  const risk = anomalyScore > 70 || reasons.length >= 3 ? 'High' : anomalyScore > 40 || reasons.length >= 2 ? 'Medium' : 'Low'

  return {
    anomalyScore,
    risk,
    reasons,
    sleepScore,
    circadianStability,
    fragmentation,
    dayNightRatio,
  }
}
