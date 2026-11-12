import { useMemo, useState } from 'react'
import { Badge } from '@/components/ui/badge.tsx'
import type {
  AIBehaviorOutput,
  CageEvent,
  DvcActivitySeries,
  EnvironmentRecord,
  QualitySignals,
  WelfareAlarm,
} from '@/domain/dvc/dvc.types.ts'
import type { DvcThresholds } from './ControlTower.tsx'
import { EnvironmentLayer } from './OverlayLayers/EnvironmentLayer.tsx'
import { EventsLayer } from './OverlayLayers/EventsLayer.tsx'
import { WelfareLayer } from './OverlayLayers/WelfareLayer.tsx'
import { AILayer } from './OverlayLayers/AILayer.tsx'

export type TimelineSelection =
  | { type: 'event'; event: CageEvent }
  | { type: 'alarm'; alarm: WelfareAlarm }
  | { type: 'ai'; ai: AIBehaviorOutput }

const WIDTH = 860
const HEIGHT = 260

const toTime = (value: string) => new Date(value).getTime()

const isNightHour = (ts: string) => {
  const hour = new Date(ts).getHours()
  return hour >= 19 || hour <= 6
}

const buildPath = (series: DvcActivitySeries, min: number, max: number, start: number, end: number) => {
  const range = Math.max(1, max - min)
  return series.ts
    .map((timestamp, index) => {
      const x = ((toTime(timestamp) - start) / Math.max(1, end - start)) * WIDTH
      const value = series.activity[index] ?? 0
      const y = HEIGHT - ((value - min) / range) * HEIGHT
      return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`
    })
    .join(' ')
}

const findNearest = (series: DvcActivitySeries, timestamp: number) => {
  let nearest = 0
  let diff = Number.POSITIVE_INFINITY
  series.ts.forEach((ts, index) => {
    const distance = Math.abs(toTime(ts) - timestamp)
    if (distance < diff) {
      diff = distance
      nearest = index
    }
  })
  return nearest
}

export function ActivityTimelineChart({
  activity,
  compare,
  environment,
  events,
  alarms,
  aiOutput,
  overlays,
  thresholds,
  quality,
  onHover,
  onSelect,
  pinnedTime,
}: {
  activity: DvcActivitySeries
  compare?: DvcActivitySeries | null
  environment?: EnvironmentRecord
  events: CageEvent[]
  alarms: WelfareAlarm[]
  aiOutput?: AIBehaviorOutput | null
  overlays: { environment: boolean; events: boolean; welfare: boolean; ai: boolean }
  thresholds: DvcThresholds
  quality?: QualitySignals | null
  onHover: (value: { ts: string; index: number } | null) => void
  onSelect: (value: TimelineSelection) => void
  pinnedTime?: string | null
}) {
  const [hoverIndex, setHoverIndex] = useState<number | null>(null)

  const times = activity.ts.map(toTime)
  const start = times[0] ?? Date.now()
  const end = times[times.length - 1] ?? Date.now()
  const values = activity.activity
  const minValue = values.length ? Math.min(...values) : 0
  const maxValue = values.length ? Math.max(...values) : 100

  const path = useMemo(() => buildPath(activity, minValue, maxValue, start, end), [activity, minValue, maxValue, start, end])
  const comparePath = useMemo(() => {
    if (!compare) return null
    const compareValues = compare.activity
    const compareMin = Math.min(...compareValues)
    const compareMax = Math.max(...compareValues)
    return buildPath(compare, compareMin, compareMax, start, end)
  }, [compare, start, end])

  const nightBands = useMemo(() => {
    const bands: Array<{ start: number; end: number }> = []
    let active = false
    let startIndex = 0

    activity.ts.forEach((timestamp, index) => {
      const isNight = isNightHour(timestamp)
      if (isNight && !active) {
        active = true
        startIndex = index
      }
      if (!isNight && active) {
        bands.push({ start: startIndex, end: index })
        active = false
      }
    })

    if (active) {
      bands.push({ start: startIndex, end: activity.ts.length - 1 })
    }
    return bands
  }, [activity.ts])

  const missingRanges = useMemo(() => {
    const ranges: Array<{ start: number; end: number }> = []
    if (activity.ts.length < 2) return ranges
    const expected = toTime(activity.ts[1]) - toTime(activity.ts[0])
    for (let i = 1; i < activity.ts.length; i += 1) {
      const gap = toTime(activity.ts[i]) - toTime(activity.ts[i - 1])
      if (gap > expected * 1.5) {
        ranges.push({ start: toTime(activity.ts[i - 1]), end: toTime(activity.ts[i]) })
      }
    }
    return ranges
  }, [activity.ts])

  const handleMove = (event: React.MouseEvent<HTMLDivElement>) => {
    const rect = event.currentTarget.getBoundingClientRect()
    const relativeX = Math.min(Math.max(0, event.clientX - rect.left), rect.width)
    const timestamp = start + (relativeX / rect.width) * (end - start)
    const nearest = findNearest(activity, timestamp)
    setHoverIndex(nearest)
    onHover({ ts: activity.ts[nearest], index: nearest })
  }

  const handleLeave = () => {
    setHoverIndex(null)
    onHover(null)
  }

  const hoverValue = hoverIndex !== null ? activity.activity[hoverIndex] : null

  const pinnedX = pinnedTime ? ((toTime(pinnedTime) - start) / Math.max(1, end - start)) * WIDTH : null

  return (
    <div className="relative" onMouseMove={handleMove} onMouseLeave={handleLeave}>
      <div className="mb-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <Badge variant="outline">Activity</Badge>
        {overlays.environment ? <Badge variant="outline">Environment</Badge> : null}
        {overlays.events ? <Badge variant="outline">Events</Badge> : null}
        {overlays.welfare ? <Badge variant="warning">Welfare</Badge> : null}
        {overlays.ai ? <Badge variant="outline">AI segments</Badge> : null}
        {compare ? <Badge variant="outline">Compare overlay</Badge> : null}
      </div>
      <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="h-64 w-full">
        {nightBands.map((band, index) => {
          const startX = ((toTime(activity.ts[band.start]) - start) / Math.max(1, end - start)) * WIDTH
          const endX = ((toTime(activity.ts[band.end]) - start) / Math.max(1, end - start)) * WIDTH
          return (
            <rect
              key={`night-${index}`}
              x={startX}
              y={0}
              width={Math.max(1, endX - startX)}
              height={HEIGHT}
              fill="#0f172a"
              opacity={0.06}
            />
          )
        })}

        {missingRanges.map((range, index) => {
          const startX = ((range.start - start) / Math.max(1, end - start)) * WIDTH
          const endX = ((range.end - start) / Math.max(1, end - start)) * WIDTH
          return (
            <rect
              key={`missing-${index}`}
              x={startX}
              y={0}
              width={Math.max(1, endX - startX)}
              height={HEIGHT}
              fill="#f1f5f9"
              opacity={0.8}
            />
          )
        })}

        {overlays.welfare ? (
          <WelfareLayer alarms={alarms} start={start} end={end} onSelect={onSelect} />
        ) : null}
        {overlays.ai && aiOutput ? (
          <AILayer aiOutput={aiOutput} start={start} end={end} onSelect={onSelect} />
        ) : null}
        {overlays.environment && environment ? (
          <EnvironmentLayer
            environment={environment}
            thresholds={thresholds}
            start={start}
            end={end}
            height={HEIGHT}
          />
        ) : null}

        <path d={path} fill="none" stroke="#0f172a" strokeWidth={2.2} />
        {comparePath ? <path d={comparePath} fill="none" stroke="#22c55e" strokeWidth={1.8} /> : null}

        {overlays.events ? (
          <EventsLayer events={events} start={start} end={end} onSelect={onSelect} />
        ) : null}

        {hoverIndex !== null ? (
          <line
            x1={((toTime(activity.ts[hoverIndex]) - start) / Math.max(1, end - start)) * WIDTH}
            x2={((toTime(activity.ts[hoverIndex]) - start) / Math.max(1, end - start)) * WIDTH}
            y1={0}
            y2={HEIGHT}
            stroke="#94a3b8"
            strokeDasharray="4 4"
          />
        ) : null}

        {pinnedX !== null ? (
          <line x1={pinnedX} x2={pinnedX} y1={0} y2={HEIGHT} stroke="#0ea5e9" strokeWidth={1.6} />
        ) : null}
      </svg>

      {hoverIndex !== null && hoverValue !== null ? (
        <div className="pointer-events-none absolute top-6 rounded-lg border border-line bg-white px-3 py-2 text-xs text-slate-600 shadow-subtle">
          <p className="font-semibold text-slate-700">Activity {hoverValue.toFixed(1)}</p>
          <p>{new Date(activity.ts[hoverIndex]).toLocaleString()}</p>
          {environment ? (
            <div className="mt-2 space-y-1 text-[11px]">
              <p>Temp {environment.temp[hoverIndex] ?? '--'} C</p>
              <p>Humidity {environment.humidity[hoverIndex] ?? '--'}%</p>
              <p>Light {environment.light[hoverIndex] ?? '--'} lux</p>
              <p>CO2 {environment.co2[hoverIndex] ?? '--'} ppm</p>
            </div>
          ) : null}
          {quality ? (
            <div className="mt-2 text-[11px] text-slate-500">
              <p>Sensor {quality.sensorHealth}</p>
              <p>Missing {quality.missingDataPct}%</p>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}
