import { Badge } from '@/components/ui/badge.tsx'
import type { CircadianTrace } from '@/domain/dvc/dvc.circadian.ts'

const WIDTH = 860
const HEIGHT = 180

const buildPath = (trace: CircadianTrace, min: number, max: number) => {
  const range = Math.max(1, max - min)
  return trace.values
    .map((value, index) => {
      const x = (trace.hours[index] / 23) * WIDTH
      const y = HEIGHT - ((value - min) / range) * HEIGHT
      return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`
    })
    .join(' ')
}

export function Circadian24hChart({
  title,
  target,
  baseline,
  mean,
  stdBand,
}: {
  title: string
  target: CircadianTrace
  baseline?: CircadianTrace | null
  mean?: CircadianTrace | null
  stdBand?: number[] | null
}) {
  const values = [...target.values, ...(baseline?.values ?? []), ...(mean?.values ?? [])]
  const minValue = values.length ? Math.min(...values) : 0
  const maxValue = values.length ? Math.max(...values) : 100

  const targetPath = buildPath(target, minValue, maxValue)
  const baselinePath = baseline ? buildPath(baseline, minValue, maxValue) : null
  const meanPath = mean ? buildPath(mean, minValue, maxValue) : null

  return (
    <div className="mt-4">
      <div className="mb-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <Badge variant="outline">{title}</Badge>
        <Badge variant="outline">Day/Night</Badge>
        {baseline ? <Badge variant="outline">Baseline</Badge> : null}
        {mean ? <Badge variant="outline">VCG mean</Badge> : null}
      </div>
      <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="h-44 w-full">
        <rect x={0} y={0} width={WIDTH} height={HEIGHT} fill="#f8fafc" />
        <rect x={(19 / 24) * WIDTH} y={0} width={((24 - 19) / 24) * WIDTH} height={HEIGHT} fill="#0f172a" opacity={0.08} />
        <rect x={0} y={0} width={(6 / 24) * WIDTH} height={HEIGHT} fill="#0f172a" opacity={0.08} />
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
        {stdBand && mean ? (
          <path
            d={mean.values
              .map((value, index) => {
                const x = (mean.hours[index] / 23) * WIDTH
                const y = HEIGHT - ((value + (stdBand[index] ?? 0) - minValue) / Math.max(1, maxValue - minValue)) * HEIGHT
                return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`
              })
              .join(' ')}
            fill="none"
            stroke="#86efac"
            strokeWidth={1}
            opacity={0.6}
          />
        ) : null}
        {baselinePath ? <path d={baselinePath} fill="none" stroke="#94a3b8" strokeWidth={2} /> : null}
        {meanPath ? <path d={meanPath} fill="none" stroke="#22c55e" strokeWidth={2} /> : null}
        <path d={targetPath} fill="none" stroke="#0f172a" strokeWidth={2.4} />
      </svg>
      <div className="mt-2 flex justify-between text-[10px] text-slate-400">
        {[0, 6, 12, 18, 23].map((hour) => (
          <span key={hour}>{hour}:00</span>
        ))}
      </div>
    </div>
  )
}
