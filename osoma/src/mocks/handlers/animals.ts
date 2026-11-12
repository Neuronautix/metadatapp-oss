import { http, HttpResponse } from 'msw'
import {
  animals,
  animalWeightsById,
  analysisResultsById,
  behaviorSeriesById,
  bloodSamplesById,
  treatmentEventsById,
  weightRangesByAnimalId,
} from '@/mocks/data/animals.ts'
import { investigations } from '@/mocks/data/investigations.ts'
import { investigationOperators } from '@/mocks/data/investigationOperators.ts'
import type {
  AnimalTimelineEvent,
  AnimalWeightEntry,
  BloodSample,
  TreatmentEvent,
} from '@/features/core/animals/animal.types.ts'
import { withLatency } from './utils.ts'

const buildWeightId = (animalId: string, index: number) => `WT-${animalId}-${index}`
const buildTreatmentId = (animalId: string) => `TRT-${animalId}-${Date.now()}`
const buildSampleId = (investigationId: string) => `BS-${investigationId}-${Math.floor(1000 + Math.random() * 9000)}`
const operatorColorMap = new Map(investigationOperators.map((operator) => [operator.name, operator.avatarColor ?? 'slate']))

const buildInitials = (name: string) =>
  name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')

const buildActorAvatar = (actor?: string) => {
  if (!actor) {
    return undefined
  }
  return {
    initials: buildInitials(actor) || 'NA',
    color: operatorColorMap.get(actor) ?? 'slate',
  }
}

const buildTimeline = (animalId: string) => {
  const animal = animals.find((record) => record.id === animalId)
  if (!animal) {
    return []
  }

  const events: AnimalTimelineEvent[] = []
  if (animal.assignedAt && animal.investigationId) {
    events.push({
      id: `TL-${animal.id}-assigned`,
      at: animal.assignedAt,
      kind: 'assignment',
      title: 'Assigned to investigation',
      detail: `Joined ${animal.investigationId} (${animal.cohort}).`,
      actor: 'Study intake',
      actorAvatar: buildActorAvatar('Study intake'),
    })
  }

  const weights = animalWeightsById[animalId] ?? []
  weights.slice(-6).forEach((weight) => {
    events.push({
      id: `TL-${weight.id}`,
      at: weight.at,
      kind: 'weight',
      title: 'Weight recorded',
      detail: `${weight.grams.toFixed(1)} g (${weight.source})`,
      actor: weight.source === 'scale' ? 'Scale feed' : 'Manual entry',
      actorAvatar: buildActorAvatar(weight.source === 'scale' ? 'Scale feed' : 'Manual entry'),
    })
  })

  const treatments = treatmentEventsById[animalId] ?? []
  treatments.forEach((treatment) => {
    events.push({
      id: `TL-${treatment.id}`,
      at: treatment.administeredAt,
      kind: 'treatment',
      title: `${treatment.drug} administered`,
      detail: `${treatment.dose} ${treatment.doseUnit} • ${treatment.pathogen}`,
      actor: treatment.operator,
      actorAvatar: buildActorAvatar(treatment.operator),
    })
  })

  const samples = bloodSamplesById[animalId] ?? []
  samples.forEach((sample) => {
    events.push({
      id: `TL-${sample.id}`,
      at: sample.collectedAt,
      kind: 'sample',
      title: `Blood sample ${sample.sampleId}`,
      detail: `${sample.volume}${sample.volumeUnit} from ${sample.site}`,
      actor: sample.operator,
      actorAvatar: buildActorAvatar(sample.operator),
    })
  })

  const analysis = analysisResultsById[animalId]
  if (analysis) {
    events.push({
      id: `TL-${animalId}-analysis`,
      at: analysis.updatedAt,
      kind: 'analysis',
      title: 'Analysis panel refreshed',
      detail: `${analysis.flow.length} flow, ${analysis.pcr.length} PCR, ${analysis.antibody.length} antibody results.`,
      actor: 'Assay pipeline',
      actorAvatar: buildActorAvatar('Assay pipeline'),
    })
  }

  const behavior = behaviorSeriesById[animalId]
  if (behavior) {
    behavior.points
      .filter((point) => point.anomaly)
      .forEach((point, index) => {
        events.push({
          id: `TL-${animalId}-behavior-${index}`,
          at: point.at,
          kind: 'behavior',
          title: 'Behavior anomaly',
          detail: `Activity spike detected (${point.activity}).`,
          actor: 'Circadian model',
          actorAvatar: buildActorAvatar('Circadian model'),
        })
      })
  }

  const range = weightRangesByAnimalId[animalId]
  if (range && animal.lastWeight > range.max) {
    events.push({
      id: `TL-${animalId}-alert`,
      at: animal.lastWeighedAt,
      kind: 'alert',
      title: 'Out-of-range weight',
      detail: `${animal.lastWeight.toFixed(1)} g exceeds ${range.max} g max.`,
      actor: 'Auto monitor',
      actorAvatar: buildActorAvatar('Auto monitor'),
    })
  }

  return events.sort((a, b) => new Date(b.at).getTime() - new Date(a.at).getTime())
}

