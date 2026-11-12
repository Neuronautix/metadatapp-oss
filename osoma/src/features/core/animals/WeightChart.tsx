import type { AnimalWeightEntry, AnimalWeightRange } from './animal.types.ts'
import { formatDateTime, formatNumber } from '@/lib/format.ts'

export function WeightChart({
  weights,
  range,
}: {
  weights: AnimalWeightEntry[]
  range?: AnimalWeightRange
}) {
  if (!weights.length) {
    return <div className="rounded-2xl border border-line bg-slate-50 p-6 text-sm text-slate-500">No weights yet.</div>
  }

  const width = 640
  const height = 220
  const padding = 28
  const values = weights.map((entry) => entry.grams)
  const minValue = Math.min(...values, range?.min ?? Infinity)
  const maxValue = Math.max(...values, range?.max ?? -Infinity)
  const rangeValue = maxValue - minValue || 1

  const xStep = (width - padding * 2) / Math.max(weights.length - 1, 1)

  const points = weights.map((entry, index) => {
    const x = padding + xStep * index
    const y = height - padding - ((entry.grams - minValue) / rangeValue) * (height - padding * 2)
    return { x, y }
  })

  const line = points.map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x},${point.y}`).join(' ')
  const area = `${line} L${points[points.length - 1].x},${height - padding} L${points[0].x},${height - padding} Z`

  const last = weights[weights.length - 1]

  return (
    <div className="space-y-3">
      <svg width="100%" height={height} viewBox={`0 0 ${width} ${height}`} aria-hidden="true">
        {range ? (
          <rect
            x={padding}
            y={height - padding - ((range.max - minValue) / rangeValue) * (height - padding * 2)}
            width={width - padding * 2}
            height={((range.max - range.min) / rangeValue) * (height - padding * 2)}
            fill="#e2e8f0"
            opacity="0.6"
          />
        ) : null}
        <path d={area} fill="#bfdbfe" opacity="0.35" />
        <path d={line} fill="none" stroke="#0f172a" strokeWidth="2" />
        {points.map((point, index) => (
          <circle key={weights[index].id} cx={point.x} cy={point.y} r="3" fill="#0f172a" />
        ))}
      </svg>
      <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
        <div>
          Latest weigh-in <span className="font-semibold text-slate-700">{formatNumber(last.grams)} g</span>
        </div>
        <div>{formatDateTime(last.at)}</div>
      </div>
    </div>
  )
}
