import { http, HttpResponse } from 'msw'
import { organizations } from '@/mocks/data/organizations.ts'
import type { Organization } from '@/domain/resources.ts'
import { matches, paginate, withLatency } from './utils.ts'

export const organizationsHandlers = [
  http.get('/api/organizations', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const pageSize = Number(url.searchParams.get('pageSize') ?? 7)
    const search = url.searchParams.get('search')
    const type = url.searchParams.get('type')
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Organization registry is degraded.' }, { status: 503 })
    }

    let filtered = [...organizations]

    if (search) {
      filtered = filtered.filter(
        (org) => matches(org.name, search) || matches(org.location, search) || matches(org.id, search)
      )
    }

    if (type) {
      filtered = filtered.filter((org) => org.type === type)
    }

    const { data, total } = paginate(filtered, page, pageSize)

    return HttpResponse.json({ data, page, pageSize, total })
  }),

  http.get('/api/organizations/:organizationId', async ({ params, request }) => {
    await withLatency()
    const url = new URL(request.url)
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Organization record unavailable.' }, { status: 500 })
    }

    const organization = organizations.find((record) => record.id === params.organizationId)
    if (!organization) {
      return HttpResponse.json({ message: 'Organization not found.' }, { status: 404 })
    }

    return HttpResponse.json(organization)
  }),

  http.patch('/api/organizations/:organizationId', async ({ request, params }) => {
    await withLatency()
    const payload = (await request.json()) as Partial<Organization>
    const index = organizations.findIndex((record) => record.id === params.organizationId)

    if (index === -1) {
      return HttpResponse.json({ message: 'Organization not found.' }, { status: 404 })
    }

    organizations[index] = {
      ...organizations[index],
      ...payload,
      updatedAt: new Date().toISOString(),
    }

    return HttpResponse.json(organizations[index])
  }),
]
