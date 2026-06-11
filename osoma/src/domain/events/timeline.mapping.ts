import type { AuditLogEntry } from '@/domain/audit.ts'
import type { CalendarEvent } from '@/features/system/calendar/calendar.types.ts'
import type { TimelineEvent } from './event.types.ts'

const actionMap: Record<AuditLogEntry['action'], TimelineEvent['kind']> = {
  created: 'created',
  updated: 'updated',
  linked: 'linked',
  unlinked: 'unlinked',
  'status.changed': 'status',
  exported: 'exported',
  scheduled: 'scheduled',
  'access.changed': 'updated',
}

export const mapAuditToTimeline = (entries: AuditLogEntry[]) =>
  entries.map<TimelineEvent>((entry) => ({
    id: entry.id,
    at: entry.at,
    title: entry.summary,
    detail: entry.action.replace('.', ' '),
    actor: entry.actor,
    kind: actionMap[entry.action],
    diff: entry.diff,
  }))

const calendarEventMatchesResource = (event: CalendarEvent, resourceId: string) =>
  event.resource?.id === resourceId ||
  event.subject?.id === resourceId ||
  event.subjects?.some((subject) => subject.id === resourceId)

export const mapCalendarToTimeline = (events: CalendarEvent[], resourceId: string) =>
  events
    .filter((event) => calendarEventMatchesResource(event, resourceId))
    .map<TimelineEvent>((event) => ({
      id: event.id,
      at: event.start,
      title: `${event.title} scheduled`,
      detail: event.location ?? event.resource?.label ?? event.subject?.label,
      kind: 'scheduled',
    }))

export const mergeTimelineEvents = (...groups: TimelineEvent[][]) =>
  groups
    .flat()
    .sort((a, b) => new Date(b.at).getTime() - new Date(a.at).getTime())
