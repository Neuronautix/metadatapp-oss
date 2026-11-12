import { apiFetch } from '@/lib/api.ts'
import type {
  AIBehaviorOutput,
  CageContextSnapshot,
  CageEvent,
  Dataset,
  DvcActivitySeries,
  EnvironmentRecord,
  QualitySignals,
  VcgSelection,
  WelfareAlarm,
  DvcMetric,
  DvcGlobResult,
  DvcTaskSubmission,
  DvcTaskSubmissionResponse,
  DvcTaskState,
} from '@/domain/dvc/dvc.types.ts'
import type { VcgMatchMode } from '@/domain/dvc/dvc.enums.ts'
import type { NamActivitySeries } from '@/domain/nam/vcg.combine.ts'

const buildQuery = (params: Record<string, string | number | undefined>) => {
  const search = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === '') return
    search.set(key, String(value))
  })
  const query = search.toString()
  return query ? `?${query}` : ''
}

export const getDvcActivity = (
  cageId: string,
  params?: { from?: string; to?: string; interval?: number }
) => apiFetch<DvcActivitySeries>(`/dvc/cages/${cageId}/activity${buildQuery(params ?? {})}`)

export const getDvcEnvironment = (
  cageId: string,
  params?: { from?: string; to?: string; interval?: number }
) => apiFetch<EnvironmentRecord>(`/dvc/cages/${cageId}/environment${buildQuery(params ?? {})}`)

export const getDvcEvents = (cageId: string, params?: { from?: string; to?: string }) =>
  apiFetch<{ data: CageEvent[] }>(`/dvc/cages/${cageId}/events${buildQuery(params ?? {})}`)

export const getDvcWelfareAlarms = (cageId: string, params?: { from?: string; to?: string }) =>
  apiFetch<{ data: WelfareAlarm[] }>(`/dvc/cages/${cageId}/welfare-alarms${buildQuery(params ?? {})}`)

export const getDvcAiOutput = (cageId: string, params?: { from?: string; to?: string; interval?: number }) =>
  apiFetch<AIBehaviorOutput>(`/dvc/cages/${cageId}/ai${buildQuery(params ?? {})}`)

export const getDvcContext = (cageId: string, at?: string) =>
  apiFetch<CageContextSnapshot>(`/dvc/cages/${cageId}/context-snapshot${buildQuery({ at })}`)

export const getDvcContextSnapshot = (cageId: string, at?: string) => getDvcContext(cageId, at)

export const getDvcCompare = (params: { cageIds: string[]; from?: string; to?: string; interval?: number }) =>
  apiFetch<{ data: DvcActivitySeries[]; context: CageContextSnapshot[]; quality?: QualitySignals[] }>(
    `/dvc/compare${buildQuery({ cageIds: params.cageIds.join(','), from: params.from, to: params.to, interval: params.interval })}`
  )

export const getNamDatasets = () => apiFetch<{ data: Dataset[] }>('/nam/datasets')

export const generateVcg = (payload: { targetDatasetId: string; mode: VcgMatchMode }) =>
  apiFetch<VcgSelection>('/nam/vcg/generate', {
    method: 'POST',
    body: JSON.stringify(payload),
  })

export const getVcgSelection = (id: string) => apiFetch<VcgSelection>(`/nam/vcg/${id}`)

export const getNamActivity = (params: { datasetIds: string[]; from?: string; to?: string; interval?: number }) =>
  apiFetch<{ data: NamActivitySeries[] }>(
    `/nam/activity${buildQuery({
      datasetIds: params.datasetIds.join(','),
      from: params.from,
      to: params.to,
      interval: params.interval,
    })}`
  )

// DVC Analytics Integration API
export const testDvcApiKey = () =>
  apiFetch<string>('/tasks/api/1/integration/test-api-key/', { method: 'POST' })

export const getDvcMetrics = () =>
  apiFetch<DvcMetric[]>('/tasks/api/1/integration/metrics/')

export const searchDvcCages = (patterns: string[]) =>
  apiFetch<DvcGlobResult[]>('/tasks/api/1/integration/cages/search', {
    method: 'POST',
    body: JSON.stringify({ patterns }),
  })

export const searchDvcAnimals = (patterns: string[]) =>
  apiFetch<DvcGlobResult[]>('/tasks/api/1/integration/animals/search', {
    method: 'POST',
    body: JSON.stringify({ patterns }),
  })

export const submitDvcTask = (payload: DvcTaskSubmission) =>
  apiFetch<DvcTaskSubmissionResponse>('/tasks/api/1/integration/submit/', {
    method: 'POST',
    body: JSON.stringify(payload),
  })

export const getDvcTaskState = (taskId: number) =>
  apiFetch<DvcTaskState[]>(`/tasks/api/1/integration/${taskId}/state`)

export const downloadDvcTaskResult = (taskId: number) =>
  apiFetch<Blob>(`/tasks/api/1/integration/${taskId}/download`)
