import { http, HttpResponse } from 'msw'
import { calendarEvents } from '@/mocks/data/calendar.ts'
import { withLatency } from './utils.ts'

export const calendarHandlers = [
  http.get('/api/calendar/events', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const start = url.searchParams.get('start')
    const end = url.searchParams.get('end')
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Calendar service is unavailable.' }, { status: 503 })
    }

    if (!start || !end) {
      return HttpResponse.json({ data: calendarEvents })
    }

    const startDate = new Date(start)
    const endDate = new Date(end)

    const data = calendarEvents.filter((event) => {
      const eventStart = new Date(event.start)
      const eventEnd = new Date(event.end)
      return eventEnd >= startDate && eventStart <= endDate
    })

    return HttpResponse.json({ data })
  }),
]
