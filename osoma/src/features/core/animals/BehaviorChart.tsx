import type { BehaviorSeries } from './animal.types.ts'

export function BehaviorChart({ series }: { series: BehaviorSeries }) {
  if (!series.points.length) {
    return <div className="rounded-2xl border border-line bg-slate-50 p-6 text-sm text-slate-500">No activity captured.</div>
  }

  const width = 640
  const height = 200
  const padding = 24
  const values = series.points.map((point) => point.activity)
  const min = Math.min(...values)
  const max = Math.max(...values)
  const range = max - min || 1

  const step = (width - padding * 2) / Math.max(series.points.length - 1, 1)
  const points = series.points.map((point, index) => {
    const x = padding + step * index
    const y = height - padding - ((point.activity - min) / range) * (height - padding * 2)
    return { x, y, anomaly: point.anomaly }
  })

  const line = points.map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x},${point.y}`).join(' ')

  return (
    <svg width="100%" height={height} viewBox={`0 0 ${width} ${height}`} aria-hidden="true">
      <path d={line} fill="none" stroke="#1d4ed8" strokeWidth="2" />
      {points.map((point, index) => (
        <circle
          key={index}
          cx={point.x}
          cy={point.y}
          r={point.anomaly ? 4 : 2}
          fill={point.anomaly ? '#f97316' : '#1d4ed8'}
        />
      ))}
    </svg>
  )
}
