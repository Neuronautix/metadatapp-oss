import type {
  AIBehaviorOutput,
  CageContextSnapshot,
  CageEvent,
  Dataset,
  DvcActivitySeries,
  EnvironmentRecord,
  Pid,
  QualitySignals,
  WelfareAlarm,
} from '@/domain/dvc/dvc.types.ts'
import type { VcgMatchMode } from '@/domain/dvc/dvc.enums.ts'
import { computeAnomalies } from '@/domain/dvc/dvc.anomalies.ts'
import { toBehaviorOutput } from '@/domain/dvc/dvc.explanations.ts'
import { buildVcgSelection as buildSelection } from './dvcVcg.ts'
import type { NamActivitySeries } from '@/domain/nam/vcg.combine.ts'

const HOUR = 60 * 60 * 1000
const MIN = 60 * 1000

const seedFromString = (input: string) =>
  input.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)

const seeded = (seed: number) => {
  let value = seed
  return () => {
    value = (value * 9301 + 49297) % 233280
    return value / 233280
  }
}

export const datasets: Dataset[] = [
  {
    id: 'DS-NEURO-001',
    pid: 'urn:os:dataset:neuro-001',
    name: 'Neurobehavior Baseline A',
    description: 'Baseline neurobehavior dataset for barrier room cohorts.',
    species: 'Mouse',
    strain: 'C57BL/6J',
    sex: 'female',
    ageWeeks: 12,
    sanitaryStatus: 'Specific pathogen free',
    facility: 'North Campus Vivarium',
    room: 'Room 101',
    environment: '12/12 light cycle',
    beddingBatch: 'BED-AX1',
    beddingMaterial: 'Aspen',
    diet: 'Standard chow',
    device: 'DVC-4K',
    pipelineVersion: 'v3.2.1',
    createdAt: new Date(Date.now() - 12 * 86400000).toISOString(),
    tags: ['baseline', 'behavior', 'circadian'],
  },
  {
    id: 'DS-NEURO-002',
    pid: 'urn:os:dataset:neuro-002',
    name: 'Neurobehavior Baseline B',
    description: 'Matched cohort with alternate bedding batch and device firmware.',
    species: 'Mouse',
    strain: 'C57BL/6J',
    sex: 'female',
    ageWeeks: 12,
    sanitaryStatus: 'Specific pathogen free',
    facility: 'North Campus Vivarium',
    room: 'Room 101',
    environment: '12/12 light cycle',
    beddingBatch: 'BED-AX2',
    beddingMaterial: 'Aspen',
    diet: 'Standard chow',
    device: 'DVC-4K',
    pipelineVersion: 'v3.2.1',
    createdAt: new Date(Date.now() - 11 * 86400000).toISOString(),
    tags: ['baseline', 'control'],
  },
  {
    id: 'DS-NEURO-003',
    pid: 'urn:os:dataset:neuro-003',
    name: 'Circadian Noise Study',
    description: 'Circadian rhythm with elevated noise floor and partial missing data.',
    species: 'Mouse',
    strain: 'C57BL/6J',
    sex: 'female',
    ageWeeks: 12,
    sanitaryStatus: 'Specific pathogen free',
    facility: 'North Campus Vivarium',
    room: 'Room 102',
    environment: '12/12 light cycle',
    beddingBatch: 'BED-AX1',
    beddingMaterial: 'Aspen',
    diet: 'Standard chow',
    device: 'DVC-4K',
    pipelineVersion: 'v3.2.0',
    createdAt: new Date(Date.now() - 10 * 86400000).toISOString(),
    tags: ['noise', 'missing-data'],
  },
  {
    id: 'DS-NEURO-004',
    pid: 'urn:os:dataset:neuro-004',
    name: 'Bedding Shift Cohort',
    description: 'Bedding batch change correlated with activity drop.',
    species: 'Mouse',
    strain: 'C57BL/6J',
    sex: 'female',
    ageWeeks: 12,
    sanitaryStatus: 'Specific pathogen free',
    facility: 'North Campus Vivarium',
    room: 'Room 101',
    environment: '12/12 light cycle',
    beddingBatch: 'BED-BX9',
    beddingMaterial: 'Paper',
    diet: 'Standard chow',
    device: 'DVC-4K',
    pipelineVersion: 'v3.2.1',
    createdAt: new Date(Date.now() - 9 * 86400000).toISOString(),
    tags: ['bedding-change', 'behavior-shift'],
  },
  {
    id: 'DS-NEURO-005',
    pid: 'urn:os:dataset:neuro-005',
    name: 'Temperature Drift Run',
    description: 'Temperature drift scenario with welfare escalation.',
    species: 'Mouse',
    strain: 'C57BL/6J',
    sex: 'female',
    ageWeeks: 12,
    sanitaryStatus: 'Specific pathogen free',
    facility: 'West Clinical Barrier',
    room: 'Room 301',
    environment: '12/12 light cycle',
    beddingBatch: 'BED-AX1',
    beddingMaterial: 'Aspen',
    diet: 'Standard chow',
    device: 'DVC-4K',
    pipelineVersion: 'v3.1.8',
    createdAt: new Date(Date.now() - 8 * 86400000).toISOString(),
    tags: ['welfare', 'temperature-drift'],
  },
  {
    id: 'DS-NEURO-006',
    pid: 'urn:os:dataset:neuro-006',
    name: 'Pipeline Upgrade Cohort',
    description: 'Pipeline version change flagged for review.',
    species: 'Mouse',
    strain: 'C57BL/6J',
    sex: 'female',
    ageWeeks: 12,
    sanitaryStatus: 'Specific pathogen free',
    facility: 'North Campus Vivarium',
    room: 'Room 101',
    environment: '12/12 light cycle',
    beddingBatch: 'BED-AX1',
    beddingMaterial: 'Aspen',
    diet: 'Standard chow',
    device: 'DVC-4K',
    pipelineVersion: 'v3.3.0',
    createdAt: new Date(Date.now() - 7 * 86400000).toISOString(),
    tags: ['pipeline-change'],
  },
]

