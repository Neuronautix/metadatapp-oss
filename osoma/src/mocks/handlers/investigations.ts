import { http, HttpResponse } from 'msw'
import { investigations } from '@/mocks/data/investigations.ts'
import { investigationOperators } from '@/mocks/data/investigationOperators.ts'
import { investigationDashboards } from '@/mocks/data/investigationDashboards.ts'
import { investigationActivity } from '@/mocks/data/investigationActivity.ts'
import { investigationExports } from '@/mocks/data/investigationExports.ts'
import { animals } from '@/mocks/data/animals.ts'
import { keyboardShortcuts } from '@/mocks/data/keyboardShortcuts.ts'
import type { Investigation, InvestigationCreateInput, InvestigationUpdateInput } from '@/features/core/investigations/investigation.types.ts'
import { withLatency } from './utils.ts'

const buildInvestigationId = () => `PRJ-${Math.floor(1000 + Math.random() * 9000)}`
const escapeCsv = (value: string | number) => {
  const stringValue = String(value)
  if (/[",\n]/.test(stringValue)) {
    return `"${stringValue.replace(/"/g, '""')}"`
  }
  return stringValue
}

export const investigationsHandlers = [
  http.get('/api/projects', async () => {
    await withLatency()
    return HttpResponse.json({
      'hydra:member': investigations,
      'hydra:totalItems': investigations.length,
    })
  }),

  http.get('/api/projects/activity', async () => {
    await withLatency()
    return HttpResponse.json({ data: investigationActivity })
  }),

  http.get('/api/keyboard-shortcuts', async () => {
    await withLatency()
    return HttpResponse.json({ data: keyboardShortcuts })
  }),

  http.get('/api/investigation-operators', async () => {
    await withLatency()
    return HttpResponse.json({ data: investigationOperators })
  }),

  http.get('/api/projects/:investigationId', async ({ params }) => {
    await withLatency()
    const investigation = investigations.find((record) => record.id === params.investigationId)
    if (!investigation) {
      return HttpResponse.json({ message: 'Investigation not found.' }, { status: 404 })
    }

    return HttpResponse.json(investigation)
  }),

  http.post('/api/projects', async ({ request }) => {
    await withLatency()
    const payload = (await request.json()) as InvestigationCreateInput
    const now = new Date().toISOString()
    const assignedOperators = investigationOperators.filter((operator) =>
      payload.assignedOperatorIds?.includes(operator.id)
    )

    const newInvestigation: Investigation = {
      id: buildInvestigationId(),
      name: payload.name ?? 'New Mouse Study',
      description: payload.description ?? 'New study awaiting scope details.',
      species: 'Mouse',
      startAt: payload.startDate ?? now.slice(0, 10),
      endAt: payload.endDate,
      fairScore: { total: 72, findable: 74, accessible: 68, interoperable: 70, reusable: 76 },
      assignedOperators,
      assignedAnimalIds: [],
      createdAt: now,
      updatedAt: now,
      tags: [],
    }

    investigations.unshift(newInvestigation)
    return HttpResponse.json(newInvestigation, { status: 201 })
  }),

  http.patch('/api/projects/:investigationId', async ({ request, params }) => {
    await withLatency()
    const payload = (await request.json()) as InvestigationUpdateInput
    const index = investigations.findIndex((record) => record.id === params.investigationId)

    if (index === -1) {
      return HttpResponse.json({ message: 'Investigation not found.' }, { status: 404 })
    }

    const { assignedOperatorIds, ...rest } = payload
    const assignedOperators = assignedOperatorIds
      ? investigationOperators.filter((operator) => assignedOperatorIds.includes(operator.id))
      : investigations[index].assignedOperators

    investigations[index] = {
      ...investigations[index],
      ...rest,
      assignedOperators,
      updatedAt: new Date().toISOString(),
    }

    return HttpResponse.json(investigations[index])
  }),

  http.patch('/api/projects/:investigationId/tags', async ({ request, params }) => {
    await withLatency()
    const payload = (await request.json()) as { tags?: string[] }
    const index = investigations.findIndex((record) => record.id === params.investigationId)

    if (index === -1) {
      return HttpResponse.json({ message: 'Investigation not found.' }, { status: 404 })
    }

    investigations[index] = {
      ...investigations[index],
      tags: payload.tags ?? [],
      updatedAt: new Date().toISOString(),
    }

    return HttpResponse.json(investigations[index])
  }),

  http.get('/api/projects/:investigationId/animals/export', async ({ params }) => {
    await withLatency()
    const investigationId = params.investigationId as string
    const status = investigationExports[investigationId]
    return HttpResponse.json({
      investigationId,
      lastExportAt: status?.lastExportAt,
      rowCount: status?.rowCount ?? 0,
    })
  }),

  http.post('/api/projects/:investigationId/animals/export', async ({ params }) => {
    await withLatency()
    const investigationId = params.investigationId as string
    const list = animals.filter((animal) => animal.investigationId === investigationId)
    const header = ['id', 'tagId', 'nickname', 'cage', 'lastWeight']
    const rows = list.map((animal) =>
      [animal.id, animal.tagId, animal.nickname, animal.cage, animal.lastWeight].map(escapeCsv).join(',')
    )
    const csv = [header.join(','), ...rows].join('\n')
    const now = new Date().toISOString()
    investigationExports[investigationId] = { lastExportAt: now, rowCount: list.length }
    return HttpResponse.json({ exportedAt: now, rowCount: list.length, csv })
  }),

  http.get('/api/projects/:investigationId/dashboard', async ({ params }) => {
    await withLatency()
    const dashboard = investigationDashboards[params.investigationId as string]
    if (!dashboard) {
      return HttpResponse.json({ message: 'Investigation dashboard not found.' }, { status: 404 })
    }

    return HttpResponse.json(dashboard)
  }),
]
