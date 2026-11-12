import { useMemo, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'

type ApiKeyRecord = {
    id: string
    label: string
    scope: string
    createdAt: string
    lastUsedAt?: string
    tokenPreview: string
}

const initialKeys: ApiKeyRecord[] = [
    {
        id: 'key-1',
        label: 'Local ETL connector',
        scope: 'datasets:read samples:write',
        createdAt: '2026-02-12T08:00:00.000Z',
        lastUsedAt: '2026-02-20T09:14:00.000Z',
        tokenPreview: 'mdta_live_••••••••••••a9f2',
    },
]

const randomSegment = () => Math.random().toString(36).slice(2, 10)

export function ApiKeysPage() {
    const [keys, setKeys] = useState<ApiKeyRecord[]>(initialKeys)

    const keyCountLabel = useMemo(() => `${keys.length} active key${keys.length > 1 ? 's' : ''}`, [keys.length])

    const createKey = () => {
        const now = new Date().toISOString()
        const token = `mdta_live_${randomSegment()}${randomSegment()}`
        setKeys((previous) => [
            {
                id: `key-${Date.now()}`,
                label: `Generated key ${previous.length + 1}`,
                scope: 'investigations:read subjects:read',
                createdAt: now,
                tokenPreview: `${token.slice(0, 14)}••••${token.slice(-4)}`,
            },
            ...previous,
        ])
    }

    const revokeKey = (id: string) => {
        setKeys((previous) => previous.filter((key) => key.id !== id))
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>API keys</CardTitle>
                    <CardDescription>Generate and revoke personal access tokens for integrations.</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-wrap items-center justify-between gap-3">
                    <Badge variant="outline">{keyCountLabel}</Badge>
                    <Button onClick={createKey}>Generate key</Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Issued keys</CardTitle>
                    <CardDescription>Only token previews are shown after creation.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {keys.length === 0 ? (
                        <p className="text-sm text-slate-500">No API keys currently active.</p>
                    ) : (
                        keys.map((key) => (
                            <div key={key.id} className="rounded-2xl border border-line bg-white p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-800">{key.label}</p>
                                        <p className="text-xs text-slate-500">{key.scope}</p>
                                    </div>
                                    <Button variant="outline" size="sm" onClick={() => revokeKey(key.id)}>
                                        Revoke
                                    </Button>
                                </div>
                                <p className="mt-2 font-mono text-xs text-slate-600">{key.tokenPreview}</p>
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>
        </div>
    )
}