const scenarioMap: Record<
  string,
  {
    name: string
    shift?: number
    missing?: { startHours: number; lengthHours: number }
    tempDrift?: number
    beddingChangeAt?: number
    pipelineVersion?: string
  }
> = {
  'CG-RK-101-A-01': { name: 'Normal circadian rhythm + noise' },
  'CG-RK-101-B-03': { name: 'Missing-data chunks', missing: { startHours: 52, lengthHours: 10 } },
  'CG-RK-302-A-04': { name: 'Bedding batch change -> activity shift', shift: -10, beddingChangeAt: 48 },
  'CG-RK-301-B-02': { name: 'Temp drift -> activity drop + welfare alarm', shift: -12, tempDrift: 0.04 },
  'CG-RK-101-C-02': { name: 'Pipeline version change -> warning', shift: -4, pipelineVersion: 'v3.3.0' },
}

const datasetScenarioMap: Record<string, string> = {
  'DS-NEURO-001': 'CG-RK-101-A-01',
  'DS-NEURO-002': 'CG-RK-101-A-01',
  'DS-NEURO-003': 'CG-RK-101-B-03',
  'DS-NEURO-004': 'CG-RK-302-A-04',
  'DS-NEURO-005': 'CG-RK-301-B-02',
  'DS-NEURO-006': 'CG-RK-101-C-02',
}

const buildRange = (from?: string, to?: string) => {
  const end = to ? new Date(to) : new Date()
  const start = from ? new Date(from) : new Date(end.getTime() - 7 * 24 * HOUR)
  return { start, end }
}

