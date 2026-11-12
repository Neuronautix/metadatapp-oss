import type { ZefishCryoRecord } from '@/domain/zefish/zefish.types.ts'
import { zefishLines } from '@/mocks/zefish/lines.ts'

const dayMs = 24 * 60 * 60 * 1000
const now = Date.now()

const isoDaysAgo = (days: number) => new Date(now - days * dayMs).toISOString()

const storageLocations = [
    'Cryo A1 / Rack 1 / Box 02',
    'Cryo A1 / Rack 2 / Box 05',
    'Cryo B2 / Rack 1 / Box 08',
    'Cryo B2 / Rack 3 / Box 01',
    'Cryo C1 / Rack 2 / Box 11',
]

const protocols = [
    'Controlled-rate freeze v1',
    'LN2 snap freeze v2',
    'Cryo archival standard v1',
    'Backup freeze protocol v3',
]

const statuses: ZefishCryoRecord['status'][] = ['stored', 'stored', 'used', 'discarded']

export const zefishCryoRecords: ZefishCryoRecord[] = zefishLines.flatMap((line, index) => {
    const baseOffset = 18 + index * 3

    return [
        {
            cryoRecordID: `${line.lineID}-CRYO-001`,
            lineID: line.lineID,
            storageDate: isoDaysAgo(baseOffset),
            storageLocation: storageLocations[index % storageLocations.length],
            protocolUsed: protocols[index % protocols.length],
            status: statuses[index % statuses.length],
            notes: `Primary conservation set for ${line.name}.`,
            createdAt: isoDaysAgo(baseOffset - 1),
        },
        {
            cryoRecordID: `${line.lineID}-CRYO-002`,
            lineID: line.lineID,
            storageDate: isoDaysAgo(baseOffset + 12),
            storageLocation: storageLocations[(index + 2) % storageLocations.length],
            protocolUsed: protocols[(index + 1) % protocols.length],
            status: index % 5 === 0 ? 'discarded' : 'stored',
            notes: index % 4 === 0 ? 'Replaced by newer duplicate vial set.' : 'Secondary archive vial batch.',
            createdAt: isoDaysAgo(baseOffset + 11),
        },
    ]
})
