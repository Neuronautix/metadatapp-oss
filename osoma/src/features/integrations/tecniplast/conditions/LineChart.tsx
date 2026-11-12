import { cn } from '@/lib/utils.ts'

export type ChartPoint = { at: string; value: number }

export function LineChart({
  points,
  min,
  max,
  unit,
  className,
}: {
  points: ChartPoint[]
  min?: number
  max?: number
  unit?: string
  className?: string
}) {
  if (!points.length) return null

  const width = 640
  const height = 160
  const padding = 24
  const values = points.map((point) => point.value)
  const dataMin = Math.min(...values, min ?? Infinity)
  const dataMax = Math.max(...values, max ?? -Infinity)
  const range = dataMax - dataMin || 1

  const toX = (index: number) =>
    padding + (index / Math.max(points.length - 1, 1)) * (width - padding * 2)

  const toY = (value: number) =>
    height - padding - ((value - dataMin) / range) * (height - padding * 2)

  const path = points
    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${toX(index)} ${toY(point.value)}`)
    .join(' ')

  const minLine = min !== undefined ? toY(min) : null
  const maxLine = max !== undefined ? toY(max) : null

  return (
    <div className={cn('w-full', className)}>
      <svg viewBox={`0 0 ${width} ${height}`} className="w-full">
        <rect x="0" y="0" width={width} height={height} rx="12" className="fill-slate-50" />
        {minLine !== null ? (
          <line x1={padding} x2={width - padding} y1={minLine} y2={minLine} stroke="#94a3b8" strokeDasharray="4 4" />
        ) : null}
        {maxLine !== null ? (
          <line x1={padding} x2={width - padding} y1={maxLine} y2={maxLine} stroke="#94a3b8" strokeDasharray="4 4" />
        ) : null}
        <path d={path} fill="none" stroke="#0f172a" strokeWidth="2" />
      </svg>
      <div className="mt-2 flex items-center justify-between text-xs text-slate-500">
        <span>{points[0]?.at.slice(0, 10)}</span>
        <span className="text-slate-600">{unit ? `Unit: ${unit}` : ''}</span>
        <span>{points[points.length - 1]?.at.slice(0, 10)}</span>
      </div>
    </div>
  )
}
