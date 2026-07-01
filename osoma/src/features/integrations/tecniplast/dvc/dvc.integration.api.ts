import { apiFetch } from '@/lib/api.ts'
import type {
    DvcMetric,
    DvcGlobResult,
    DvcGlobSearchResponse,
    DvcTaskSubmission,
    DvcTaskSubmissionResponse,
    DvcTaskState,
    DvcTestApiKeyResponse,
} from '@/domain/dvc/dvc.types.ts'
import {
    dvcGlobResultsSchema,
    dvcGlobSearchResponseSchema,
    dvcGlobSearchSchema,
    dvcMetricsSchema,
    dvcTaskStateResponseSchema,
    dvcTaskSubmissionFailureSchema,
    dvcTaskSubmissionSchema,
    dvcTaskSubmissionResponseSchema,
    dvcTestApiKeyResponseSchema,
    parseDvcContract,
    validateDvcRequest,
} from '@/domain/dvc/dvc.contract.ts'

// Re-export the types that consumers of this API module access via it,
// so their imports don't need updating.
export type {
    DvcMetric as Metric,
    DvcGlobResult as GlobResult,
    DvcGlobSearchResponse as GlobSearchResponse,
    DvcTaskSubmission as TaskSubmission,
    DvcTaskSubmissionResponse as TaskSubmissionResponse,
    DvcTaskState as TaskState,
}

// Enum-like const objects so that consumers using value access
// (e.g. TaskAggregationType.HOUR) continue to work without changes.
export const TaskAggregationType = {
    HOUR: 'HOUR',
    MINUTE: 'MINUTE',
} as const
export type TaskAggregationType = typeof TaskAggregationType[keyof typeof TaskAggregationType]

export const TaskResourceType = {
    CAGE: 'CAGE',
    ANIMAL: 'ANIMAL',
} as const
export type TaskResourceType = typeof TaskResourceType[keyof typeof TaskResourceType]

export const TaskPartitionType = {
    SINGLE_FILE: 'SINGLE_FILE',
    SINGLE_FILE_BY_DAY: 'SINGLE_FILE_BY_DAY',
    SINGLE_FILE_BY_CAGE: 'SINGLE_FILE_BY_CAGE',
} as const
export type TaskPartitionType = typeof TaskPartitionType[keyof typeof TaskPartitionType]

export const TaskStateEnum = {
    SUBMITTED: 'SUBMITTED',
    RUNNING: 'RUNNING',
    COMPLETED: 'COMPLETED',
    FAILED: 'FAILED',
} as const

export type GlobSearch = { patterns: string[] }
export type { DvcTestApiKeyResponse }

// --- API Client ---

export const testApiKey = async (appId: string) => {
    const response = await apiFetch<unknown>(`/connected_apps/${appId}/dvc/test-api-key`, {
        method: 'POST',
    })

    return parseDvcContract(dvcTestApiKeyResponseSchema, response, 'DVC test-api-key')
}

export const getMetrics = async (appId: string) => {
    const response = await apiFetch<unknown>(`/connected_apps/${appId}/dvc/metrics`, {
        method: 'GET',
    })

    return parseDvcContract(dvcMetricsSchema, response, 'DVC metrics')
}

export const searchCages = async (appId: string, payload: GlobSearch) => {
    const requestPayload = validateDvcRequest(dvcGlobSearchSchema, payload, 'DVC cages search payload')
    const response = await apiFetch<unknown>(`/connected_apps/${appId}/dvc/cages/search`, {
        method: 'POST',
        body: JSON.stringify(requestPayload),
    })

    return parseDvcContract(dvcGlobSearchResponseSchema, response, 'DVC cages search')
}

export const searchAnimals = async (appId: string, payload: GlobSearch) => {
    const requestPayload = validateDvcRequest(dvcGlobSearchSchema, payload, 'DVC animals search payload')
    const response = await apiFetch<unknown>(`/connected_apps/${appId}/dvc/animals/search`, {
        method: 'POST',
        body: JSON.stringify(requestPayload),
    })

    return parseDvcContract(dvcGlobResultsSchema, response, 'DVC animals search')
}

export const submitTask = async (appId: string, payload: DvcTaskSubmission) => {
    const requestPayload = validateDvcRequest(dvcTaskSubmissionSchema, payload, 'DVC task submission payload')
    const response = await apiFetch<unknown>(`/connected_apps/${appId}/dvc/submit`, {
        method: 'POST',
        body: JSON.stringify(requestPayload),
    })

    const success = dvcTaskSubmissionResponseSchema.safeParse(response)
    if (success.success) {
        return success.data
    }

    const failure = dvcTaskSubmissionFailureSchema.safeParse(response)
    if (failure.success) {
        throw new Error(failure.data.detail || failure.data.message || 'Tecniplast did not return a valid task id.')
    }

    return parseDvcContract(dvcTaskSubmissionResponseSchema, response, 'DVC task submission')
}

export const getTaskState = async (appId: string, taskId: number) => {
    const payload = await apiFetch<unknown>(`/connected_apps/${appId}/dvc/${taskId}/state`, {
        method: 'GET',
    })

    const normalized = parseDvcContract(dvcTaskStateResponseSchema, payload, 'DVC task state')
    return Array.isArray(normalized) ? normalized : [normalized]
}

export const downloadTaskResult = (appId: string, taskId: number) =>
    apiFetch<Blob>(`/connected_apps/${appId}/dvc/${taskId}/download`, {
        method: 'GET',
        responseType: 'blob',
    })