export const animalsHandlers = [
  http.get('/api/ticklab/animals', async () => {
    await withLatency()
    return HttpResponse.json({ data: animals.filter((animal) => !animal.investigationId) })
  }),

  http.get('/api/investigations/:investigationId/animals', async ({ params }) => {
    await withLatency()
    const data = animals.filter((animal) => animal.investigationId === params.investigationId)
    return HttpResponse.json({ data })
  }),

  http.post('/api/investigations/:investigationId/animals', async ({ request, params }) => {
    await withLatency()
    const payload = (await request.json()) as { animalIds?: string[] }
    const investigation = investigations.find((record) => record.id === params.investigationId)

    if (!investigation) {
      return HttpResponse.json({ message: 'Investigation not found.' }, { status: 404 })
    }

    const now = new Date().toISOString()
    const animalIds = payload.animalIds ?? []
    animalIds.forEach((animalId) => {
      const animal = animals.find((record) => record.id === animalId)
      if (animal) {
        animal.investigationId = investigation.id
        animal.assignedAt = now
      }
    })

    investigation.assignedAnimalIds = Array.from(new Set([...investigation.assignedAnimalIds, ...animalIds]))
    investigation.updatedAt = now

    return HttpResponse.json(investigation)
  }),

  http.get('/api/animals/:animalId', async ({ params }) => {
    await withLatency()
    const animal = animals.find((record) => record.id === params.animalId)
    if (!animal) {
      return HttpResponse.json({ message: 'Animal not found.' }, { status: 404 })
    }

    return HttpResponse.json(animal)
  }),

  http.patch('/api/animals/:animalId/note', async ({ request, params }) => {
    await withLatency()
    const payload = (await request.json()) as { note?: string }
    const animal = animals.find((record) => record.id === params.animalId)
    if (!animal) {
      return HttpResponse.json({ message: 'Animal not found.' }, { status: 404 })
    }
    animal.quickNote = payload.note ?? ''
    return HttpResponse.json(animal)
  }),

  http.patch('/api/animals/:animalId/favorite', async ({ request, params }) => {
    await withLatency()
    const payload = (await request.json()) as { isFavorite?: boolean }
    const animal = animals.find((record) => record.id === params.animalId)
    if (!animal) {
      return HttpResponse.json({ message: 'Animal not found.' }, { status: 404 })
    }
    animal.isFavorite = Boolean(payload.isFavorite)
    return HttpResponse.json(animal)
  }),

  http.get('/api/animals/:animalId/weights', async ({ params }) => {
    await withLatency()
    return HttpResponse.json({ data: animalWeightsById[params.animalId as string] ?? [] })
  }),

  http.post('/api/animals/:animalId/weights', async ({ params, request }) => {
    await withLatency()
    const payload = (await request.json()) as Pick<AnimalWeightEntry, 'grams' | 'at' | 'source'>
    const list = animalWeightsById[params.animalId as string] ?? []
    const newEntry: AnimalWeightEntry = {
      id: buildWeightId(params.animalId as string, list.length + 1),
      animalId: params.animalId as string,
      grams: payload.grams,
      at: payload.at,
      source: payload.source ?? 'scale',
    }
    list.push(newEntry)
    animalWeightsById[params.animalId as string] = list

    const animal = animals.find((record) => record.id === params.animalId)
    if (animal) {
      animal.lastWeight = payload.grams
      animal.lastWeighedAt = payload.at
    }

    return HttpResponse.json(newEntry, { status: 201 })
  }),

  http.get('/api/animals/:animalId/weight-range', async ({ params }) => {
    await withLatency()
    const range = weightRangesByAnimalId[params.animalId as string] ?? { min: 18, max: 25, target: 22 }
    return HttpResponse.json(range)
  }),

  http.get('/api/animals/:animalId/treatments', async ({ params }) => {
    await withLatency()
    return HttpResponse.json({ data: treatmentEventsById[params.animalId as string] ?? [] })
  }),

  http.post('/api/animals/:animalId/treatments', async ({ params, request }) => {
    await withLatency()
    const payload = (await request.json()) as Omit<TreatmentEvent, 'id' | 'animalId'>
    const list = treatmentEventsById[params.animalId as string] ?? []
    const newEvent: TreatmentEvent = {
      ...payload,
      id: buildTreatmentId(params.animalId as string),
      animalId: params.animalId as string,
    }
    list.unshift(newEvent)
    treatmentEventsById[params.animalId as string] = list

    return HttpResponse.json(newEvent, { status: 201 })
  }),

  http.get('/api/animals/:animalId/samples', async ({ params }) => {
    await withLatency()
    return HttpResponse.json({ data: bloodSamplesById[params.animalId as string] ?? [] })
  }),

  http.post('/api/animals/:animalId/samples', async ({ params, request }) => {
    await withLatency()
    const payload = (await request.json()) as Omit<BloodSample, 'id' | 'animalId' | 'sampleId'>
    const list = bloodSamplesById[params.animalId as string] ?? []
    const newSample: BloodSample = {
      ...payload,
      id: `SMP-${params.animalId}-${list.length + 1}`,
      animalId: params.animalId as string,
      sampleId: buildSampleId(payload.investigationId),
    }
    list.unshift(newSample)
    bloodSamplesById[params.animalId as string] = list

    return HttpResponse.json(newSample, { status: 201 })
  }),

  http.get('/api/animals/:animalId/analysis', async ({ params }) => {
    await withLatency()
    const analysis = analysisResultsById[params.animalId as string]
    if (!analysis) {
      return HttpResponse.json({ flow: [], pcr: [], antibody: [], trends: [], updatedAt: new Date().toISOString() })
    }
    return HttpResponse.json(analysis)
  }),

  http.get('/api/animals/:animalId/behavior', async ({ params }) => {
    await withLatency()
    const behavior = behaviorSeriesById[params.animalId as string]
    if (!behavior) {
      return HttpResponse.json({ points: [], baseline: 0, circadianPeak: '--', circadianLow: '--' })
    }
    return HttpResponse.json(behavior)
  }),

  http.get('/api/animals/:animalId/timeline', async ({ params }) => {
    await withLatency()
    return HttpResponse.json({ data: buildTimeline(params.animalId as string) })
  }),
]
