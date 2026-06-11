import { cages, racks, rooms, subjects } from '@/mocks/data/tecniplast.ts'
import { buildContextAt, buildScenarioBundle, buildWelfareAlarms, buildQuality } from './dvcScenarios.ts'
import type { CageDetail, CageHcmResponse, CageSummary, CageSubjectsResponse } from '@/features/core/cages/cage.types.ts'

const rackMap = new Map(racks.map((rack) => [rack.id, rack]))
const roomMap = new Map(rooms.map((room) => [room.id, room]))

export const buildCageSummaries = (): CageSummary[] => {
  return cages.map((cage) => {
    const rack = rackMap.get(cage.rackId)
    const room = roomMap.get(rack?.roomId ?? '')
    const context = buildContextAt(cage.id)
    const quality = buildQuality(cage.id)
    const alerts = buildWelfareAlarms(cage.id).length
    return {
      id: cage.id,
      label: cage.label,
      room: room?.name ?? 'Unknown room',
      rack: rack?.label ?? 'Unknown rack',
      bedding: context.beddingMaterial,
      sanitary: context.sanitaryStatus,
      lastSeen: quality.lastSeen,
      alerts,
    }
  })
}

export const buildCageDetail = (id: string): CageDetail | null => {
  const cage = cages.find((item) => item.id === id)
  if (!cage) return null
  const rack = rackMap.get(cage.rackId)
  const room = roomMap.get(rack?.roomId ?? '')
  const context = buildContextAt(cage.id)
  const quality = buildQuality(cage.id)
  const alerts = buildWelfareAlarms(cage.id).length
  const bundle = buildScenarioBundle(cage.id)

  return {
    id: cage.id,
    label: cage.label,
    room: room?.name ?? 'Unknown room',
    rack: rack?.label ?? 'Unknown rack',
    bedding: context.beddingMaterial,
    sanitary: context.sanitaryStatus,
    lastSeen: quality.lastSeen,
    alerts,
    cageType: context.cageType,
    facility: context.facility,
    beddingBatch: context.beddingBatch,
    diet: context.diet,
    water: context.water,
    device: context.device,
    firmware: context.firmware,
    pipelineVersion: context.pipelineVersion,
    quality: {
      missingDataPct: quality.missingDataPct,
      sensorHealth: quality.sensorHealth,
    },
    events: bundle.events,
  }
}

export const buildCageSubjects = (id: string): CageSubjectsResponse => {
  return {
    cageId: id,
    subjects: subjects.filter((subject) => subject.cageId === id),
  }
}

export const buildCageHcm = (id: string): CageHcmResponse => {
  const cage = buildCageDetail(id)
  if (!cage) {
    return { cageId: id, systems: [] }
  }

  const recordedAt = new Date('2026-03-04T18:00:00.000Z').toISOString()
  const variables = [
    {
      id: `${id}-activity-variable`,
      name: 'Tecniplast DVC activity index',
      externalId: 'OBI:0001444',
      tecniplastExternalId: 'DVC_METRIC_ACTIVITY',
    },
    {
      id: `${id}-sinusoid-variable`,
      name: 'Tecniplast DVC sinusoid extraction',
      externalId: null,
      tecniplastExternalId: null,
    },
  ]

  return {
    cageId: id,
    systems: [
      {
        id: `${id}-hcm`,
        system: 'Tecniplast DVC',
        sourceAppCode: 'tecniplast_dvc',
        sourceAppCodes: ['tecniplast_dvc'],
        lightsOnUtcHour: 6,
        lightDurationHours: 12,
        experiment: {
          id: `${id}-experiment`,
          name: 'Mock HCM benchmark extraction',
          protocol: 'HCM benchmark with activity index, environment telemetry, and sinusoid extraction.',
          project: {
            id: `${id}-project`,
            name: 'Mock HCM comparison',
          },
        },
        environment: {
          temperature: 21.6,
          humidity: 52.4,
          lightIntensity: 185,
          noise: 41.5,
          lightCycle: '08:00 ON, 20:00 OFF',
          humanMovementWorkday: 8.4,
        },
        modalities: ['activity', 'sinusoid'],
        variables,
        measures: [
          {
            id: `${id}-measure-activity`,
            variable: variables[0],
            dataType: 'activity',
            value: 42.7,
            recordedAt,
            sourceTaskId: 'MOCK-DVC-MEASURE-01',
            sourceAppCode: 'tecniplast_dvc',
          },
          {
            id: `${id}-measure-sinusoid`,
            variable: variables[1],
            dataType: 'sinusoid',
            value: 18.4,
            recordedAt: new Date('2026-03-04T19:00:00.000Z').toISOString(),
            sourceTaskId: 'MOCK-DVC-MEASURE-02',
            sourceAppCode: 'tecniplast_dvc',
          },
        ],
        sinusoidMetadata: [
          {
            id: `${id}-sinusoid`,
            date: '2026-03-04',
            amplitude: 18.4,
            mesor: 42.0,
            phase: 1.62,
            peakHour: 18.2,
            sourceTaskId: 'MOCK-DVC-TASK-01',
            sourceAppCode: 'tecniplast_dvc',
          },
        ],
        provenance: [
          {
            type: 'measure',
            sourceAppCode: 'tecniplast_dvc',
            sourceTaskId: 'MOCK-DVC-MEASURE-01',
            at: recordedAt,
            label: 'Tecniplast DVC activity index',
          },
          {
            type: 'sinusoid',
            sourceAppCode: 'tecniplast_dvc',
            sourceTaskId: 'MOCK-DVC-TASK-01',
            at: '2026-03-04',
            label: 'MIROsine sinusoid fit',
          },
        ],
      },
    ],
  }
}
