import { http, HttpResponse } from 'msw'
import { buildCageDetail, buildCageHcm, buildCageSubjects, buildCageSummaries } from '@/mocks/data/cages.ts'
import { withLatency } from './utils.ts'

export const cagesHandlers = [
  http.get('/api/cages', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const search = (url.searchParams.get('search') ?? '').toLowerCase()
    const data = buildCageSummaries().filter((cage) => {
      if (!search) return true
      return [cage.id, cage.label, cage.room, cage.rack]
        .some((value) => value.toLowerCase().includes(search))
    })
    return HttpResponse.json({
      'hydra:member': data,
      'hydra:totalItems': data.length,
    })
  }),
  http.get('/api/cages/:id', async ({ params }) => {
    await withLatency()
    const cage = buildCageDetail(params.id as string)
    if (!cage) {
      return HttpResponse.json({ message: 'Cage not found.' }, { status: 404 })
    }
    return HttpResponse.json(cage)
  }),
  http.get('/api/cages/:id/subjects', async ({ params }) => {
    await withLatency()
    return HttpResponse.json(buildCageSubjects(params.id as string))
  }),
  http.get('/api/cages/:id/hcm', async ({ params }) => {
    await withLatency()
    return HttpResponse.json(buildCageHcm(params.id as string))
  }),
]
