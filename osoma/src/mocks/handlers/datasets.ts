import { http, HttpResponse } from 'msw'
import { datasets } from '@/mocks/data/datasets.ts'
import type { Dataset } from '@/domain/resources.ts'
import { matches, paginate, withLatency } from './utils.ts'

export const datasetsHandlers = [
  http.get('/api/datasets', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const pageSize = Number(url.searchParams.get('pageSize') ?? 7)
    const search = url.searchParams.get('search')
    const status = url.searchParams.get('status')
    const format = url.searchParams.get('format')
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Dataset registry is degraded.' }, { status: 503 })
    }

    let filtered = [...datasets]

    if (search) {
      filtered = filtered.filter(
        (dataset) => matches(dataset.title, search) || matches(dataset.investigationName, search) || matches(dataset.id, search)
      )
    }

    if (status) {
      filtered = filtered.filter((dataset) => dataset.status === status)
    }

    if (format) {
      filtered = filtered.filter((dataset) => dataset.format === format)
    }

    const { data, total } = paginate(filtered, page, pageSize)

    return HttpResponse.json({
      'hydra:member': data,
      'hydra:totalItems': total,
      'hydra:view': {
        '@id': `/datasets?page=${page}&pageSize=${pageSize}`,
        'hydra:first': `/datasets?page=1&pageSize=${pageSize}`,
        'hydra:last': `/datasets?page=${Math.ceil(total / pageSize)}&pageSize=${pageSize}`,
        'hydra:next': page < Math.ceil(total / pageSize) ? `/datasets?page=${page + 1}&pageSize=${pageSize}` : undefined,
      },
    })
  }),

  http.get('/api/datasets/:datasetId', async ({ params, request }) => {
    await withLatency()
    const url = new URL(request.url)
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Dataset record unavailable.' }, { status: 500 })
    }

    const dataset = datasets.find((record) => record.id === params.datasetId)
    if (!dataset) {
      return HttpResponse.json({ message: 'Dataset not found.' }, { status: 404 })
    }

    return HttpResponse.json(dataset)
  }),

  http.patch('/api/datasets/:datasetId', async ({ request, params }) => {
    await withLatency()
    const payload = (await request.json()) as Partial<Dataset>
    const index = datasets.findIndex((record) => record.id === params.datasetId)

    if (index === -1) {
      return HttpResponse.json({ message: 'Dataset not found.' }, { status: 404 })
    }

    datasets[index] = {
      ...datasets[index],
      ...payload,
      updatedAt: new Date().toISOString(),
    }

    return HttpResponse.json(datasets[index])
  }),
]
