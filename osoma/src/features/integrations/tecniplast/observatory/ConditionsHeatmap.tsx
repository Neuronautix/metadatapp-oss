import { Link } from 'react-router-dom'
import type { HeatmapRow, MetricSeries } from './observatory.types.ts'
import { ObservatoryLegend } from './ObservatoryLegend.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'

const metricLabels: Record<string, string> = {
  temperature: 'Temperature',
  humidity: 'Humidity',
  pressure: 'Pressure',
}

const getCellColor = (value: number, min: number, max: number, status: 'ok' | 'out-of-range') => {
  if (max <= min) {
    return status === 'out-of-range' ? 'hsl(28 70% 90%)' : 'hsl(184 40% 92%)'
  }
  const ratio = Math.min(1, Math.max(0, (value - min) / (max - min)))
  const lightness = status === 'out-of-range' ? 88 - ratio * 18 : 92 - ratio * 14
  const hue = status === 'out-of-range' ? 28 : 184
  return `hsl(${hue} 55% ${lightness}%)`
}

export function ConditionsHeatmap({
  rows,
  series,
  isLoading,
}: {
  rows: HeatmapRow[]
  series: MetricSeries[]
  isLoading?: boolean
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Environmental patterns</CardTitle>
        <CardDescription>Conditions rendered as shapes, not raw numbers.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        {isLoading ? (
          <Skeleton className="h-64 w-full" />
        ) : (
          <div className="space-y-5">
            <div className="grid gap-2">
              <div className="grid grid-cols-[160px_repeat(3,1fr)] gap-2 text-xs text-slate-400">
                <span className="uppercase tracking-[0.2em]">Room</span>
                {Object.keys(metricLabels).map((metric) => (
                  <span key={metric} className="uppercase tracking-[0.2em]">
                    {metricLabels[metric]}
                  </span>
                ))}
              </div>
              {rows.map((row) => (
                <div key={row.id} className="grid grid-cols-[160px_repeat(3,1fr)] items-center gap-2">
                  <div className="flex items-center gap-2">
                    <span
                      className={`h-2.5 w-2.5 rounded-full ${row.status === 'watch' ? 'bg-amber-400' : 'bg-emerald-400'}`}
                    />
                    <Link to={`/tp/rooms/${row.id}`} className="text-sm font-medium text-slate-700 hover:text-slate-900">
                      {row.label}
                    </Link>
                  </div>
                  {row.cells.map((cell) => (
                    <div
                      key={`${row.id}-${cell.metric}`}
                      className="rounded-lg border border-white/60 px-3 py-2 text-xs text-slate-700 shadow-sm"
                      style={{ backgroundColor: getCellColor(cell.value, cell.min, cell.max, cell.status) }}
                      title={`${metricLabels[cell.metric]} • ${cell.value} ${cell.unit} (range ${cell.min}-${cell.max})`}
                    >
                      <div className="flex items-center justify-between">
                        <span className="font-semibold">{cell.value.toFixed(1)}</span>
                        <span className="text-[10px] uppercase tracking-[0.2em] text-slate-400">{cell.unit}</span>
                      </div>
                      <div className="mt-1 text-[10px] text-slate-500">
                        {cell.min}-{cell.max}
                      </div>
                    </div>
                  ))}
                </div>
              ))}
            </div>

            <div className="grid gap-3 md:grid-cols-3">
              {series.map((metric) => (
                <Link
                  key={metric.metric}
                  to="/tp/conditions"
                  className="rounded-2xl border border-line bg-slate-50/60 p-3 transition hover:-translate-y-0.5 hover:shadow-subtle"
                >
                  <div className="flex items-center justify-between text-xs text-slate-500">
                    <span className="font-semibold text-slate-700">{metricLabels[metric.metric]}</span>
                    <span>
                      {metric.min.toFixed(1)}-{metric.max.toFixed(1)} {metric.unit}
                    </span>
                  </div>
                  <div className="mt-3 flex h-16 items-end gap-1">
                    {metric.values.map((value, index) => {
                      const height = metric.max > metric.min ? ((value - metric.min) / (metric.max - metric.min)) * 100 : 0
                      return (
                        <span
                          key={`${metric.metric}-${metric.labels[index]}`}
                          className="flex-1 rounded-md bg-slate-300/60"
                          style={{ height: `${Math.max(12, height)}%` }}
                          title={`${metric.labels[index]} • ${value.toFixed(1)} ${metric.unit}`}
                        />
                      )
                    })}
                  </div>
                  <div className="mt-2 text-[10px] uppercase tracking-[0.2em] text-slate-400">Small multiple</div>
                </Link>
              ))}
            </div>

            <ObservatoryLegend
              items={[
                { label: 'Within range', color: 'bg-emerald-400' },
                { label: 'Out-of-range', color: 'bg-amber-400' },
              ]}
            />
          </div>
        )}
      </CardContent>
    </Card>
  )
}
