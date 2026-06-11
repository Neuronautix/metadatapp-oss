import { Badge } from '@/components/ui/badge.tsx'
import type { DvcActivitySeries } from '@/domain/dvc/dvc.types.ts'

const WIDTH = 860
const HEIGHT = 160

const buildHourlyMean = (series: DvcActivitySeries) => {
  const sums = Array.from({ length: 24 }, () => 0)
  const counts = Array.from({ length: 24 }, () => 0)
  series.ts.forEach((timestamp, index) => {
    const hour = new Date(timestamp).getHours()
    sums[hour] += series.activity[index] ?? 0
    counts[hour] += 1
  })
  return sums.map((sum, hour) => (counts[hour] ? sum / counts[hour] : 0))
}

const buildMeanAcross = (seriesList: DvcActivitySeries[]) => {
  if (!seriesList.length) return null
  const hourlyMeans = seriesList.map(buildHourlyMean)
  const combined = Array.from({ length: 24 }, (_, hour) => {
    const values = hourlyMeans.map((list) => list[hour] ?? 0)
    const total = values.reduce((acc, value) => acc + value, 0)
    return values.length ? total / values.length : 0
  })
  return combined
}

const buildPath = (values: number[], min: number, max: number) => {
  const range = Math.max(1, max - min)
  return values
    .map((value, hour) => {
      const x = (hour / 23) * WIDTH
      const y = HEIGHT - ((value - min) / range) * HEIGHT
      return `${hour === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`
    })
    .join(' ')
}

export function CircadianTraceChart({
  activity,
  compareSeries,
  compareMode,
}: {
  activity: DvcActivitySeries
  compareSeries: DvcActivitySeries[]
  compareMode: 'none' | 'cage' | 'vcg'
}) {
  const targetMeans = buildHourlyMean(activity)
  const compareMeans = compareSeries.length ? buildMeanAcross(compareSeries) : null
  const values = [...targetMeans, ...(compareMeans ?? [])]
  const minValue = values.length ? Math.min(...values) : 0
  const maxValue = values.length ? Math.max(...values) : 100

  const targetPath = buildPath(targetMeans, minValue, maxValue)
  const comparePath = compareMeans ? buildPath(compareMeans, minValue, maxValue) : null

  return (
    <div className="mt-6">
      <div className="mb-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <Badge variant="outline">Circadian trace (24h)</Badge>
        {comparePath ? (
          <Badge variant="outline">
            {compareMode === 'vcg' ? 'VCG mean' : 'Compare cage'}
          </Badge>
        ) : null}
      </div>
      <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="h-40 w-full">
        <rect x={0} y={0} width={WIDTH} height={HEIGHT} fill="#f8fafc" />
        {Array.from({ length: 6 }).map((_, index) => (
          <line
            key={`grid-${index}`}
            x1={(index / 5) * WIDTH}
            x2={(index / 5) * WIDTH}
            y1={0}
            y2={HEIGHT}
            stroke="#e2e8f0"
            strokeWidth={1}
          />
        ))}
        <path d={targetPath} fill="none" stroke="#0f172a" strokeWidth={2} />
        {comparePath ? <path d={comparePath} fill="none" stroke="#22c55e" strokeWidth={2} /> : null}
      </svg>
      <div className="mt-2 flex justify-between text-[10px] text-slate-400">
        {[0, 6, 12, 18, 23].map((hour) => (
          <span key={hour}>{hour}:00</span>
        ))}
      </div>
    </div>
  )
}
