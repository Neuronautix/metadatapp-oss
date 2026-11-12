import type { CircadianModelParams } from './circadianModel.ts'

export type AnimalMetadata = {
  animalId: string
  cageId: string
  species: string
  strain: string
  sex: 'male' | 'female'
  ageWeeks: number
  genotype: string
  supplier: string
  sanitaryStatus: string
  project: string
  protocol: string
  treatment: string
  dose: string
  administrationRoute: string
  facility: string
  room: string
  rack: string
  beddingType: string
  dietType: string
  waterSystem: string
  temperatureMean: number
  humidityMean: number
  lightIntensityMean?: number
  lightCycle: string
  noiseLevel: string
  noiseMean?: number
  humanMovementWorkday?: number
  dvcSystem: string
  firmwareVersion: string
  analyticsVersion: string
  sourceApp: string
  modalities?: string[]
}

export type AnimalRecord = {
  animalId: string
  cageId: string
  metadata: AnimalMetadata
  circadianParameters: CircadianModelParams
}

/** Weights for each metadata dimension used in distance calculation */
export const METADATA_WEIGHTS: Record<string, number> = {
  strain: 0.25,
  sex: 0.20,
  ageWeeks: 0.15,
  sanitaryStatus: 0.10,
  beddingType: 0.08,
  dietType: 0.07,
  treatment: 0.08,
  facility: 0.04,
  lightCycle: 0.03,
}

const MAX_AGE_DIFF_WEEKS = 52

/** Normalized distance between two values (0 = identical, 1 = maximally different) */
const categoricalDistance = (a: string, b: string): number => (a === b ? 0 : 1)

const numericDistance = (a: number, b: number, maxDiff: number): number =>
  Math.min(1, Math.abs(a - b) / maxDiff)

/** Compute a similarity score (0–100) between two animals */
export const computeSimilarityScore = (target: AnimalRecord, candidate: AnimalRecord): number => {
  const t = target.metadata
  const c = candidate.metadata

  const distances: Record<string, number> = {
    strain: categoricalDistance(t.strain, c.strain),
    sex: categoricalDistance(t.sex, c.sex),
    ageWeeks: numericDistance(t.ageWeeks, c.ageWeeks, MAX_AGE_DIFF_WEEKS),
    sanitaryStatus: categoricalDistance(t.sanitaryStatus, c.sanitaryStatus),
    beddingType: categoricalDistance(t.beddingType, c.beddingType),
    dietType: categoricalDistance(t.dietType, c.dietType),
    treatment: categoricalDistance(t.treatment, c.treatment),
    facility: categoricalDistance(t.facility, c.facility),
    lightCycle: categoricalDistance(t.lightCycle, c.lightCycle),
  }

  const weightedDistance = Object.entries(METADATA_WEIGHTS).reduce(
    (sum, [key, weight]) => sum + weight * (distances[key] ?? 0),
    0
  )

  return Math.round((1 - weightedDistance) * 100)
}

export type FilterCriteria = {
  strain?: string
  sex?: string
  ageMin?: number
  ageMax?: number
  tempMin?: number
  tempMax?: number
  humidityMin?: number
  humidityMax?: number
  lightMin?: number
  lightMax?: number
  noiseMin?: number
  noiseMax?: number
  humanMoveMin?: number
  humanMoveMax?: number
  amplitudeMin?: number
  amplitudeMax?: number
  phaseMin?: number
  phaseMax?: number
  project?: string
  treatment?: string
  sanitaryStatus?: string
  facility?: string
  lightCycle?: string
  dvcSystems?: string[]
}

/** Filter a list of animal records based on criteria */
export const filterAnimals = (animals: AnimalRecord[], criteria: FilterCriteria): AnimalRecord[] => {
  return animals.filter((animal) => {
    const m = animal.metadata
    if (criteria.strain && m.strain !== criteria.strain) return false
    if (criteria.sex && m.sex !== criteria.sex) return false
    if (criteria.ageMin !== undefined && m.ageWeeks < criteria.ageMin) return false
    if (criteria.ageMax !== undefined && m.ageWeeks > criteria.ageMax) return false
    if (criteria.tempMin !== undefined && m.temperatureMean < criteria.tempMin) return false
    if (criteria.tempMax !== undefined && m.temperatureMean > criteria.tempMax) return false
    if (criteria.humidityMin !== undefined && m.humidityMean < criteria.humidityMin) return false
    if (criteria.humidityMax !== undefined && m.humidityMean > criteria.humidityMax) return false
    if (criteria.lightMin !== undefined && (m.lightIntensityMean ?? 0) < criteria.lightMin) return false
    if (criteria.lightMax !== undefined && (m.lightIntensityMean ?? 0) > criteria.lightMax) return false
    if (criteria.noiseMin !== undefined && (m.noiseMean ?? 0) < criteria.noiseMin) return false
    if (criteria.noiseMax !== undefined && (m.noiseMean ?? 0) > criteria.noiseMax) return false
    if (criteria.humanMoveMin !== undefined && (m.humanMovementWorkday ?? 0) < criteria.humanMoveMin) return false
    if (criteria.humanMoveMax !== undefined && (m.humanMovementWorkday ?? 0) > criteria.humanMoveMax) return false
    if (criteria.amplitudeMin !== undefined && animal.circadianParameters.amplitude < criteria.amplitudeMin) return false
    if (criteria.amplitudeMax !== undefined && animal.circadianParameters.amplitude > criteria.amplitudeMax) return false
    if (criteria.phaseMin !== undefined && animal.circadianParameters.phase < criteria.phaseMin) return false
    if (criteria.phaseMax !== undefined && animal.circadianParameters.phase > criteria.phaseMax) return false
    if (criteria.project && m.project !== criteria.project) return false
    if (criteria.treatment && m.treatment !== criteria.treatment) return false
    if (criteria.sanitaryStatus && m.sanitaryStatus !== criteria.sanitaryStatus) return false
    if (criteria.facility && m.facility !== criteria.facility) return false
    if (criteria.lightCycle && m.lightCycle !== criteria.lightCycle) return false
    if (criteria.dvcSystems?.length && !criteria.dvcSystems.includes(m.dvcSystem)) return false
    return true
  })
}
