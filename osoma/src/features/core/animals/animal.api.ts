import { apiFetch } from '@/lib/api.ts'
import type {
  Animal,
  AnimalTimelineEvent,
  AnimalWeightEntry,
  AnimalWeightRange,
  AnimalsResponse,
  AnimalWeightsResponse,
  AnalysisResults,
  BehaviorSeries,
  BloodSample,
  SamplesResponse,
  TreatmentEvent,
  TreatmentsResponse,
} from './animal.types.ts'

export function getTicklabAnimals() {
  return apiFetch<AnimalsResponse>('/ticklab/animals')
}

export function getInvestigationAnimals(investigationId: string) {
  return apiFetch<AnimalsResponse>(`/experiments/${investigationId}/animals`)
}

export function getAnimal(animalId: string) {
  return apiFetch<Animal>(`/animals/${animalId}`)
}

export function updateAnimalNote(animalId: string, note: string) {
  return apiFetch<Animal>(`/animals/${animalId}/note`, {
    method: 'PATCH',
    body: JSON.stringify({ note }),
  })
}

export function updateAnimalFavorite(animalId: string, isFavorite: boolean) {
  return apiFetch<Animal>(`/animals/${animalId}/favorite`, {
    method: 'PATCH',
    body: JSON.stringify({ isFavorite }),
  })
}

export function getAnimalWeights(animalId: string) {
  return apiFetch<AnimalWeightsResponse>(`/animals/${animalId}/weights`)
}

export function createAnimalWeight(animalId: string, payload: Pick<AnimalWeightEntry, 'grams' | 'at' | 'source'>) {
  return apiFetch<AnimalWeightEntry>(`/animals/${animalId}/weights`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function getAnimalWeightRange(animalId: string) {
  return apiFetch<AnimalWeightRange>(`/animals/${animalId}/weight-range`)
}

export function getAnimalTreatments(animalId: string) {
  return apiFetch<TreatmentsResponse>(`/animals/${animalId}/treatments`)
}

export function createAnimalTreatment(
  animalId: string,
  payload: Omit<TreatmentEvent, 'id' | 'animalId'>
) {
  return apiFetch<TreatmentEvent>(`/animals/${animalId}/treatments`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function getAnimalSamples(animalId: string) {
  return apiFetch<SamplesResponse>(`/animals/${animalId}/samples`)
}

export function createAnimalSample(animalId: string, payload: Omit<BloodSample, 'id' | 'animalId' | 'sampleId'>) {
  return apiFetch<BloodSample>(`/animals/${animalId}/samples`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function getAnimalAnalysis(animalId: string) {
  return apiFetch<AnalysisResults>(`/animals/${animalId}/analysis`)
}

export function getAnimalBehavior(animalId: string) {
  return apiFetch<BehaviorSeries>(`/animals/${animalId}/behavior`)
}

export function getAnimalTimeline(animalId: string) {
  return apiFetch<{ data: AnimalTimelineEvent[] }>(`/animals/${animalId}/timeline`)
}
