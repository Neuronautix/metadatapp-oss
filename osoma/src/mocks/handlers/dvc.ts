import { http, HttpResponse } from 'msw'
import {
  buildActivitySeries,
  buildDatasetActivitySeries,
  buildAiOutput,
  buildContextAt,
  buildEnvironmentSeries,
  buildScenarioBundle,
  buildVcgSelection,
  buildWelfareAlarms,
  datasets,
} from '@/mocks/data/dvcScenarios.ts'
import { withLatency } from './utils.ts'
import type { VcgMatchMode } from '@/domain/dvc/dvc.enums.ts'

const shouldReject = () => Math.random() < 0.12
const shouldFail = () => Math.random() < 0.08

const toNumber = (value: string | null, fallback: number) => {
  if (!value) return fallback
  const parsed = Number(value)
  return Number.isNaN(parsed) ? fallback : parsed
}

export const dvcHandlers = [
  http.get('/api/dvc/cages/:id/activity', async ({ params, request }) => {
    await withLatency()
    if (shouldFail()) {
      return HttpResponse.json({ message: 'Activity stream unavailable.' }, { status: 500 })
    }
    const cageId = params.id as string
    const url = new URL(request.url)
    const interval = toNumber(url.searchParams.get('interval'), 15)
    return HttpResponse.json(buildActivitySeries(cageId, url.searchParams.get('from') ?? undefined, url.searchParams.get('to') ?? undefined, interval))
  }),
  http.get('/api/dvc/cages/:id/environment', async ({ params, request }) => {
    await withLatency()
    if (shouldFail()) {
      return HttpResponse.json({ message: 'Environment feed unavailable.' }, { status: 500 })
    }
    const cageId = params.id as string
    const url = new URL(request.url)
    const interval = toNumber(url.searchParams.get('interval'), 15)
    return HttpResponse.json(buildEnvironmentSeries(cageId, url.searchParams.get('from') ?? undefined, url.searchParams.get('to') ?? undefined, interval))
  }),
  http.get('/api/dvc/cages/:id/events', async ({ params, request }) => {
    await withLatency()
    if (shouldFail()) {
      return HttpResponse.json({ message: 'Event feed unavailable.' }, { status: 500 })
    }
    const cageId = params.id as string
    const url = new URL(request.url)
    const bundle = buildScenarioBundle(cageId, url.searchParams.get('from') ?? undefined, url.searchParams.get('to') ?? undefined, toNumber(url.searchParams.get('interval'), 15))
    return HttpResponse.json({ data: bundle.events })
  }),
  http.get('/api/dvc/cages/:id/welfare-alarms', async ({ params }) => {
    await withLatency()
    const cageId = params.id as string
    return HttpResponse.json({ data: buildWelfareAlarms(cageId) })
  }),
  http.get('/api/dvc/cages/:id/ai', async ({ params, request }) => {
    await withLatency()
    const cageId = params.id as string
    const url = new URL(request.url)
    const interval = toNumber(url.searchParams.get('interval'), 15)
    return HttpResponse.json(buildAiOutput(cageId, url.searchParams.get('from') ?? undefined, url.searchParams.get('to') ?? undefined, interval))
  }),
  http.get('/api/dvc/cages/:id/context-snapshot', async ({ params, request }) => {
    await withLatency()
    const cageId = params.id as string
    const url = new URL(request.url)
    return HttpResponse.json(buildContextAt(cageId, url.searchParams.get('at') ?? undefined))
  }),
  http.get('/api/dvc/compare', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const interval = toNumber(url.searchParams.get('interval'), 15)
    const cageIdsParam = url.searchParams.get('cageIds') ?? ''
    const cageIds = cageIdsParam.split(',').map((value) => value.trim()).filter(Boolean)
    const series = cageIds.map((cageId) => buildActivitySeries(cageId, url.searchParams.get('from') ?? undefined, url.searchParams.get('to') ?? undefined, interval))
    const context = cageIds.map((cageId) => buildContextAt(cageId, url.searchParams.get('from') ?? undefined))
    return HttpResponse.json({ data: series, context })
  }),
  http.get('/api/nam/datasets', async () => {
    await withLatency()
    return HttpResponse.json({ data: datasets })
  }),
  http.get('/api/nam/activity', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const interval = toNumber(url.searchParams.get('interval'), 15)
    const datasetIds = (url.searchParams.get('datasetIds') ?? '')
      .split(',')
      .map((value) => value.trim())
      .filter(Boolean)
    const data = datasetIds.length
      ? datasetIds.map((datasetId) =>
          buildDatasetActivitySeries(
            datasetId,
            url.searchParams.get('from') ?? undefined,
            url.searchParams.get('to') ?? undefined,
            interval
          )
        )
      : []
    return HttpResponse.json({ data })
  }),
  http.post('/api/nam/vcg/generate', async ({ request }) => {
    await withLatency()
    if (shouldReject()) {
      return HttpResponse.json({ message: 'VCG generation failed validation.' }, { status: 422 })
    }
    const payload = (await request.json()) as { targetDatasetId?: string; mode?: VcgMatchMode }
    const selection = buildVcgSelection(payload.targetDatasetId ?? datasets[0].id, payload.mode ?? 'Weighted Similarity')
    return HttpResponse.json(selection)
  }),
  http.get('/api/nam/vcg/:id', async ({ params }) => {
    await withLatency()
    const id = params.id as string
    const datasetId = id.replace('VCG-', '')
    return HttpResponse.json(buildVcgSelection(datasetId, 'Weighted Similarity'))
  }),
]
