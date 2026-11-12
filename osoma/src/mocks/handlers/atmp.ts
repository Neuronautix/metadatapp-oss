import { http, HttpResponse } from 'msw'
import { atmpStudies } from '@/mocks/data/atmp.ts'
import type { AtmpStudy } from '@/domain/atmp/atmp.types.ts'
import { matches, withLatency } from './utils.ts'

const shouldThrowUnprocessable = () => Math.random() < 0.18

export const atmpHandlers = [
  http.get('/api/atmp/studies', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const search = url.searchParams.get('search')
    const atmpCategory = url.searchParams.get('atmpCategory')
    const species = url.searchParams.get('species')

    let filtered = [...atmpStudies]

    if (search) {
      filtered = filtered.filter(
        (study) =>
          matches(study.title, search) ||
          matches(study.investigationID, search) ||
          matches(study.chainOfIdentityID, search)
      )
    }

    if (atmpCategory) {
      filtered = filtered.filter((study) => study.atmpCategory === atmpCategory)
    }

    if (species) {
      filtered = filtered.filter((study) => matches(study.species, species))
    }

    return HttpResponse.json({ data: filtered, total: filtered.length })
  }),

  http.get('/api/atmp/studies/:investigationID', async ({ params }) => {
    await withLatency()
    const study = atmpStudies.find((record) => record.investigationID === params.investigationID)
    if (!study) {
      return HttpResponse.json({ message: 'ATMP study not found.' }, { status: 404 })
    }

    return HttpResponse.json(study)
  }),

  http.post('/api/atmp/studies', async ({ request }) => {
    await withLatency()
    if (shouldThrowUnprocessable()) {
      return HttpResponse.json({ message: 'ATMP validation failed.' }, { status: 422 })
    }

    const payload = (await request.json()) as AtmpStudy
    if (!payload.investigationID || !payload.chainOfIdentityID) {
      return HttpResponse.json({ message: 'Investigation ID and COI are required.' }, { status: 422 })
    }

    if (atmpStudies.some((record) => record.investigationID === payload.investigationID)) {
      return HttpResponse.json({ message: 'ATMP study already exists.' }, { status: 409 })
    }

    const timestamp = new Date().toISOString()
    const next: AtmpStudy = {
      ...payload,
      createdAt: payload.createdAt ?? timestamp,
      updatedAt: timestamp,
    }

    atmpStudies.unshift(next)
    return HttpResponse.json(next, { status: 201 })
  }),

  http.put('/api/atmp/studies/:investigationID', async ({ request, params }) => {
    await withLatency()
    if (shouldThrowUnprocessable()) {
      return HttpResponse.json({ message: 'ATMP update rejected.' }, { status: 422 })
    }

    const payload = (await request.json()) as Partial<AtmpStudy>
    const index = atmpStudies.findIndex((record) => record.investigationID === params.investigationID)

    if (index === -1) {
      return HttpResponse.json({ message: 'ATMP study not found.' }, { status: 404 })
    }

    atmpStudies[index] = {
      ...atmpStudies[index],
      ...payload,
      updatedAt: new Date().toISOString(),
    }

    return HttpResponse.json(atmpStudies[index])
  }),
]
