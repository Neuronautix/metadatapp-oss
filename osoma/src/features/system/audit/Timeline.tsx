import { useMemo, useState } from 'react'
import {
  Activity,
  CalendarCheck,
  ClipboardCheck,
  FileDown,
  Link2,
  Link2Off,
  Pencil,
  Plus,
} from 'lucide-react'
import type { TimelineEvent } from '@/domain/events/event.types.ts'
import { formatDateTime } from '@/lib/format.ts'

const iconMap: Record<TimelineEvent['kind'], React.ElementType> = {
  created: Plus,
  updated: Pencil,
  linked: Link2,
  unlinked: Link2Off,
  status: Activity,
  scheduled: CalendarCheck,
  exported: FileDown,
}

const kindLabel: Record<TimelineEvent['kind'], string> = {
  created: 'Created',
  updated: 'Updated',
  linked: 'Linked',
  unlinked: 'Unlinked',
  status: 'Status change',
  scheduled: 'Scheduled',
  exported: 'Exported',
}

const toDayKey = (date: Date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function Timeline({ events }: { events: TimelineEvent[] }) {
  const [expanded, setExpanded] = useState<string[]>([])

  const grouped = useMemo(() => {
    const groups = new Map<string, TimelineEvent[]>()
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
              const isOpen = expanded.includes(event.id)
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
                      {event.diff?.length ? (
                        <button
                          className="text-xs text-slate-500 underline"
                          onClick={() =>
                            setExpanded((prev) =>
                              prev.includes(event.id)
                                ? prev.filter((value) => value !== event.id)
                                : [...prev, event.id]
                            )
                          }
                        >
                          {isOpen ? 'Hide diff' : 'View diff'}
                        </button>
                      ) : null}
                    </div>
                    {event.detail ? <p className="mt-2 text-sm text-slate-600">{event.detail}</p> : null}
                    {event.diff?.length && isOpen ? (
                      <div className="mt-2 space-y-2 rounded-xl border border-line bg-white p-3 text-xs text-slate-600">
                        {event.diff.map((diff) => (
                          <div key={diff.field} className="flex items-center justify-between gap-2">
                            <span className="uppercase tracking-[0.2em] text-slate-400">{diff.field}</span>
                            <span className="text-slate-500">{String(diff.before ?? '—')}</span>
                            <span className="text-slate-400">→</span>
                            <span className="font-semibold text-slate-700">{String(diff.after ?? '—')}</span>
                          </div>
                        ))}
                      </div>
                    ) : null}
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
