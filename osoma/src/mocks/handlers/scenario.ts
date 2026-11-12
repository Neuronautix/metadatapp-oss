import { http, HttpResponse } from 'msw'
import { createScenario, findScenario, listScenarios } from '@/mocks/data/scenarios.ts'
import { alarms, getThresholdsForScope, outOfRangeScopes } from '@/mocks/data/tecniplast.ts'
import { replayScenario } from '@/domain/scenario/replay-engine.ts'
import type { ScopeType } from '@/domain/scope.ts'

export const scenarioHandlers = [
  http.get('/api/scenarios', () => {
    return HttpResponse.json({ data: listScenarios() })
  }),

  http.get('/api/scenarios/:id', ({ params }) => {
    const scenario = findScenario(params.id as string)
    if (!scenario) {
      return HttpResponse.json({ message: 'Scenario not found' }, { status: 404 })
    }
    return HttpResponse.json({ scenario })
  }),

  http.post('/api/scenarios', async ({ request }) => {
    const body = (await request.json().catch(() => ({}))) as {
      label?: string
      baseTimestamp?: string
      scope?: { type?: ScopeType; id?: string; label?: string }
      overrides?: unknown
      createdBy?: string
    }

    if (!body.scope?.type || !body.scope?.id || !body.baseTimestamp) {
      return HttpResponse.json({ message: 'label, baseTimestamp, and scope are required' }, { status: 400 })
    }

    const scenario = createScenario({
      label: body.label ?? 'Scenario',
      baseTimestamp: body.baseTimestamp,
      scope: {
        type: body.scope.type,
        id: body.scope.id,
        label: body.scope.label,
      },
      overrides: (body.overrides as Record<string, unknown>) ?? {},
      createdBy: body.createdBy ?? 'Scenario Builder',
    })

    return HttpResponse.json({ scenario })
  }),

  http.post('/api/scenarios/:id/replay', async ({ params, request }) => {
    const scenario = findScenario(params.id as string)
    if (!scenario) {
      return HttpResponse.json({ message: 'Scenario not found' }, { status: 404 })
    }
    const payload = (await request.json().catch(() => ({}))) as { at?: string }
    const at = payload.at ?? scenario.baseTimestamp
    const scopeType = scenario.scope.type as ScopeType
    const scopeId = scenario.scope.id
    const thresholds = getThresholdsForScope(scopeType, scopeId).map((item) => ({ ...item }))
    const result = replayScenario({
      scopeType,
      scopeId,
      scopeLabel: scenario.scope.label,
      at,
      thresholds,
      baseAlarms: alarms,
      outOfRangeScopes,
      scenario,
    })
    return HttpResponse.json(result)
  }),
]
