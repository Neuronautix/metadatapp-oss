import type {
    BatchEvent,
    BatchMortalityEvent,
    BatchSexRatio,
    ZefishBatch,
} from '@/domain/zefish/zefish.types.ts'
import { zefishLines } from '@/mocks/zefish/lines.ts'
import { zefishLocations } from '@/mocks/zefish/systems.ts'

const dayMs = 24 * 60 * 60 * 1000
const now = Date.now()

const isoDaysAgo = (days: number) => new Date(now - days * dayMs).toISOString()

const createSexRatio = (total: number, seed: number): BatchSexRatio => {
    const male = Math.floor(total * (0.41 + (seed % 6) * 0.02))
    const female = Math.floor(total * (0.43 + (seed % 4) * 0.015))
    const unknown = Math.max(0, total - male - female)

    return {
        male,
        female,
        unknown,
    }
}

const createMortalityEvent = (
    batchID: string,
    index: number,
    daysAgo: number,
    number: number,
    reason: BatchMortalityEvent['reason']
): BatchMortalityEvent => ({
    id: `${batchID}-M-${index}`,
    type: 'mortality',
    date: isoDaysAgo(daysAgo),
    number,
    reason,
})

export const zefishBatches: ZefishBatch[] = Array.from({ length: 30 }).map((_, index) => {
    const batchNumber = index + 1
    const batchID = `BATCH-${String(batchNumber).padStart(3, '0')}`
    const line = zefishLines[index % zefishLines.length]

    const initialCount = 64 + (index % 7) * 10 + Math.floor(index / 3)
    const birthOffsetDays = 25 + index * 7
    const birthDate = isoDaysAgo(birthOffsetDays)

    const originLocation = zefishLocations[(index * 3) % zefishLocations.length]
    const transferTarget = zefishLocations[(index * 3 + 17) % zefishLocations.length]

    const events: BatchEvent[] = [
        {
            id: `${batchID}-B-1`,
            type: 'birth',
            date: birthDate,
            count: initialCount,
        },
    ]

    if (index % 3 === 0) {
        events.push({
            id: `${batchID}-T-1`,
            type: 'transfer',
            date: isoDaysAgo(Math.max(3, birthOffsetDays - 12)),
            count: Math.floor(initialCount * 0.9),
            fromLocationId: originLocation.locationId,
            toLocationId: transferTarget.locationId,
        })
    }

    const firstMortality = 1 + (index % 4)
    const secondMortality = index % 5 === 0 ? 3 + (index % 3) : 1

    events.push(
        createMortalityEvent(batchID, 1, Math.max(2, birthOffsetDays - 8), firstMortality, 'natural')
    )
    events.push(
        createMortalityEvent(
            batchID,
            2,
            Math.max(1, birthOffsetDays - 2),
            secondMortality,
            index % 5 === 0 ? 'unknown' : 'natural'
        )
    )

    // Scenario: high mortality spike
    if (batchID === 'BATCH-018') {
        events.push(createMortalityEvent(batchID, 3, 1, 18, 'disease'))
    }

    // Scenario: sudden population drop from system issue
    if (batchID === 'BATCH-007') {
        events.push(createMortalityEvent(batchID, 3, 0, 14, 'system issue'))
    }

    const totalMortality = events
        .filter((event): event is BatchMortalityEvent => event.type === 'mortality')
        .reduce((sum, event) => sum + event.number, 0)

    const location = index % 3 === 0 ? transferTarget : originLocation

    return {
        batchID,
        lineID: line.lineID,
        birthDate,
        sexRatio: createSexRatio(initialCount - totalMortality, index),
        initialCount,
        individualCount: Math.max(0, initialCount - totalMortality),
        locationId: location.locationId,
        systemID: location.systemID,
        roomID: location.roomID,
        events,
    }
})
