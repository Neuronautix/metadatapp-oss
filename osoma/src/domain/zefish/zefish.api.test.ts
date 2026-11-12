import { beforeEach, describe, expect, it, vi } from 'vitest'

const realSession = {
    accessToken: 'real-access-token',
    idToken: 'real-id-token',
    refreshToken: 'real-refresh-token',
    expiresAt: Math.floor(Date.now() / 1000) + 3600,
    user: { sub: 'user-1' },
}

describe('zefish cryo api integration', () => {
    beforeEach(() => {
        localStorage.setItem('use_msw', 'false')
        localStorage.setItem('metadatapp_auth_session', JSON.stringify(realSession))
        vi.resetModules()
        vi.unstubAllGlobals()
    })

    it('maps hydra cryo records from the real API', async () => {
        const fetchSpy = vi.fn().mockResolvedValue(new Response(JSON.stringify({
            'hydra:member': [
                {
                    '@id': '/cryo_records/abc-123',
                    line: { id: 'line-123' },
                    storageDate: '2026-03-18',
                    storageLocation: 'Freezer A / Box 4 / Rack 2',
                    protocolUsed: 'Slow freeze v1',
                    status: { value: 'used' },
                    notes: 'Reserve stock consumed for recovery.',
                },
            ],
        }), {
            status: 200,
            headers: { 'Content-Type': 'application/ld+json' },
        }))

        vi.stubGlobal('fetch', fetchSpy)

        const { getZefishCryoRecords } = await import('./zefish.api.ts')
        const records = await getZefishCryoRecords('line-123')
        const [, init] = fetchSpy.mock.calls[0]

        expect(fetchSpy).toHaveBeenCalledWith(
            '/api/cryo_records?line.id=line-123',
            expect.any(Object)
        )
        expect(init.headers.Authorization).toBe('Bearer real-access-token')
        expect(records).toEqual([
            {
                cryoRecordID: 'abc-123',
                lineID: 'line-123',
                storageDate: '2026-03-18',
                storageLocation: 'Freezer A / Box 4 / Rack 2',
                protocolUsed: 'Slow freeze v1',
                status: 'used',
                notes: 'Reserve stock consumed for recovery.',
            },
        ])
    })

    it('maps hydra cryo records when line is an IRI string', async () => {
        const fetchSpy = vi.fn().mockResolvedValue(new Response(JSON.stringify({
            'hydra:member': [
                {
                    '@id': '/cryo_records/iri-123',
                    line: '/zebrafish_lines/line-iri-123',
                    storageDate: '2026-03-19',
                    storageLocation: 'Freezer B / Box 1 / Rack 7',
                    protocolUsed: 'Slow freeze v2',
                    status: { value: 'stored' },
                    notes: 'Primary stock.',
                },
            ],
        }), {
            status: 200,
            headers: { 'Content-Type': 'application/ld+json' },
        }))

        vi.stubGlobal('fetch', fetchSpy)

        const { getZefishCryoRecords } = await import('./zefish.api.ts')
        const records = await getZefishCryoRecords('line-iri-123')

        expect(fetchSpy).toHaveBeenCalledWith(
            '/api/cryo_records?line.id=line-iri-123',
            expect.any(Object)
        )
        expect(records).toEqual([
            {
                cryoRecordID: 'iri-123',
                lineID: 'line-iri-123',
                storageDate: '2026-03-19',
                storageLocation: 'Freezer B / Box 1 / Rack 7',
                protocolUsed: 'Slow freeze v2',
                status: 'stored',
                notes: 'Primary stock.',
            },
        ])
    })

    it('maps hydra cryo records with a deterministic fallback ID when identifiers are missing', async () => {
        const fetchSpy = vi.fn().mockResolvedValue(new Response(JSON.stringify({
            'hydra:member': [
                {
                    line: '/zebrafish_lines/line-fallback-123',
                    storageDate: '2026-03-20',
                    storageLocation: 'Freezer C / Box 9 / Rack 3',
                    protocolUsed: 'Vitrification',
                    status: { value: 'discarded' },
                    notes: 'Fallback identity path.',
                },
            ],
        }), {
            status: 200,
            headers: { 'Content-Type': 'application/ld+json' },
        }))

        vi.stubGlobal('fetch', fetchSpy)

        const { getZefishCryoRecords } = await import('./zefish.api.ts')
        const records = await getZefishCryoRecords('line-fallback-123')

        expect(fetchSpy).toHaveBeenCalledWith(
            '/api/cryo_records?line.id=line-fallback-123',
            expect.any(Object)
        )
        expect(records).toEqual([
            {
                cryoRecordID:
                    'lineID=line-fallback-123&storageDate=2026-03-20&storageLocation=Freezer+C+%2F+Box+9+%2F+Rack+3&protocolUsed=Vitrification&status=discarded&notes=Fallback+identity+path.',
                lineID: 'line-fallback-123',
                storageDate: '2026-03-20',
                storageLocation: 'Freezer C / Box 9 / Rack 3',
                protocolUsed: 'Vitrification',
                status: 'discarded',
                notes: 'Fallback identity path.',
            },
        ])
    })

    it('posts cryo records using the backend enum IRI', async () => {
        const fetchSpy = vi.fn().mockResolvedValue(new Response('', { status: 201 }))

        vi.stubGlobal('fetch', fetchSpy)

        const { createZefishCryoRecord } = await import('./zefish.api.ts')
        await createZefishCryoRecord({
            lineID: 'line-123',
            storageDate: '2026-03-18',
            storageLocation: 'Freezer A / Box 4 / Rack 2',
            protocolUsed: 'Slow freeze v1',
            status: 'discarded',
            notes: 'Superseded by a fresh vial set.',
        })

        const [, init] = fetchSpy.mock.calls[0]
        const body = JSON.parse(String(init.body))

        expect(fetchSpy).toHaveBeenCalledWith(
            '/api/zefix/lines/line-123/cryo-records',
            expect.any(Object)
        )
        expect(init.method).toBe('POST')
        expect(body).toEqual({
            storageDate: '2026-03-18',
            storageLocation: 'Freezer A / Box 4 / Rack 2',
            protocolUsed: 'Slow freeze v1',
            status: '/cryo_record_statuses/discarded',
            notes: 'Superseded by a fresh vial set.',
        })
    })
})
