import { http, HttpResponse } from 'msw'
import { auditEntries } from '@/mocks/data/audit.ts'
import { matches, paginate, withLatency } from './utils.ts'

export const auditHandlers = [
  http.get('/api/audit', async ({ request }) => {
    await withLatency(260, 880)
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const pageSize = Number(url.searchParams.get('pageSize') ?? 10)
    const actor = url.searchParams.get('actor')
    const action = url.searchParams.get('action')
    const resourceType = url.searchParams.get('resourceType')
    const resourceId = url.searchParams.get('resourceId')
    const startDate = url.searchParams.get('startDate')
    const endDate = url.searchParams.get('endDate')
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Audit stream unavailable.' }, { status: 503 })
    }

    let filtered = [...auditEntries]

    if (actor) {
      filtered = filtered.filter((entry) => matches(entry.actor, actor))
    }

    if (action) {
      filtered = filtered.filter((entry) => entry.action === action)
    }

    if (resourceType) {
      filtered = filtered.filter((entry) => entry.resourceType === resourceType)
    }

    if (resourceId) {
      filtered = filtered.filter((entry) => entry.resourceId === resourceId)
    }

    if (startDate) {
      const start = new Date(startDate)
      filtered = filtered.filter((entry) => new Date(entry.at) >= start)
    }

    if (endDate) {
      const end = new Date(endDate)
      end.setHours(23, 59, 59, 999)
      filtered = filtered.filter((entry) => new Date(entry.at) <= end)
    }

    filtered.sort((a, b) => new Date(b.at).getTime() - new Date(a.at).getTime())

    const { data, total } = paginate(filtered, page, pageSize)

    return HttpResponse.json({ data, page, pageSize, total })
  }),
]
