import { useMemo } from 'react'
import {
  Activity,
  AlertTriangle,
  ClipboardCheck,
  FlaskConical,
  LineChart,
  Radar,
  TestTube2,
} from 'lucide-react'
import type { AnimalTimelineEvent } from './animal.types.ts'
import { formatDateTime } from '@/lib/format.ts'

const iconMap: Record<AnimalTimelineEvent['kind'], React.ElementType> = {
  assignment: ClipboardCheck,
  weight: Activity,
  treatment: FlaskConical,
  sample: TestTube2,
  analysis: LineChart,
  behavior: Radar,
  alert: AlertTriangle,
}

const kindLabel: Record<AnimalTimelineEvent['kind'], string> = {
  assignment: 'Assignment',
  weight: 'Weight',
  treatment: 'Treatment',
  sample: 'Sample',
  analysis: 'Analysis',
  behavior: 'Behavior',
  alert: 'Alert',
}

const avatarStyles: Record<string, string> = {
  emerald: 'bg-emerald-100 text-emerald-700',
  sky: 'bg-sky-100 text-sky-700',
  amber: 'bg-amber-100 text-amber-700',
  rose: 'bg-rose-100 text-rose-700',
  violet: 'bg-violet-100 text-violet-700',
  slate: 'bg-slate-100 text-slate-600',
}

const toDayKey = (date: Date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function AnimalTimeline({ events }: { events: AnimalTimelineEvent[] }) {
  const grouped = useMemo(() => {
    const groups = new Map<string, AnimalTimelineEvent[]>()
    events.forEach((event) => {
      const key = toDayKey(new Date(event.at))
      const list = groups.get(key) ?? []
      list.push(event)
      groups.set(key, list)
    })
    return Array.from(groups.entries()).sort(([a], [b]) => b.localeCompare(a))
  }, [events])

  if (!events.length) {
    return (
      <div className="rounded-2xl border border-line bg-surface p-6 text-sm text-slate-500">
        No timeline events recorded yet.
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {grouped.map(([day, dayEvents]) => (
        <div key={day} className="rounded-2xl border border-line bg-surface p-5">
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-slate-400">
            <ClipboardCheck className="h-4 w-4" />
            {day}
          </div>
          <div className="mt-4 space-y-4">
            {dayEvents.map((event) => {
              const Icon = iconMap[event.kind]
              return (
                <div key={event.id} className="flex gap-3">
                  <div className="mt-1 flex h-8 w-8 items-center justify-center rounded-full border border-line bg-surface-2 text-slate-600">
                    <Icon className="h-4 w-4" />
                  </div>
                  <div className="flex-1">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <div>
                        <p className="text-sm font-semibold text-slate-800">{event.title}</p>
                        <p className="text-xs text-slate-500">
                          {kindLabel[event.kind]} • {formatDateTime(event.at)}
                          {event.actor ? ` • ${event.actor}` : ''}
                        </p>
                      </div>
                      <div className="flex items-center gap-2">
                        {event.actorAvatar ? (
                          <span
                            className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold ${avatarStyles[event.actorAvatar.color] ?? avatarStyles.slate}`}
                          >
                            {event.actorAvatar.initials}
                          </span>
                        ) : null}
                        {event.value ? (
                          <span className="rounded-full border border-line px-2 py-1 text-xs text-slate-600">
                            {event.value}
                          </span>
                        ) : null}
                      </div>
                    </div>
                    {event.detail ? <p className="mt-2 text-sm text-slate-600">{event.detail}</p> : null}
                  </div>
                </div>
              )
            })}
          </div>
        </div>
      ))}
    </div>
  )
}
