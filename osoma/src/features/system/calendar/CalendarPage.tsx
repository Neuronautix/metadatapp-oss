import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronLeft, ChevronRight, CalendarClock, X } from 'lucide-react'
import { getCalendarEvents } from './calendar.api.ts'
import type { CalendarEvent, CalendarView } from './calendar.types.ts'
import {
  addDays,
  addMonths,
  endOfMonth,
  endOfWeek,
  getMonthGrid,
  isSameDay,
  isSameMonth,
  startOfDay,
  startOfMonth,
  startOfWeek,
} from './calendar.utils.ts'
import { Button } from '@/components/ui/button.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'

const weekdayFormatter = new Intl.DateTimeFormat('en-US', { weekday: 'short' })
const monthFormatter = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' })
const dayFormatter = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' })
const timeFormatter = new Intl.DateTimeFormat('en-US', { hour: '2-digit', minute: '2-digit' })

const viewOptions: CalendarView[] = ['month', 'week', 'agenda']

const statusTone: Record<CalendarEvent['status'], string> = {
  scheduled: 'border-line bg-surface-2 text-ink',
  'in-progress': 'border-emerald-200 bg-emerald-100 text-emerald-700',
  completed: 'border-secondary bg-secondary text-on-secondary',
  blocked: 'border-rose-200 bg-rose-100 text-rose-700',
}

const kindLabel: Record<CalendarEvent['kind'], string> = {
  study: 'Study',
  protocol: 'Protocol',
  sample: 'Sample',
  appointment: 'Appointment',
}

const statusOptions: CalendarEvent['status'][] = ['scheduled', 'in-progress', 'completed', 'blocked']
const kindOptions: CalendarEvent['kind'][] = ['study', 'protocol', 'sample', 'appointment']

const resourcePath = (event: CalendarEvent) => {
  if (!event.resource) return null
  if (event.resource.type === 'study') return `/studies/${event.resource.id}`
  if (event.resource.type === 'protocol') return `/protocols/${event.resource.id}`
  if (event.resource.type === 'sample') return `/samples/${event.resource.id}`
  if (event.resource.type === 'investigation') return `/investigations/${event.resource.id}`
  return null
}