const buildSeries = (
  cageId: string,
  from?: string,
  to?: string,
  intervalMinutes = 15
) => {
  const { start, end } = buildRange(from, to)
  const scenario = scenarioMap[cageId] ?? { name: 'Normal circadian rhythm + noise' }
  const rand = seeded(seedFromString(cageId))
  const total = Math.max(1, Math.floor((end.getTime() - start.getTime()) / (intervalMinutes * MIN)))
  const ts: string[] = []
  const activity: number[] = []

  for (let i = 0; i <= total; i += 1) {
    const timestamp = new Date(start.getTime() + i * intervalMinutes * MIN)
    const hour = timestamp.getHours() + timestamp.getMinutes() / 60

    // Nocturnal mice: higher activity in dark (19:00 - 07:00)
    // Dark cycle peak at ~01:00
    const circadian = Math.cos((Math.PI * 2 * (hour - 1)) / 24)
    const noise = (rand() - 0.5) * 10
    const shift = scenario.shift && scenario.beddingChangeAt && i > scenario.beddingChangeAt * (60 / intervalMinutes) ? scenario.shift : 0

    // Base activation: 40-80 range
    const value = Math.max(2, 50 + circadian * 35 + noise + shift)
    ts.push(timestamp.toISOString())
    activity.push(Number(value.toFixed(2)))
  }

  let missingRanges: Array<{ start: string; end: string }> = []
  if (scenario.missing) {
    const startIndex = Math.floor(scenario.missing.startHours * (60 / intervalMinutes))
    const length = Math.floor(scenario.missing.lengthHours * (60 / intervalMinutes))
    const endIndex = Math.min(ts.length - 1, startIndex + length)
    if (ts[startIndex] && ts[endIndex]) {
      missingRanges = [{ start: ts[startIndex], end: ts[endIndex] }]
      const filteredTs: string[] = []
      const filteredActivity: number[] = []
      ts.forEach((timestamp, index) => {
        if (index < startIndex || index > endIndex) {
          filteredTs.push(timestamp)
          filteredActivity.push(activity[index])
        }
      })
      return { ts: filteredTs, activity: filteredActivity, missingRanges, scenario: scenario.name }
    }
  }

  return { ts, activity, missingRanges, scenario: scenario.name }
}

const buildEnvironment = (
  cageId: string,
  from?: string,
  to?: string,
  intervalMinutes = 15
): EnvironmentRecord => {
  const { start, end } = buildRange(from, to)
  const scenario = scenarioMap[cageId] ?? { name: 'Normal circadian rhythm + noise' }
  const rand = seeded(seedFromString(`${cageId}-env`))
  const total = Math.max(1, Math.floor((end.getTime() - start.getTime()) / (intervalMinutes * MIN)))

  const ts: string[] = []
  const temp: number[] = []
  const humidity: number[] = []
  const light: number[] = []
  const noise: number[] = []
  const co2: number[] = []
  const ammonia: number[] = []

  for (let i = 0; i <= total; i += 1) {
    const timestamp = new Date(start.getTime() + i * intervalMinutes * MIN)
    const hour = timestamp.getHours() + timestamp.getMinutes() / 60
    const circadian = Math.sin((Math.PI * 2 * hour) / 24)
    const tempDrift = scenario.tempDrift ? scenario.tempDrift * i : 0
    const tempValue = 22.2 + circadian * 1.8 + (rand() - 0.5) * 0.6 + tempDrift
    const humidityValue = 48 + circadian * 6 + (rand() - 0.5) * 2
    const lightValue = hour >= 7 && hour <= 19 ? 120 + (rand() - 0.5) * 10 : 12 + (rand() - 0.5) * 4
    const noiseValue = 38 + circadian * 4 + (rand() - 0.5) * 6
    const co2Value = 480 + (rand() - 0.5) * 30
    const ammoniaValue = 2 + (rand() - 0.5) * 0.5 + (scenario.shift ? 0.4 : 0)

    ts.push(timestamp.toISOString())
    temp.push(Number(tempValue.toFixed(2)))
    humidity.push(Number(humidityValue.toFixed(2)))
    light.push(Number(lightValue.toFixed(2)))
    noise.push(Number(noiseValue.toFixed(2)))
    co2.push(Number(co2Value.toFixed(2)))
    ammonia.push(Number(ammoniaValue.toFixed(2)))
  }

  return {
    scopeType: 'cage',
    scopeId: cageId,
    pid: `urn:os:dvc:environment:${cageId}`,
    ts,
    temp,
    humidity,
    light,
    noise,
    co2,
    ammonia,
  }
}

