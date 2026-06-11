import type { DvcActivitySeries } from './dvc.types.ts'

export type CircadianTrace = {
  hours: number[]
  values: number[]
}

export const buildCircadianTrace = (series: DvcActivitySeries, windowHours?: number): CircadianTrace => {
  const hours = Array.from({ length: 24 }, (_, index) => index)
  const sums = Array.from({ length: 24 }, () => 0)
  const counts = Array.from({ length: 24 }, () => 0)

  const cutoff = windowHours
    ? new Date(series.ts[series.ts.length - 1] ?? new Date().toISOString()).getTime() -
      windowHours * 60 * 60 * 1000
    : null

  series.ts.forEach((timestamp, index) => {
    const ts = new Date(timestamp).getTime()
    if (cutoff && ts < cutoff) return
    const hour = new Date(timestamp).getHours()
    sums[hour] += series.activity[index] ?? 0
    counts[hour] += 1
  })

  const values = sums.map((sum, hour) => (counts[hour] ? sum / counts[hour] : 0))
  return { hours, values }
}

export const smoothTrace = (trace: CircadianTrace, window = 3): CircadianTrace => {
  if (window <= 1) return trace
  const values = trace.values.map((_, index) => {
    const start = Math.max(0, index - Math.floor(window / 2))
    const end = Math.min(trace.values.length, index + Math.ceil(window / 2))
    const slice = trace.values.slice(start, end)
    const avg = slice.reduce((acc, value) => acc + value, 0) / Math.max(1, slice.length)
    return avg
  })
  return { hours: trace.hours, values }
}

export const meanTrace = (traces: CircadianTrace[]): CircadianTrace => {
  if (!traces.length) return { hours: Array.from({ length: 24 }, (_, index) => index), values: Array(24).fill(0) }
  const values = traces[0].hours.map((_, hour) => {
    const total = traces.reduce((acc, trace) => acc + (trace.values[hour] ?? 0), 0)
    return total / traces.length
  })
  return { hours: traces[0].hours, values }
}

export const stdTrace = (traces: CircadianTrace[]): number[] => {
  if (!traces.length) return Array(24).fill(0)
  const mean = meanTrace(traces)
  return mean.hours.map((_, hour) => {
    const variance = traces.reduce((acc, trace) => {
      const delta = (trace.values[hour] ?? 0) - mean.values[hour]
      return acc + delta * delta
    }, 0) / traces.length
    return Math.sqrt(variance)
  })
}