const toDayKey = (date: Date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const CalendarEventPill = ({ event }: { event: CalendarEvent }) => {
  const path = resourcePath(event)
  const timeLabel = `${timeFormatter.format(new Date(event.start))} - ${timeFormatter.format(new Date(event.end))}`
  const content = (
    <div className={`rounded-lg border px-2 py-1 text-xs ${statusTone[event.status]}`}>
      <div className="flex items-center justify-between gap-2">
        <span className="font-semibold">{event.title}</span>
        <span className="text-[10px] uppercase tracking-[0.2em] opacity-70">{kindLabel[event.kind]}</span>
      </div>
      <div className="mt-1 flex items-center justify-between text-[11px] opacity-80">
        <span>{timeLabel}</span>
        {event.resource ? <span>{event.resource.label}</span> : null}
      </div>
    </div>
  )

  if (!path) return content

  return (
    <Link to={path} className="block transition hover:opacity-90" onClick={(event) => event.stopPropagation()}>
      {content}
    </Link>
  )
}

export function CalendarPage() {
  const [view, setView] = useState<CalendarView>('month')
  const [cursorDate, setCursorDate] = useState(() => startOfDay(new Date()))
  const [selectedDay, setSelectedDay] = useState<Date | null>(null)
  const [statusFilters, setStatusFilters] = useState<CalendarEvent['status'][]>(statusOptions)
  const [kindFilters, setKindFilters] = useState<CalendarEvent['kind'][]>(kindOptions)
  const queryClient = useQueryClient()

  const range = useMemo(() => {
    if (view === 'month') {
      const start = startOfWeek(startOfMonth(cursorDate))
      const end = endOfWeek(endOfMonth(cursorDate))
      return { start, end }
    }
    if (view === 'week') {
      const start = startOfWeek(cursorDate)
      const end = endOfWeek(cursorDate)
      return { start, end }
    }
    const start = startOfDay(cursorDate)
    const end = addDays(start, 30)
    return { start, end }
  }, [cursorDate, view])

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['calendar-events', view, range.start.toISOString(), range.end.toISOString()],
    queryFn: () =>
      getCalendarEvents({
        start: range.start.toISOString(),
        end: range.end.toISOString(),
      }),
  })

  const filteredEvents = useMemo(() => {
    if (!data?.data) return []
    return data.data.filter((event) => statusFilters.includes(event.status) && kindFilters.includes(event.kind))
  }, [kindFilters, statusFilters, data?.data])

  const eventsByDay = useMemo(() => {
    const map = new Map<string, CalendarEvent[]>()
    if (!filteredEvents.length) return map

    filteredEvents
      .slice()
      .sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime())
      .forEach((event) => {
        const key = toDayKey(new Date(event.start))
        const list = map.get(key) ?? []
        list.push(event)
        map.set(key, list)
      })

    return map
  }, [filteredEvents])

  const selectedDayKey = selectedDay ? toDayKey(selectedDay) : null
  const selectedEvents = selectedDayKey ? eventsByDay.get(selectedDayKey) ?? [] : []

  const handleMove = (direction: 'prev' | 'next') => {
    setCursorDate((prev) => {
      if (view === 'month') return addMonths(prev, direction === 'prev' ? -1 : 1)
      if (view === 'week') return addDays(prev, direction === 'prev' ? -7 : 7)
      return addDays(prev, direction === 'prev' ? -14 : 14)
    })
  }

  const label = useMemo(() => {
    if (view === 'month') {
      return monthFormatter.format(cursorDate)
    }
    if (view === 'week') {
      const start = startOfWeek(cursorDate)
      const end = addDays(start, 6)
      return `${dayFormatter.format(start)} - ${dayFormatter.format(end)}`
    }
    return `Next 30 days`
  }, [cursorDate, view])

  const monthGrid = useMemo(() => getMonthGrid(cursorDate), [cursorDate])
  const weekDays = useMemo(() => {
    const start = startOfWeek(cursorDate)
    return Array.from({ length: 7 }, (_, index) => addDays(start, index))
  }, [cursorDate])

  const toggleStatus = (status: CalendarEvent['status']) => {
    setStatusFilters((prev) =>
      prev.includes(status) ? prev.filter((value) => value !== status) : [...prev, status]
    )
  }

  const toggleKind = (kind: CalendarEvent['kind']) => {
    setKindFilters((prev) => (prev.includes(kind) ? prev.filter((value) => value !== kind) : [...prev, kind]))
  }

  const resetFilters = () => {
    setStatusFilters(statusOptions)
    setKindFilters(kindOptions)
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Premium Scheduler</p>
          <h2 className="font-display text-2xl">Calendar</h2>
          <p className="mt-2 text-sm text-slate-500">
            Domain-aware schedules for studies, protocols, and specimen lifecycles.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <div className="flex items-center gap-2 rounded-full border border-line bg-white px-2 py-1">
            {viewOptions.map((option) => (
              <Button
                key={option}
                size="sm"
                variant={view === option ? 'default' : 'ghost'}
                onClick={() => setView(option)}
              >
                {option}
              </Button>
            ))}
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={() => handleMove('prev')}>
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Button variant="outline" size="sm" onClick={() => setCursorDate(startOfDay(new Date()))}>
              Today
            </Button>
            <Button variant="outline" size="sm" onClick={() => handleMove('next')}>
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      <Card className="p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2 text-sm text-slate-500">
            <CalendarClock className="h-4 w-4" />
            <span>{label}</span>
          </div>
          <Button variant="ghost" size="sm" onClick={resetFilters}>
            Reset filters
          </Button>
        </div>

        <div className="mt-4 flex flex-wrap items-center gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Status</span>
            {statusOptions.map((status) => (
              <Button
                key={status}
                size="sm"
                variant={statusFilters.includes(status) ? 'secondary' : 'outline'}
                onClick={() => toggleStatus(status)}
              >
                {status.replace('-', ' ')}
              </Button>
            ))}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Type</span>
            {kindOptions.map((kind) => (
              <Button
                key={kind}
                size="sm"
                variant={kindFilters.includes(kind) ? 'secondary' : 'outline'}
                onClick={() => toggleKind(kind)}
              >
                {kind}
              </Button>
            ))}
          </div>
        </div>

        {isLoading ? (
          <div className="mt-6 space-y-3">
            {Array.from({ length: 6 }).map((_, index) => (
              <Skeleton key={index} className="h-10 w-full" />
            ))}
          </div>
        ) : isError ? (
          <div className="mt-6">
            <EmptyState
              title="Calendar unavailable"
              description={(error as Error).message}
              actionLabel="Retry"
              onAction={() => queryClient.invalidateQueries({ queryKey: ['calendar-events'] })}
            />
          </div>
        ) : view === 'month' ? (
          <div className="mt-6 space-y-2">
            <div className="grid grid-cols-7 gap-2 text-xs text-slate-400">
              {Array.from({ length: 7 }, (_, index) => (
                <div key={index} className="px-2">
                  {weekdayFormatter.format(addDays(startOfWeek(cursorDate), index))}
                </div>
              ))}
            </div>
            <div className="grid grid-cols-7 gap-2">
              {monthGrid.flat().map((day) => {
                const key = toDayKey(day)
                const dayEvents = eventsByDay.get(key) ?? []
                const isToday = isSameDay(day, new Date())
                const isSelected = selectedDayKey === key
                return (
                  <div
                    key={key}
                    role="button"
                    tabIndex={0}
                    onClick={() => setSelectedDay(day)}
                    onKeyDown={(event) => {
                      if (event.key === 'Enter') setSelectedDay(day)
                    }}
                    className={`min-h-[120px] cursor-pointer rounded-2xl border border-line bg-surface p-2 text-xs text-slate-600 shadow-subtle transition ${
                      isSameMonth(day, cursorDate) ? '' : 'opacity-50'
                    } ${isSelected ? 'ring-2 ring-focus/20' : 'hover:border-line'}`}
                  >
                    <div className="flex items-center justify-between">
                      <span className={isToday ? 'font-semibold text-slate-900' : ''}>{day.getDate()}</span>
                    </div>
                    <div className="mt-2 space-y-2">
                      {dayEvents.slice(0, 2).map((event) => (
                        <CalendarEventPill key={event.id} event={event} />
                      ))}
                      {dayEvents.length > 2 ? (
                        <span className="text-[11px] text-slate-400">+{dayEvents.length - 2} more</span>
                      ) : null}
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        ) : view === 'week' ? (
          <div className="mt-6 grid gap-3 lg:grid-cols-7">
            {weekDays.map((day) => {
              const key = toDayKey(day)
              const dayEvents = eventsByDay.get(key) ?? []
              const isSelected = selectedDayKey === key
              return (
                <div
                  key={key}
                  role="button"
                  tabIndex={0}
                  onClick={() => setSelectedDay(day)}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter') setSelectedDay(day)
                  }}
                  className={`space-y-3 rounded-2xl border border-line bg-surface p-3 transition ${
                    isSelected ? 'ring-2 ring-focus/20' : 'hover:border-line'
                  }`}
                >
                  <div className="text-xs text-slate-500">
                    <p className="font-semibold text-slate-700">{weekdayFormatter.format(day)}</p>
                    <p>{dayFormatter.format(day)}</p>
                  </div>
                  {dayEvents.length ? (
                    <div className="space-y-2">
                      {dayEvents.map((event) => (
                        <CalendarEventPill key={event.id} event={event} />
                      ))}
                    </div>
                  ) : (
                    <p className="text-xs text-slate-400">No scheduled items</p>
                  )}
                </div>
              )
            })}
          </div>
        ) : (
          <div className="mt-6 space-y-4">
            {Array.from(eventsByDay.entries()).length === 0 ? (
              <p className="text-sm text-slate-500">No scheduled events in this window.</p>
            ) : (
              Array.from(eventsByDay.entries())
                .sort(([a], [b]) => a.localeCompare(b))
                .map(([key, dayEvents]) => (
                  <div key={key} className="rounded-2xl border border-line bg-surface p-4">
                    <div className="flex items-center justify-between">
                      <p className="text-xs uppercase tracking-[0.2em] text-slate-400">
                        {dayFormatter.format(new Date(`${key}T00:00:00`))}
                      </p>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setSelectedDay(new Date(`${key}T00:00:00`))}
                      >
                        Details
                      </Button>
                    </div>
                    <div className="mt-3 space-y-2">
                      {dayEvents.map((event) => (
                        <CalendarEventPill key={event.id} event={event} />
                      ))}
                    </div>
                  </div>
                ))
            )}
          </div>
        )}
      </Card>

      {selectedDay ? (
        <div className="fixed inset-0 z-40">
          <div className="absolute inset-0 bg-slate-900/20" onClick={() => setSelectedDay(null)} />
          <aside className="absolute right-0 top-0 flex h-full w-full max-w-md flex-col border-l border-line bg-surface p-6 shadow-panel">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Day details</p>
                <h3 className="font-display text-xl">{dayFormatter.format(selectedDay)}</h3>
                <p className="text-sm text-slate-500">{selectedEvents.length} scheduled items</p>
              </div>
              <Button variant="ghost" size="icon" onClick={() => setSelectedDay(null)}>
                <X className="h-4 w-4" />
              </Button>
            </div>
            <div className="mt-6 space-y-3 overflow-auto">
              {selectedEvents.length ? (
                selectedEvents.map((event) => (
                  <div key={event.id} className="rounded-2xl border border-line bg-surface-2 p-3 text-sm">
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-slate-800">{event.title}</span>
                      <span
                        className={`rounded-full border px-2 py-0.5 text-[11px] uppercase tracking-[0.2em] ${statusTone[event.status]}`}
                      >
                        {event.status.replace('-', ' ')}
                      </span>
                    </div>
                    <div className="mt-2 flex items-center justify-between text-xs text-slate-500">
                      <span>
                        {timeFormatter.format(new Date(event.start))} - {timeFormatter.format(new Date(event.end))}
                      </span>
                      <span>{kindLabel[event.kind]}</span>
                    </div>
                    <div className="mt-2 text-xs text-slate-500">
                      {event.resource ? `Resource: ${event.resource.label}` : event.location ?? 'No location set'}
                    </div>
                    {resourcePath(event) ? (
                      <Button variant="outline" size="sm" className="mt-3 w-full" asChild>
                        <Link to={resourcePath(event) ?? ''}>Open resource</Link>
                      </Button>
                    ) : null}
                  </div>
                ))
              ) : (
                <p className="text-sm text-slate-500">No events scheduled for this day.</p>
              )}
            </div>
          </aside>
        </div>
      ) : null}
    </div>
  )
}