const buildEvents = (cageId: string): CageEvent[] => {
  const scenario = scenarioMap[cageId] ?? { name: 'Normal circadian rhythm + noise' }
  const base = new Date(Date.now() - 6 * 24 * HOUR)
  const events: CageEvent[] = [
    {
      id: `EV-${cageId}-clean`,
      cageId,
      ts: new Date(base.getTime() + 12 * HOUR).toISOString(),
      type: 'cleaning',
      payload: { notes: 'Routine sanitation completed.' },
    },
    {
      id: `EV-${cageId}-enrich`,
      cageId,
      ts: new Date(base.getTime() + 30 * HOUR).toISOString(),
      type: 'enrichment_added',
      payload: { notes: 'Added nesting pad and tunnel.' },
    },
  ]

  if (scenario.beddingChangeAt) {
    events.push({
      id: `EV-${cageId}-bedding`,
      cageId,
      ts: new Date(base.getTime() + scenario.beddingChangeAt * HOUR).toISOString(),
      type: 'bedding_change',
      payload: { beddingBatch: 'BED-BX9', notes: 'Swapped to paper bedding.' },
    })
  }

  if (scenario.tempDrift) {
    events.push({
      id: `EV-${cageId}-move`,
      cageId,
      ts: new Date(base.getTime() + 68 * HOUR).toISOString(),
      type: 'move',
      payload: { fromRoom: 'Room 301', toRoom: 'Room 302', notes: 'Moved for stabilization.' },
    })
  }

  if (scenario.pipelineVersion) {
    events.push({
      id: `EV-${cageId}-feed`,
      cageId,
      ts: new Date(base.getTime() + 54 * HOUR).toISOString(),
      type: 'feed_change',
      payload: { notes: 'Feed batch updated after pipeline change.' },
    })
  }

  return events
}

const buildQualitySignals = (cageId: string, missingRanges: Array<{ start: string; end: string }>, totalPoints: number): QualitySignals => {
  const missingPoints = missingRanges.reduce((acc, range) => {
    if (!range.start || !range.end) return acc
    const span = new Date(range.end).getTime() - new Date(range.start).getTime()
    return acc + Math.max(1, Math.round(span / (15 * MIN)))
  }, 0)
  const missingDataPct = totalPoints ? Math.min(100, Math.round((missingPoints / totalPoints) * 100)) : 0
  return {
    cageId,
    pid: `urn:os:dvc:quality:${cageId}`,
    missingDataPct,
    lastSeen: new Date().toISOString(),
    sensorHealth: missingDataPct > 18 ? 'warn' : 'good',
    pipelineVersion: scenarioMap[cageId]?.pipelineVersion ?? 'v3.2.1',
  }
}

const buildContextSnapshot = (cageId: string, at?: string): CageContextSnapshot => {
  const scenario = scenarioMap[cageId] ?? { name: 'Normal circadian rhythm + noise' }
  const beddingChangeAt = scenario.beddingChangeAt
    ? new Date(Date.now() - 6 * 24 * HOUR + scenario.beddingChangeAt * HOUR).getTime()
    : null
  const atTime = at ? new Date(at).getTime() : Date.now()
  const beddingMaterial = beddingChangeAt && atTime >= beddingChangeAt ? 'Paper' : 'Aspen'
  const beddingBatch = beddingMaterial === 'Paper' ? 'BED-BX9' : 'BED-AX1'

  return {
    cageId,
    pid: `urn:os:dvc:context:${cageId}`,
    room: 'Room 101',
    rack: 'Rack B',
    cageType: 'IVC',
    facility: 'North Campus Vivarium',
    beddingBatch,
    beddingMaterial,
    sanitaryStatus: 'Specific pathogen free',
    diet: 'Standard chow',
    water: 'Reverse osmosis',
    device: 'DVC-4K',
    firmware: 'fw-2.4.3',
    pipelineVersion: scenario.pipelineVersion ?? 'v3.2.1',
    lastChangeout: new Date(Date.now() - 3 * 24 * HOUR).toISOString(),
  }
}

