import type { DvcActivitySeries } from './dvc.types.ts'

const NIGHT_START = 19
const NIGHT_END = 6

const isNightHour = (ts: string) => {
  const hour = new Date(ts).getHours()
  return hour >= NIGHT_START || hour <= NIGHT_END
}

export const buildPoints = (series: DvcActivitySeries) => {
  return series.ts.map((timestamp, index) => ({ ts: timestamp, value: series.activity[index] ?? 0 }))
}

export const computeAuc = (series: DvcActivitySeries) => {
  const points = buildPoints(series)
  if (points.length < 2) return 0
  let auc = 0
  for (let i = 1; i < points.length; i += 1) {
    const prev = points[i - 1]
    const curr = points[i]
    const delta = (new Date(curr.ts).getTime() - new Date(prev.ts).getTime()) / 3600000
    auc += ((prev.value + curr.value) / 2) * delta
  }
  return Number(auc.toFixed(2))
}

export const computeDayNightRatio = (series: DvcActivitySeries) => {
  const points = buildPoints(series)
  let daySum = 0
  let nightSum = 0
  let dayCount = 0
  let nightCount = 0

  points.forEach((point) => {
    if (isNightHour(point.ts)) {
      nightSum += point.value
      nightCount += 1
    } else {
      daySum += point.value
      dayCount += 1
    }
  })

  const dayAvg = dayCount ? daySum / dayCount : 0
  const nightAvg = nightCount ? nightSum / nightCount : 0
  return nightAvg ? Number((dayAvg / nightAvg).toFixed(2)) : 0
}

export const computeSleepScore = (series: DvcActivitySeries, threshold = 35) => {
  const points = buildPoints(series)
  const nightPoints = points.filter((point) => isNightHour(point.ts))
  if (!nightPoints.length) return 0
  const sleepPoints = nightPoints.filter((point) => point.value <= threshold)
  return Math.round((sleepPoints.length / nightPoints.length) * 100)
}

export const computeCircadianStability = (series: DvcActivitySeries) => {
  const points = buildPoints(series)
  if (!points.length) return 0
  const dayNightRatio = computeDayNightRatio(series)
  const variance = points.reduce((acc, point) => acc + Math.abs(point.value - (points[0]?.value ?? 0)), 0) / points.length
  const stability = Math.max(0, 100 - Math.min(60, variance))
  const ratioPenalty = Math.abs(1.5 - dayNightRatio) * 12
  return Math.max(0, Math.round(stability - ratioPenalty))
}

export const computeFragmentation = (series: DvcActivitySeries, threshold = 35) => {
  const points = buildPoints(series).filter((point) => isNightHour(point.ts))
  if (points.length < 2) return 0
  let transitions = 0
  let asleep = points[0].value <= threshold
  for (let i = 1; i < points.length; i += 1) {
    const nowAsleep = points[i].value <= threshold
    if (nowAsleep !== asleep) {
      transitions += 1
      asleep = nowAsleep
    }
  }
  return Math.min(100, Math.round((transitions / points.length) * 220))
}
