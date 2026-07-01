import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import {
    searchNihCdeElements,
    getNihCdeElement,
    importNihCdeElement,
} from '../connected-apps.api.ts'
import type { NihCdeElement } from '../connected-apps.api.ts'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { Search, Download, ChevronRight, ChevronLeft, X, BookOpen, ListChecks, Info } from 'lucide-react'

const PAGE_SIZE = 20

function extractName(element: NihCdeElement): string {
    return element.designations?.[0]?.designation ?? element.tinyId
}

function extractDefinition(element: NihCdeElement): string | null {
    return element.definitions?.[0]?.definition ?? null
}

function statusVariant(status: string | null): 'default' | 'outline' | 'success' {
    if (!status) return 'outline'
    const s = status.toLowerCase()
    if (s === 'standard') return 'default'
    if (s === 'qualified' || s === 'candidate') return 'success'
    return 'outline'
}

interface CdeDetailPanelProps {
    appId: string
    tinyId: string
    onClose: () => void
}

function CdeDetailPanel({ appId, tinyId, onClose }: CdeDetailPanelProps) {
    const { toast } = useToast()

    const { data: element, isLoading, isError } = useQuery({
        queryKey: ['nih-cde-element', appId, tinyId],
        queryFn: () => getNihCdeElement(appId, tinyId),
    })

    const importMutation = useMutation({
        mutationFn: () => importNihCdeElement(appId, tinyId),
        onSuccess: (result) => {
            toast({
                title: 'CDE imported',
                description: `"${result.name}" (${result.tinyId}) has been saved to your local database.`,
            })
        },
        onError: (err: Error) => {
            toast({
                title: 'Import failed',
                description: err.message,
                variant: 'error',
            })
        },
    })

    return (
        <div className="flex flex-col h-full">
            <div className="flex items-center justify-between mb-4">
                <h4 className="font-display font-semibold text-ink truncate mr-2">
                    {isLoading ? 'Loading…' : element ? extractName(element) : tinyId}
                </h4>
                <button onClick={onClose} className="text-muted hover:text-ink shrink-0">
                    <X className="h-4 w-4" />
                </button>
            </div>

            {isLoading && (
                <div className="space-y-3">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-16 w-full" />
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-24 w-full" />
                </div>
            )}

            {isError && (
                <p className="text-sm text-red-600">Failed to load CDE details.</p>
            )}

            {element && (
                <div className="space-y-4 overflow-y-auto flex-1 text-sm">
                    <div className="flex flex-wrap gap-2 items-center">
                        <code className="rounded bg-surface-2 px-2 py-0.5 text-xs font-mono text-ink">{element.tinyId}</code>
                        {element.version && (
                            <Badge variant="outline">v{element.version}</Badge>
                        )}
                        {element.registrationStatus && (
                            <Badge variant={statusVariant(element.registrationStatus)}>
                                {element.registrationStatus}
                            </Badge>
                        )}
                        {element.stewardOrg?.name && (
                            <span className="text-xs text-muted">{element.stewardOrg.name}</span>
                        )}
                    </div>

                    {element.designations && element.designations.length > 1 && (
                        <div>
                            <p className="text-xs font-semibold text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                                <BookOpen className="h-3 w-3" /> Designations
                            </p>
                            <ul className="space-y-1">
                                {element.designations.map((d, i) => (
                                    <li key={i} className="text-ink">{d.designation}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {extractDefinition(element) && (
                        <div>
                            <p className="text-xs font-semibold text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                                <Info className="h-3 w-3" /> Definition
                            </p>
                            <p className="text-ink leading-relaxed">{extractDefinition(element)}</p>
                        </div>
                    )}

                    {element.valueDomain && (element.valueDomain.datatype || element.valueDomain.uom) && (
                        <div className="flex gap-4">
                            {element.valueDomain.datatype && (
                                <div>
                                    <p className="text-xs text-muted">Datatype</p>
                                    <p className="font-mono text-xs">{element.valueDomain.datatype}</p>
                                </div>
                            )}
                            {element.valueDomain.uom && (
                                <div>
                                    <p className="text-xs text-muted">Unit</p>
                                    <p className="font-mono text-xs">{element.valueDomain.uom}</p>
                                </div>
                            )}
                        </div>
                    )}

                    {element.permissibleValues && element.permissibleValues.length > 0 && (
                        <div>
                            <p className="text-xs font-semibold text-muted uppercase tracking-wide mb-2 flex items-center gap-1">
                                <ListChecks className="h-3 w-3" /> Permissible Values ({element.permissibleValues.length})
                            </p>
                            <div className="rounded-xl border border-line overflow-hidden">
                                <table className="w-full text-xs">
                                    <thead>
                                        <tr className="bg-surface-2 text-muted">
                                            <th className="text-left px-3 py-2 font-medium">Value</th>
                                            <th className="text-left px-3 py-2 font-medium">Meaning</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {element.permissibleValues.map((pv, i) => (
                                            <tr key={i} className="border-t border-line">
                                                <td className="px-3 py-2 font-mono">{pv.permissibleValue ?? '—'}</td>
                                                <td className="px-3 py-2 text-ink">{pv.valueMeaningName ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    <div className="pt-2 border-t border-line">
                        <Button
                            className="w-full"
                            size="sm"
                            onClick={() => importMutation.mutate()}
                            disabled={importMutation.isPending}
                        >
                            <Download className="h-4 w-4 mr-2" />
                            {importMutation.isPending ? 'Importing…' : 'Import to local database'}
                        </Button>
                        <p className="mt-2 text-xs text-muted text-center">
                            Stores source, tinyId, version, definition, permissible values, and raw JSON.
                        </p>
                    </div>
                </div>
            )}
        </div>
    )
}

interface NihCdeBrowserPanelProps {
    appId: string
}

export function NihCdeBrowserPanel({ appId }: NihCdeBrowserPanelProps) {
    const [searchTerm, setSearchTerm] = useState('')
    const [submittedTerm, setSubmittedTerm] = useState('')
    const [page, setPage] = useState(0)
    const [selectedTinyId, setSelectedTinyId] = useState<string | null>(null)

    const skip = page * PAGE_SIZE

    const { data, isLoading, isError } = useQuery({
        queryKey: ['nih-cde-search', appId, submittedTerm, page],
        queryFn: () => searchNihCdeElements(appId, submittedTerm, PAGE_SIZE, skip),
        enabled: submittedTerm.trim().length > 0,
    })

    const totalPages = data ? Math.ceil(data.totalItems / PAGE_SIZE) : 0

    function handleSearch(e: React.FormEvent) {
        e.preventDefault()
        setPage(0)
        setSelectedTinyId(null)
        setSubmittedTerm(searchTerm.trim())
    }

    const hasResults = data && data.elements.length > 0

    return (
        <div className="space-y-4">
            <form onSubmit={handleSearch} className="flex gap-2">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted" />
                    <Input
                        className="pl-9"
                        placeholder="Search CDEs (e.g. body weight, sex, age…)"
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                </div>
                <Button type="submit" disabled={searchTerm.trim().length === 0 || isLoading}>
                    Search
                </Button>
            </form>

            {!submittedTerm && (
                <div className="rounded-xl border border-line bg-surface-2 px-4 py-8 text-center text-sm text-muted">
                    Search the NIH Common Data Elements repository to browse standardized metadata fields and their permissible values.
                </div>
            )}

            {isLoading && (
                <div className="space-y-2">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <Skeleton key={i} className="h-12 w-full rounded-xl" />
                    ))}
                </div>
            )}

            {isError && (
                <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Search failed. Check that the connected app is configured and the NIH CDE API is reachable.
                </div>
            )}

            {data && !isLoading && data.elements.length === 0 && (
                <div className="rounded-xl border border-line bg-surface-2 px-4 py-8 text-center text-sm text-muted">
                    No CDEs found for &ldquo;{submittedTerm}&rdquo;.
                </div>
            )}

            {hasResults && (
                <div className={`grid gap-4 ${selectedTinyId ? 'lg:grid-cols-2' : ''}`}>
                    <div className="space-y-2">
                        {data.elements.map((element) => {
                            const name = extractName(element)
                            const definition = extractDefinition(element)
                            const isSelected = selectedTinyId === element.tinyId
                            return (
                                <button
                                    key={element.tinyId}
                                    onClick={() => setSelectedTinyId(isSelected ? null : element.tinyId)}
                                    className={`w-full text-left rounded-xl border px-4 py-3 transition-colors hover:border-brand hover:bg-brand/5 ${
                                        isSelected ? 'border-brand bg-brand/5' : 'border-line bg-surface'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="font-medium text-sm text-ink truncate">{name}</span>
                                                {element.registrationStatus && (
                                                    <Badge variant={statusVariant(element.registrationStatus)} className="text-xs shrink-0">
                                                        {element.registrationStatus}
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2 mt-0.5">
                                                <code className="text-xs text-muted font-mono">{element.tinyId}</code>
                                                {element.stewardOrg?.name && (
                                                    <span className="text-xs text-muted">· {element.stewardOrg.name}</span>
                                                )}
                                                {element.permissibleValues?.length > 0 && (
                                                    <span className="text-xs text-muted">· {element.permissibleValues.length} values</span>
                                                )}
                                            </div>
                                            {definition && (
                                                <p className="mt-1 text-xs text-muted line-clamp-2">{definition}</p>
                                            )}
                                        </div>
                                        <ChevronRight className={`h-4 w-4 text-muted shrink-0 transition-transform ${isSelected ? 'rotate-90' : ''}`} />
                                    </div>
                                </button>
                            )
                        })}

                        {totalPages > 1 && (
                            <div className="flex items-center justify-between pt-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => { setPage(p => p - 1); setSelectedTinyId(null) }}
                                    disabled={page === 0}
                                >
                                    <ChevronLeft className="h-4 w-4 mr-1" /> Previous
                                </Button>
                                <span className="text-xs text-muted">
                                    Page {page + 1} of {totalPages} ({data.totalItems} CDEs)
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => { setPage(p => p + 1); setSelectedTinyId(null) }}
                                    disabled={page >= totalPages - 1}
                                >
                                    Next <ChevronRight className="h-4 w-4 ml-1" />
                                </Button>
                            </div>
                        )}
                    </div>

                    {selectedTinyId && (
                        <Card className="bg-surface-3 border-line self-start sticky top-4">
                            <CdeDetailPanel
                                appId={appId}
                                tinyId={selectedTinyId}
                                onClose={() => setSelectedTinyId(null)}
                            />
                        </Card>
                    )}
                </div>
            )}
        </div>
    )
}
