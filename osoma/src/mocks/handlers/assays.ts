import { http, HttpResponse } from 'msw'
import { assays } from '@/mocks/data/assays.ts'
import type { Assay } from '@/domain/resources.ts'
import { matches, paginate, withLatency } from './utils.ts'

export const assaysHandlers = [
  http.get('/api/procedures', async ({ request }) => {
    await withLatency(40, 120)
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const pageSize = Number(url.searchParams.get('itemsPerPage') ?? url.searchParams.get('pageSize') ?? 7)
    const search = url.searchParams.get('name') ?? url.searchParams.get('search')
    const reviewStatus = url.searchParams.get('reviewStatus')
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Assay registry is degraded.' }, { status: 503 })
    }

    let filtered = [...assays]

    if (search) {
      filtered = filtered.filter(
        (assay) =>
          matches(assay.name, search) || matches(assay.method, search) || matches(assay.id, search)
      )
    }

    if (reviewStatus) {
      filtered = filtered.filter((assay) => assay.reviewStatus === reviewStatus)
    }

    const { data, total } = paginate(filtered, page, pageSize)

    return HttpResponse.json({
      'hydra:member': data,
      'hydra:totalItems': total,
      'hydra:view': {
        '@id': `/procedures?page=${page}&pageSize=${pageSize}`,
        'hydra:first': `/procedures?page=1&pageSize=${pageSize}`,
        'hydra:last': `/procedures?page=${Math.ceil(total / pageSize)}&pageSize=${pageSize}`,
        'hydra:next': page < Math.ceil(total / pageSize) ? `/procedures?page=${page + 1}&pageSize=${pageSize}` : undefined,
      },
    })
  }),

  http.get('/api/procedures/:assayId', async ({ params, request }) => {
    await withLatency(40, 120)
    const url = new URL(request.url)
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Assay record unavailable.' }, { status: 500 })
    }

    const assay = assays.find((record) => record.id === params.assayId)
    if (!assay) {
      return HttpResponse.json({ message: 'Assay not found.' }, { status: 404 })
    }

    return HttpResponse.json(assay)
  }),

  http.patch('/api/procedures/:assayId', async ({ request, params }) => {
    await withLatency(40, 120)
    const payload = (await request.json()) as Partial<Assay>
    const index = assays.findIndex((record) => record.id === params.assayId)

    if (index === -1) {
      return HttpResponse.json({ message: 'Assay not found.' }, { status: 404 })
    }

    assays[index] = {
      ...assays[index],
      ...payload,
      updatedAt: new Date().toISOString(),
    }

    return HttpResponse.json(assays[index])
  }),
]
