import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AuditResourceType } from '@/domain/audit.ts'
import { addDays, startOfDay } from '@/features/system/calendar/calendar.utils.ts'
import { getCalendarEvents } from '@/features/system/calendar/calendar.api.ts'
import { mapAuditToTimeline, mapCalendarToTimeline, mergeTimelineEvents } from '@/domain/events/timeline.mapping.ts'
import { getAuditEntries } from './audit.api.ts'

export function useTimelineEvents(resourceType: AuditResourceType, resourceId: string) {
  const auditQuery = useQuery({
    queryKey: ['audit', resourceType, resourceId, 'timeline'],
    queryFn: () => getAuditEntries({ resourceType, resourceId, page: 1, pageSize: 50 }),
    enabled: Boolean(resourceId),
  })

  const rangeStart = startOfDay(addDays(new Date(), -30)).toISOString()
  const rangeEnd = startOfDay(addDays(new Date(), 60)).toISOString()

  const calendarQuery = useQuery({
    queryKey: ['calendar-events', resourceId, rangeStart, rangeEnd],
    queryFn: () => getCalendarEvents({ start: rangeStart, end: rangeEnd }),
    enabled: Boolean(resourceId),
  })

  const events = useMemo(() => {
    const auditEvents = auditQuery.data?.data ? mapAuditToTimeline(auditQuery.data.data) : []
    const calendarEvents = calendarQuery.data?.data
      ? mapCalendarToTimeline(calendarQuery.data.data, resourceId)
      : []
    return mergeTimelineEvents(auditEvents, calendarEvents)
  }, [auditQuery.data?.data, calendarQuery.data?.data, resourceId])

  return {
    events,
    isLoading: auditQuery.isLoading || calendarQuery.isLoading,
    isError: auditQuery.isError || calendarQuery.isError,
    error: (auditQuery.error ?? calendarQuery.error) as Error | null,
    refetch: () => {
      auditQuery.refetch()
      calendarQuery.refetch()
    },
  }
}
