import { http, HttpResponse } from 'msw'
import { samples } from '@/mocks/data/samples.ts'
import type { Sample } from '@/domain/resources.ts'
import { matches, paginate, withLatency } from './utils.ts'

const toWeightMeasurement = (sample: Sample) => ({
  id: sample.id,
  '@id': `/weight_measurements/${sample.id}`,
  externalId: sample.label,
  subject: {
    id: sample.subjectId,
    '@id': `/subjects/${sample.subjectId}`,
    name: sample.subjectLabel,
  },
  measuredAt: sample.collectedAt,
  weight: Number(sample.identifiers.find((identifier) => identifier.type === 'weight')?.value ?? 25),
  createdAt: sample.createdAt,
  updatedAt: sample.updatedAt,
})

export const samplesHandlers = [
  http.get('/api/weight_measurements', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const pageSize = Number(url.searchParams.get('pageSize') ?? 8)
    const search = url.searchParams.get('search')
    const status = url.searchParams.get('status')
    const type = url.searchParams.get('type')
    const storage = url.searchParams.get('storage')
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Sample registry is degraded.' }, { status: 503 })
    }

    let filtered = [...samples]
    if (search) {
      filtered = filtered.filter(
        (sample) =>
          matches(sample.label, search) ||
          matches(sample.subjectLabel, search) ||
          matches(sample.investigationName, search) ||
          matches(sample.id, search)
      )
    }
    if (status) {
      filtered = filtered.filter((sample) => sample.status === status)
    }
    if (type) {
      filtered = filtered.filter((sample) => sample.type === type)
    }
    if (storage) {
      filtered = filtered.filter((sample) => sample.storageLocation === storage)
    }

    const { data, total } = paginate(filtered, page, pageSize)

    return HttpResponse.json({
      'hydra:member': data.map(toWeightMeasurement),
      'hydra:totalItems': total,
      'hydra:view': {
        '@id': `/weight_measurements?page=${page}&pageSize=${pageSize}`,
      },
    })
  }),

  http.get('/api/weight_measurements/:sampleId', async ({ params }) => {
    await withLatency()
    const sample = samples.find((record) => record.id === params.sampleId)
    if (!sample) {
      return HttpResponse.json({ message: 'Sample not found.' }, { status: 404 })
    }

    return HttpResponse.json(toWeightMeasurement(sample))
  }),

  http.get('/api/samples', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const pageSize = Number(url.searchParams.get('pageSize') ?? 8)
    const search = url.searchParams.get('search')
    const status = url.searchParams.get('status')
    const type = url.searchParams.get('type')
    const storage = url.searchParams.get('storage')
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Sample registry is degraded.' }, { status: 503 })
    }

    let filtered = [...samples]

    if (search) {
      filtered = filtered.filter(
        (sample) =>
          matches(sample.label, search) ||
          matches(sample.subjectLabel, search) ||
          matches(sample.investigationName, search) ||
          matches(sample.id, search)
      )
    }

    if (status) {
      filtered = filtered.filter((sample) => sample.status === status)
    }

    if (type) {
      filtered = filtered.filter((sample) => sample.type === type)
    }

    if (storage) {
      filtered = filtered.filter((sample) => sample.storageLocation === storage)
    }

    const { data, total } = paginate(filtered, page, pageSize)

    return HttpResponse.json({ data, page, pageSize, total })
  }),

  http.get('/api/samples/:sampleId', async ({ params, request }) => {
    await withLatency()
    const url = new URL(request.url)
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Sample record unavailable.' }, { status: 500 })
    }

    const sample = samples.find((record) => record.id === params.sampleId)
    if (!sample) {
      return HttpResponse.json({ message: 'Sample not found.' }, { status: 404 })
    }

    return HttpResponse.json(sample)
  }),

  http.patch('/api/samples/:sampleId', async ({ request, params }) => {
    await withLatency()
    const payload = (await request.json()) as Partial<Sample>
    const index = samples.findIndex((record) => record.id === params.sampleId)

    if (index === -1) {
      return HttpResponse.json({ message: 'Sample not found.' }, { status: 404 })
    }

    samples[index] = {
      ...samples[index],
      ...payload,
      updatedAt: new Date().toISOString(),
    }

    return HttpResponse.json(samples[index])
  }),
]
