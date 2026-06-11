import type { AIBehaviorOutput } from './dvc.types.ts'
import type { AnomalyReport } from './dvc.anomalies.ts'

export const buildBehaviorExplanation = (report: AnomalyReport) => {
  if (report.anomalyScore > 70) {
    return `Anomaly score ${report.anomalyScore} with ${report.reasons.length} contributing signals. Activity dropped below baseline and circadian stability at ${report.circadianStability}%.`
  }
  if (report.anomalyScore > 40) {
    return `Moderate anomaly score ${report.anomalyScore}. Day/night ratio ${report.dayNightRatio} and sleep score ${report.sleepScore}%.`
  }
  return `Activity remains within baseline. Sleep score ${report.sleepScore}% with circadian stability ${report.circadianStability}%.`
}

export const toBehaviorOutput = (
  cageId: string,
  windowStart: string,
  windowEnd: string,
  report: AnomalyReport
): AIBehaviorOutput => {
  const label = report.anomalyScore > 70 ? 'anomaly' : report.circadianStability < 50 ? 'circadian' : 'sleep'
  return {
    id: `AI-${cageId}`,
    cageId,
    windowStart,
    windowEnd,
    sleepScore: report.sleepScore,
    circadianStability: report.circadianStability,
    fragmentation: report.fragmentation,
    anomalyScore: report.anomalyScore,
    label,
    explanation: buildBehaviorExplanation(report),
  }
}
