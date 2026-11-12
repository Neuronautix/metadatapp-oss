import { http, HttpResponse } from 'msw'
import { subjects } from '@/mocks/data/subjects.ts'
import type { Subject } from '@/domain/resources.ts'
import { matches, paginate, withLatency } from './utils.ts'

export const subjectsHandlers = [
  http.get('/api/subjects', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const pageSize = Number(url.searchParams.get('itemsPerPage') ?? url.searchParams.get('pageSize') ?? 7)
    const search = url.searchParams.get('name') ?? url.searchParams.get('search')
    const species = url.searchParams.get('species')
    const consent = url.searchParams.get('consent')
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Subject registry is degraded.' }, { status: 503 })
    }

    let filtered = [...subjects]

    if (search) {
      filtered = filtered.filter(
        (subject) => matches(subject.label, search) || matches(subject.cohort, search) || matches(subject.id, search)
      )
    }

    if (species) {
      filtered = filtered.filter((subject) => subject.species === species)
    }

    if (consent) {
      filtered = filtered.filter((subject) => subject.consent === consent)
    }

    const { data, total } = paginate(filtered, page, pageSize)

    return HttpResponse.json({
      'hydra:member': data,
      'hydra:totalItems': total,
      'hydra:view': {
        '@id': `/subjects?page=${page}&pageSize=${pageSize}`,
        'hydra:first': `/subjects?page=1&pageSize=${pageSize}`,
        'hydra:last': `/subjects?page=${Math.ceil(total / pageSize)}&pageSize=${pageSize}`,
        'hydra:next': page < Math.ceil(total / pageSize) ? `/subjects?page=${page + 1}&pageSize=${pageSize}` : undefined,
      },
    })
  }),

  http.get('/api/subjects/:subjectId', async ({ params, request }) => {
    await withLatency()
    const url = new URL(request.url)
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Subject record unavailable.' }, { status: 500 })
    }

    const subject = subjects.find((record) => record.id === params.subjectId)
    if (!subject) {
      return HttpResponse.json({ message: 'Subject not found.' }, { status: 404 })
    }

    return HttpResponse.json(subject)
  }),

  http.post('/api/subjects/lookup', async ({ request }) => {
    await withLatency()
    const body = (await request.json()) as { ids: string[] }
    const results: Record<string, Subject | null> = {}
    for (const id of body.ids) {
      results[id] = subjects.find((s) => s.id === id) ?? null
    }
    return HttpResponse.json(results)
  }),

  http.patch('/api/subjects/:subjectId', async ({ request, params }) => {
    await withLatency()
    const payload = (await request.json()) as Partial<Subject>
    const index = subjects.findIndex((record) => record.id === params.subjectId)

    if (index === -1) {
      return HttpResponse.json({ message: 'Subject not found.' }, { status: 404 })
    }

    subjects[index] = {
      ...subjects[index],
      ...payload,
      updatedAt: new Date().toISOString(),
    }

    return HttpResponse.json(subjects[index])
  }),
]
