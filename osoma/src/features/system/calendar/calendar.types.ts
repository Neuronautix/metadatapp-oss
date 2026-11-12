export type CalendarView = 'month' | 'week' | 'agenda'

export type CalendarEventStatus = 'scheduled' | 'in-progress' | 'completed' | 'blocked'

export type CalendarEventKind = 'study' | 'protocol' | 'sample' | 'appointment'

export type CalendarEventResource = {
  type: 'study' | 'protocol' | 'sample' | 'investigation'
  id: string
  label: string
}

export type CalendarEventSubject = {
  id: string
  label: string
}

export type CalendarEvent = {
  id: string
  title: string
  start: string
  end: string
  status: CalendarEventStatus
  kind: CalendarEventKind
  resource?: CalendarEventResource
  subject?: CalendarEventSubject
  subjects?: CalendarEventSubject[]
  location?: string
}

export type CalendarEventsResponse = {
  data: CalendarEvent[]
}
