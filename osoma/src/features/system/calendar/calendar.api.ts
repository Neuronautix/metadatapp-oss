import { apiFetch } from '@/lib/api.ts'
import type { CalendarEventsResponse } from './calendar.types.ts'

export type CalendarRange = {
  start: string
  end: string
}

export function getCalendarEvents(range: CalendarRange) {
  const params = new URLSearchParams()
  params.set('start', range.start)
  params.set('end', range.end)

  return apiFetch<CalendarEventsResponse>(`/calendar/events?${params.toString()}`)
}
