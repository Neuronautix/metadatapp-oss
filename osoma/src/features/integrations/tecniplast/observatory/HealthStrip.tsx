import { Link } from 'react-router-dom'
import { ArrowDownRight, ArrowRight, ArrowUpRight } from 'lucide-react'
import type { HealthMetric } from './observatory.types.ts'
import { metricToneClasses, trendClasses } from './observatory.tokens.ts'
import { Skeleton } from '@/components/ui/skeleton.tsx'

const trendIconMap = {
  up: ArrowUpRight,
  down: ArrowDownRight,
  flat: ArrowRight,
}

export function HealthStrip({ metrics, isLoading }: { metrics: HealthMetric[]; isLoading?: boolean }) {
  if (isLoading) {
    return (
      <div className="grid gap-3 lg:grid-cols-5">
        {Array.from({ length: 5 }).map((_, index) => (
          <Skeleton key={index} className="h-24 w-full rounded-2xl" />
        ))}
      </div>
    )
  }

  return (
    <section className="space-y-3">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="font-display text-lg">System health overview</h3>
          <p className="text-sm text-slate-500">Calm readout of current risk posture.</p>
        </div>
        <span className="text-xs uppercase tracking-[0.3em] text-slate-400">Last 24h / 7d</span>
      </div>
      <div className="grid gap-3 lg:grid-cols-5">
        {metrics.map((metric) => {
          const TrendIcon = trendIconMap[metric.trend.direction]
          const delta = Math.round(metric.trend.delta)
          const deltaLabel = delta > 0 ? `+${delta}` : `${delta}`
          return (
            <Link
              key={metric.id}
              to={metric.href}
              className={`group rounded-2xl border p-4 transition hover:-translate-y-0.5 hover:shadow-subtle ${
                metricToneClasses[metric.tone]
              }`}
            >
              <div className="flex items-center justify-between">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                  {metric.label}
                </p>
                <span className={`flex items-center gap-1 text-xs ${trendClasses[metric.trend.direction]}`}>
                  <TrendIcon className="h-3.5 w-3.5" />
                  {deltaLabel}
                </span>
              </div>
              <div className="mt-3 text-2xl font-semibold text-slate-900">{metric.value}</div>
              <div className="mt-2 flex items-center justify-between text-xs text-slate-500">
                <span>{metric.helper}</span>
                <span className="text-[10px] uppercase tracking-[0.2em] text-slate-400">
                  {metric.trend.label}
                </span>
              </div>
            </Link>
          )
        })}
      </div>
    </section>
  )
}
