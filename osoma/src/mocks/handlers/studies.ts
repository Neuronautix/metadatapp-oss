import { http, HttpResponse } from 'msw'
import { studies } from '@/mocks/data/studies.ts'
import type { Study } from '@/domain/resources.ts'
import { computeFairCriteria } from '@/lib/fair.ts'
import { matches, paginate, withLatency } from './utils.ts'

type FairCriterionWithNote = {
  id: string
  label: string
  description: string
  pass: boolean
  note: string
}

type FairCriteriaPayload = Record<string, FairCriterionWithNote[]>

export const studiesHandlers = [
  http.get('/api/experiments', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const pageSize = Number(url.searchParams.get('pageSize') ?? 7)
    const search = url.searchParams.get('search')
    const status = url.searchParams.get('status')
    const investigation = url.searchParams.get('investigation')
    const qcStatus = url.searchParams.get('qcStatus')
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Study service is degraded.' }, { status: 503 })
    }

    let filtered = [...studies]

    if (search) {
      filtered = filtered.filter(
        (study) =>
          matches(study.name, search) ||
          matches(study.investigationName, search) ||
          matches(study.assayName, search) ||
          matches(study.id, search)
      )
    }

    if (status) {
      filtered = filtered.filter((study) => study.status === status)
    }

    if (investigation) {
      filtered = filtered.filter((study) => study.investigationName === investigation)
    }

    if (qcStatus) {
      filtered = filtered.filter((study) => study.qcStatus === qcStatus)
    }

    const { data, total } = paginate(filtered, page, pageSize)

    return HttpResponse.json({
      'hydra:member': data,
      'hydra:totalItems': total,
      'hydra:view': {
        '@id': `/experiments?page=${page}&pageSize=${pageSize}`,
        'hydra:first': `/experiments?page=1&pageSize=${pageSize}`,
        'hydra:last': `/experiments?page=${Math.ceil(total / pageSize)}&pageSize=${pageSize}`,
        'hydra:next': page < Math.ceil(total / pageSize) ? `/experiments?page=${page + 1}&pageSize=${pageSize}` : undefined,
      },
    })
  }),

  http.get('/api/experiments/:studyId', async ({ params, request }) => {
    await withLatency()
    const url = new URL(request.url)
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'Study record unavailable.' }, { status: 500 })
    }

    const study = studies.find((record) => record.id === params.studyId)
    if (!study) {
      return HttpResponse.json({ message: 'Study not found.' }, { status: 404 })
    }

    return HttpResponse.json(study)
  }),

  http.patch('/api/experiments/:studyId', async ({ request, params }) => {
    await withLatency()
    const payload = (await request.json()) as Partial<Study>
    const index = studies.findIndex((record) => record.id === params.studyId)

    if (index === -1) {
      return HttpResponse.json({ message: 'Study not found.' }, { status: 404 })
    }

    studies[index] = {
      ...studies[index],
      ...payload,
      updatedAt: new Date().toISOString(),
    }

    return HttpResponse.json(studies[index])
  }),

  http.post('/api/experiments/:studyId/sign', async ({ params }) => {
    await withLatency()
    const index = studies.findIndex((record) => record.id === params.studyId)

    if (index === -1) {
      return HttpResponse.json({ message: 'Study not found.' }, { status: 404 })
    }

    const now = new Date().toISOString()
    const user = 'Dr. Laurent Huzard' // Mock user for now

    studies[index] = {
      ...studies[index],
      isSigned: true,
      signedBy: user,
      signedAt: now,
      updatedAt: now,
      provenance: [
        {
          id: `exp-prov-${Date.now()}`,
          at: now,
          actor: user,
          action: 'Study Signed',
          detail: 'Scientific validation confirmed.',
        },
        ...studies[index].provenance,
      ],
    }

    return HttpResponse.json(studies[index])
  }),

  // FAIR assessment JSON endpoint (used by the AI assistant via MCP in mock mode)
  http.get('/api/studies/:studyId/fair-assessment', async ({ params }) => {
    await withLatency()
    const study = studies.find((record) => record.id === params.studyId)

    if (!study) {
      return HttpResponse.json({ message: 'Study not found.' }, { status: 404 })
    }

    const criteria = computeFairCriteria(study)
    const score = study.fairScore

    // Map FAIRCriteria (grouped by pillar) into the backend shape
    const criteriaPayload: FairCriteriaPayload = {}
    for (const [pillar, items] of Object.entries(criteria)) {
      const pillarLabel = pillar.charAt(0).toUpperCase() + pillar.slice(1)
      criteriaPayload[pillarLabel] = items.map((item) => ({
        id: item.id,
        label: item.label,
        description: item.description,
        pass: item.pass,
        note: item.pass ? 'Criterion satisfied.' : 'Criterion not satisfied.',
      }))
    }

    return HttpResponse.json({
      id: study.id,
      studyName: study.name,
      investigationName: study.investigationName,
      score,
      criteria: criteriaPayload,
      assessedAt: new Date().toISOString(),
    })
  }),

  // FAIR report PDF endpoint (mock — returns a minimal placeholder PDF)
  http.get('/api/studies/:studyId/fair-report.pdf', async ({ params }) => {
    await withLatency()
    const study = studies.find((record) => record.id === params.studyId)

    if (!study) {
      return HttpResponse.json({ message: 'Study not found.' }, { status: 404 })
    }

    // Minimal stub so the download button works in mock mode
    const pdfContent = `%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n%%EOF`
    return new HttpResponse(pdfContent, {
      status: 200,
      headers: {
        'Content-Type': 'application/pdf',
        'Content-Disposition': `attachment; filename="fair-report-study-${params.studyId}.pdf"`,
      },
    })
  }),
]
