import type { Pid } from '@/domain/dvc/dvc.types.ts'
import { buildCircadianTrace, meanTrace, smoothTrace, stdTrace, type CircadianTrace } from '@/domain/dvc/dvc.circadian.ts'

export type NamActivitySeries = {
  datasetId: string
  pid: Pid
  ts: string[]
  activity: number[]
}

export type CircadianSeriesTrace = {
  datasetId: string
  trace: CircadianTrace
}

export const buildCircadianTraceForSeries = (
  series: NamActivitySeries,
  smoothing: 'none' | 'light' = 'none'
) => {
  const trace = buildCircadianTrace({
    cageId: series.datasetId,
    pid: series.pid,
    ts: series.ts,
    activity: series.activity,
  })
  return smoothing === 'light' ? smoothTrace(trace, 3) : trace
}

export const buildMeanCircadianBundle = (
  seriesList: NamActivitySeries[],
  smoothing: 'none' | 'light' = 'none'
) => {
  if (!seriesList.length) {
    return {
      traces: [] as CircadianSeriesTrace[],
      mean: { hours: Array.from({ length: 24 }, (_, index) => index), values: Array(24).fill(0) },
      std: Array(24).fill(0),
    }
  }

  const traces = seriesList.map((series) => ({
    datasetId: series.datasetId,
    trace: buildCircadianTraceForSeries(series, smoothing),
  }))

  const mean = meanTrace(traces.map((item) => item.trace))
  const std = stdTrace(traces.map((item) => item.trace))
  return {
    traces,
    mean: smoothing === 'light' ? smoothTrace(mean, 3) : mean,
    std,
  }
}
