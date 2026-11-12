import { http, HttpResponse, delay } from 'msw'
import {
    type GlobResult,
    type Metric,
    type TaskState,
    type TaskSubmissionResponse,
    TaskStateEnum,
} from '@/features/integrations/tecniplast/dvc/dvc.integration.api.ts'

// --- Mock Data ---

const MOCK_METRICS: Metric[] = [
    { id: 'ACTIVATION', description: 'Electrodes average', type: 'METRIC' },
    { id: 'TEMPERATURE', description: 'Avg Temperature', type: 'ENV_PARAM' },
    { id: 'HUMIDITY', description: 'Avg Humidity', type: 'ENV_PARAM' },
    { id: 'LOCOMOTION', description: 'Animal movement', type: 'METRIC' },
]

const MOCK_CAGES: GlobResult[] = [
    {
        uuid: 'c1',
        humanReadableId: 'CAGE-001',
        status: 'RUNNING',
        lastEventDate: '2023-06-01T12:00:00Z',
        registrationEventDate: '2023-01-10T08:00:00Z',
        rfids: [],
    },
    {
        uuid: 'c2',
        humanReadableId: 'CAGE-002',
        status: 'OUT_OF_RACK',
        lastEventDate: '2023-05-15T10:00:00Z',
        registrationEventDate: '2023-01-11T09:00:00Z',
        rfids: [],
    },
    {
        uuid: 'c3',
        humanReadableId: 'CAGE-105',
        status: 'TERMINATED',
        lastEventDate: '2023-06-01T12:00:00Z',
        registrationEventDate: '2022-12-01T08:00:00Z',
        rfids: [],
    },
]

const MOCK_ANIMALS: GlobResult[] = [
    {
        uuid: 'a1',
        humanReadableId: 'ANIMAL-X1',
        status: 'RUNNING',
        lastEventDate: '2023-06-01T12:00:00Z',
        registrationEventDate: '2023-01-10T08:00:00Z',
        rfids: [{ rfid: 'RF-999-001', start: 1673337600, stop: 1673341200 }],
    },
    {
        uuid: 'a2',
        humanReadableId: 'ANIMAL-Y2',
        status: 'RUNNING',
        lastEventDate: '2023-06-01T12:00:00Z',
        registrationEventDate: '2023-01-15T08:00:00Z',
        rfids: [{ rfid: 'RF-888-002', start: 1673769600, stop: 1673773200 }],
    },
]

// In-memory store for tasks to simulate state changes
const tasksStore = new Map<number, TaskState>()
let nextTaskId = 100

// --- Handlers ---

// --- Resolvers ---

const testApiKeyResolver = async ({ request }: any) => {
    await delay(500)
    const apiKey = request.headers.get('x-api-key')
    if (apiKey === 'valid-key' || apiKey === 'demo') {
        return HttpResponse.json("f1234")
    }
    return new HttpResponse(null, { status: 401 })
}

const getMetricsResolver = async () => {
    await delay(300)
    return HttpResponse.json(MOCK_METRICS)
}

const searchCagesResolver = async ({ request }: any) => {
    await delay(600)
    const body = (await request.json()) as { patterns: string[] }
    const pattern = body.patterns[0] || ''
    if (pattern === '*') {
        return HttpResponse.json(MOCK_CAGES)
    }
    const filtered = MOCK_CAGES.filter((c) =>
        c.humanReadableId.includes(pattern.replace('*', ''))
    )
    return HttpResponse.json(filtered)
}

const searchAnimalsResolver = async ({ request }: any) => {
    await delay(600)
    const body = (await request.json()) as { patterns: string[] }
    const pattern = body.patterns[0] || ''
    if (pattern === '*') {
        return HttpResponse.json(MOCK_ANIMALS)
    }
    const filtered = MOCK_ANIMALS.filter((a) =>
        a.humanReadableId.includes(pattern.replace('*', ''))
    )
    return HttpResponse.json(filtered)
}

const submitTaskResolver = async () => {
    await delay(800)
    const id = nextTaskId++
    const newTask: TaskState = {
        id,
        state: TaskStateEnum.SUBMITTED,
        createdAt: new Date().toISOString(),
    }
    tasksStore.set(id, newTask)

    setTimeout(() => {
        const t = tasksStore.get(id)
        if (t) tasksStore.set(id, { ...t, state: TaskStateEnum.RUNNING })
    }, 5000)

    setTimeout(() => {
        const t = tasksStore.get(id)
        if (t) tasksStore.set(id, {
            ...t,
            state: TaskStateEnum.COMPLETED,
            finishedAt: new Date().toISOString(),
            fileSize: 1024 * 1024 * 2.5,
            checksum: 'sha1:mock-checksum',
        })
    }, 15000)

    const response: TaskSubmissionResponse = {
        taskId: id,
        requestId: `req-${Date.now()}`,
    }
    return HttpResponse.json(response)
}

const getTaskStateResolver = async ({ params }: any) => {
    const taskId = Number(params.taskId)
    const task = tasksStore.get(taskId)
    if (!task) {
        return new HttpResponse(null, { status: 404 })
    }
    return HttpResponse.json([task])
}

const downloadTaskResultResolver = async () => {
    await delay(1000)
    const fileContent = 'Mock file content for DVC Analytics Task'
    const blob = new Blob([fileContent], { type: 'application/octet-stream' })
    return new HttpResponse(blob, {
        headers: {
            'Content-Disposition': 'attachment; filename="data.csv"',
            'Content-Type': 'application/octet-stream',
        }
    })
}

// --- Handlers ---

export const dvcIntegrationHandlers = [
    http.post('/api/tasks/api/1/integration/test-api-key/', testApiKeyResolver),
    http.get('/api/tasks/api/1/integration/metrics/', getMetricsResolver),
    http.post('/api/tasks/api/1/integration/cages/search', searchCagesResolver),
    http.post('/api/tasks/api/1/integration/animals/search', searchAnimalsResolver),
    http.post('/api/tasks/api/1/integration/submit/', submitTaskResolver),
    http.get('/api/tasks/api/1/integration/:taskId/state', getTaskStateResolver),
    http.get('/api/tasks/api/1/integration/:taskId/download', downloadTaskResultResolver),

    // --- Modern Proxy Routes Mapping ---

    http.post('/api/connected_apps/:id/dvc/test-api-key', testApiKeyResolver),
    http.get('/api/connected_apps/:id/dvc/metrics', getMetricsResolver),
    http.post('/api/connected_apps/:id/dvc/cages/search', searchCagesResolver),
    http.post('/api/connected_apps/:id/dvc/animals/search', searchAnimalsResolver),
    http.post('/api/connected_apps/:id/dvc/submit', submitTaskResolver),
    http.get('/api/connected_apps/:id/dvc/:taskId/state', getTaskStateResolver),
    http.get('/api/connected_apps/:id/dvc/:taskId/download', downloadTaskResultResolver),
]