const DEFAULT_THRESHOLDS = {
  activityDropPct: 18,
  missingDataPct: 12,
  tempRange: { min: 20, max: 26 },
  humidityRange: { min: 40, max: 60 },
}

export const buildActivitySeries = (
  cageId: string,
  from?: string,
  to?: string,
  intervalMinutes = 15
) => {
  const { ts, activity } = buildSeries(cageId, from, to, intervalMinutes)
  return {
    cageId,
    pid: `urn:os:dvc:activity:${cageId}`,
    ts,
    activity,
  } as DvcActivitySeries
}

export const buildDatasetActivitySeries = (
  datasetId: string,
  from?: string,
  to?: string,
  intervalMinutes = 15
): NamActivitySeries => {
  const cageId = datasetScenarioMap[datasetId] ?? 'CG-RK-101-A-01'
  const { ts, activity } = buildSeries(cageId, from, to, intervalMinutes)
  return {
    datasetId,
    pid: `urn:os:dvc:activity:${datasetId}` as Pid,
    ts,
    activity,
  }
}

export const buildEnvironmentSeries = (
  cageId: string,
  from?: string,
  to?: string,
  intervalMinutes = 15
) => buildEnvironment(cageId, from, to, intervalMinutes)

export const buildScenarioBundle = (
  cageId: string,
  from?: string,
  to?: string,
  intervalMinutes = 15
) => {
  const activity = buildSeries(cageId, from, to, intervalMinutes)
  const environment = buildEnvironment(cageId, from, to, intervalMinutes)
  const events = buildEvents(cageId)
  const quality = buildQualitySignals(cageId, activity.missingRanges, activity.ts.length)
  const report = computeAnomalies(
    {
      cageId,
      pid: `urn:os:dvc:activity:${cageId}`,
      ts: activity.ts,
      activity: activity.activity,
    },
    environment,
    quality,
    DEFAULT_THRESHOLDS
  )
  const ai = toBehaviorOutput(cageId, activity.ts[0] ?? new Date().toISOString(), activity.ts[activity.ts.length - 1] ?? new Date().toISOString(), report)

  return {
    activity: {
      cageId,
      pid: `urn:os:dvc:activity:${cageId}`,
      ts: activity.ts,
      activity: activity.activity,
    } as DvcActivitySeries,
    environment,
    events,
    quality,
    ai,
    missingRanges: activity.missingRanges,
    scenario: activity.scenario,
  }
}

export const buildWelfareAlarms = (cageId: string): WelfareAlarm[] => {
  if (!scenarioMap[cageId]?.tempDrift) return []
  const start = new Date(Date.now() - 2 * 24 * HOUR)
  const end = new Date(start.getTime() + 8 * HOUR)
  return [
    {
      id: `WF-${cageId}-temp`,
      cageId,
      startTs: start.toISOString(),
      endTs: end.toISOString(),
      severity: 'critical',
      status: 'open',
      reasonCode: 'TEMP_DRIFT',
      explanation: 'Ambient temperature exceeded threshold for 8 hours.',
      actions: ['Inspect hydration', 'Increase monitoring', 'Escalate to vet if persists'],
    },
  ]
}

export const buildContextAt = (cageId: string, at?: string) => buildContextSnapshot(cageId, at)

export const buildAiOutput = (
  cageId: string,
  from?: string,
  to?: string,
  intervalMinutes = 15
): AIBehaviorOutput => {
  const bundle = buildScenarioBundle(cageId, from, to, intervalMinutes)
  return bundle.ai
}

export const buildQuality = (cageId: string, from?: string, to?: string, intervalMinutes = 15) => {
  const activity = buildSeries(cageId, from, to, intervalMinutes)
  return buildQualitySignals(cageId, activity.missingRanges, activity.ts.length)
}

export const buildVcgSelection = (targetDatasetId: string, mode: VcgMatchMode) =>
  buildSelection(datasets, targetDatasetId, mode)
