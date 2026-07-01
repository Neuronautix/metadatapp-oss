import type { InvestigationGroupWeightPoint } from './investigation.types.ts'
import { formatDate } from '@/lib/format.ts'

export function InvestigationGroupWeightChart({ points }: { points: InvestigationGroupWeightPoint[] }) {
  if (!points.length) {
    return <div className="rounded-2xl border border-line bg-slate-50 p-6 text-sm text-slate-500">No weight series yet.</div>
  }

  const width = 640
  const height = 200
  const padding = 26
  const minValue = Math.min(...points.map((point) => point.min))
  const maxValue = Math.max(...points.map((point) => point.max))
  const range = maxValue - minValue || 1
  const step = (width - padding * 2) / Math.max(points.length - 1, 1)

  const avgPoints = points.map((point, index) => {
    const x = padding + step * index
    const y = height - padding - ((point.average - minValue) / range) * (height - padding * 2)
    return { x, y }
  })

  const minPoints = points.map((point, index) => {
    const x = padding + step * index
    const y = height - padding - ((point.min - minValue) / range) * (height - padding * 2)
    return { x, y }
  })

  const maxPoints = points.map((point, index) => {
    const x = padding + step * index
    const y = height - padding - ((point.max - minValue) / range) * (height - padding * 2)
    return { x, y }
  })

  const avgLine = avgPoints.map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x},${point.y}`).join(' ')
  const band = `${maxPoints
    .map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x},${point.y}`)
    .join(' ')} ${minPoints
    .slice()
    .reverse()
    .map((point) => `L${point.x},${point.y}`)
    .join(' ')} Z`

  return (
    <div className="space-y-3">
      <svg width="100%" height={height} viewBox={`0 0 ${width} ${height}`} aria-hidden="true">
        <path d={band} fill="#bae6fd" opacity="0.5" />
        <path d={avgLine} fill="none" stroke="#0f172a" strokeWidth="2" />
      </svg>
      <div className="flex items-center justify-between text-xs text-slate-500">
        <span>{formatDate(points[0].at)}</span>
        <span>{formatDate(points[points.length - 1].at)}</span>
      </div>
    </div>
  )
}
