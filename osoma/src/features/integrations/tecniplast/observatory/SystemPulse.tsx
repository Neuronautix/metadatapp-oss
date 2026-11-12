import { Link } from 'react-router-dom'
import type { PulseLane, PulseRange } from './observatory.types.ts'
import { laneAccentClasses, laneDotColors, pulseRangeConfig } from './observatory.tokens.ts'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'

export function SystemPulse({
  range,
  onRangeChange,
  lanes,
  isLoading,
}: {
  range: PulseRange
  onRangeChange: (range: PulseRange) => void
  lanes: PulseLane[]
  isLoading?: boolean
}) {
  return (
    <section className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 className="font-display text-lg">System Pulse Timeline</h3>
          <p className="text-sm text-slate-500">Layered signal density across alarms, protocols, anomalies, and humans.</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {(Object.keys(pulseRangeConfig) as PulseRange[]).map((key) => (
            <Button
              key={key}
              size="sm"
              variant={range === key ? 'default' : 'outline'}
              onClick={() => onRangeChange(key)}
            >
              {pulseRangeConfig[key].label}
            </Button>
          ))}
        </div>
      </div>

      {isLoading ? (
        <Skeleton className="h-64 w-full" />
      ) : (
        <div className="space-y-3">
          {lanes.map((lane) => (
            <Link
              key={lane.id}
              to={lane.href}
              className="group block rounded-2xl border border-line bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-subtle"
            >
              <div className="flex items-center justify-between text-sm">
                <span className="font-semibold text-slate-700">{lane.label}</span>
                <span className="text-xs text-slate-500">{lane.total} events</span>
              </div>
              <div className="relative mt-3 h-16 overflow-hidden rounded-xl">
                <div
                  className={`absolute inset-0 rounded-xl bg-gradient-to-r ${laneAccentClasses[lane.accent]}`}
                />
                <div className="relative z-10 flex h-full items-end gap-[2px] px-1">
                  {lane.bins.map((bin) => {
                    const height = Math.max(6, Math.round((bin.count / lane.max) * 100))
                    return (
                      <span
                        key={bin.index}
                        title={bin.sampleLabel ? `${bin.count} signals • ${bin.sampleLabel}` : `${bin.count} signals`}
                        className={`flex-1 rounded-sm transition ${laneDotColors[lane.accent]} opacity-70 group-hover:opacity-90`}
                        style={{ height: `${height}%` }}
                      />
                    )
                  })}
                </div>
              </div>
              <div className="mt-2 flex items-center justify-between text-xs text-slate-400">
                <span>Dense clusters surface in brighter bands</span>
                <span>{pulseRangeConfig[range].label} focus</span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </section>
  )
}
