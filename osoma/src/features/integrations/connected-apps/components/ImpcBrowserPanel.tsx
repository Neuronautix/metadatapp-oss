import { useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
    searchImpcProcedures,
    getImpcProcedure,
    listImpcPipelines,
} from '../connected-apps.api.ts'
import type { ImpcProcedure, ImpcParameter, ImpcPipeline } from '../connected-apps.api.ts'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { Search, ChevronRight, ChevronLeft, X, Info, Ruler, FlaskConical, Workflow } from 'lucide-react'

const PAGE_SIZE = 20

type BrowseMode = 'procedures' | 'pipelines'

function procedureKey(procedure: ImpcProcedure): string {
    return String(procedure.stableKey ?? '')
}

// IMPReSS keys detail/parameter REST calls by the numeric procedure id (procID),
// not the stable key — so selection identity and detail fetches use this, falling
// back to the stable key only when no numeric id is present (e.g. mock data).
function procedureDetailId(procedure: ImpcProcedure): string {
    return String(procedure.procedureId ?? procedure.procID ?? procedure.id ?? procedure.stableKey ?? '')
}

function procedureName(procedure: ImpcProcedure): string {
    return procedure.name ?? procedureKey(procedure) ?? 'Untitled procedure'
}

function pipelineKey(pipeline: ImpcPipeline): string {
    return String(pipeline.stableKey ?? '')
}

function pipelineName(pipeline: ImpcPipeline): string {
    return pipeline.name ?? pipelineKey(pipeline) ?? 'Untitled pipeline'
}

function parameterKey(parameter: ImpcParameter): string {
    return String(parameter.stableKey ?? parameter.name ?? '')
}

interface ProcedureDetailPanelProps {
    appId: string
    procedureId: string
    initialData?: ImpcProcedure
    onClose: () => void
}

function ProcedureDetailPanel({ appId, procedureId, initialData, onClose }: ProcedureDetailPanelProps) {
    const { data: procedure, isLoading, isError } = useQuery({
        queryKey: ['impc-procedure', appId, procedureId],
        queryFn: () => getImpcProcedure(appId, procedureId),
        // Seed from the selected row so the panel renders instantly while the
        // full procedure (with parameters) is fetched in the background.
        initialData,
    })

    const parameters = procedure?.parameters ?? []

    return (
        <div className="flex flex-col h-full">
            <div className="flex items-center justify-between mb-4">
                <h4 className="font-display font-semibold text-ink truncate mr-2">
                    {isLoading ? 'Loading…' : procedure ? procedureName(procedure) : procedureId}
                </h4>
                <button onClick={onClose} className="text-muted hover:text-ink shrink-0" aria-label="Close procedure detail">
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
                <p className="text-sm text-error">Failed to load procedure details.</p>
            )}

            {procedure && (
                <div className="space-y-4 overflow-y-auto flex-1 text-sm">
                    <div className="flex flex-wrap gap-2 items-center">
                        <code className="rounded bg-surface-2 px-2 py-0.5 text-xs font-mono text-ink">{procedureKey(procedure) || procedureId}</code>
                        {procedure.isMandatory && <Badge variant="outline">Mandatory</Badge>}
                        {procedure.level && <span className="text-xs text-muted">{procedure.level}</span>}
                    </div>

                    {procedure.description && (
                        <div>
                            <p className="text-xs font-semibold text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                                <Info className="h-3 w-3" /> Description
                            </p>
                            <p className="text-ink leading-relaxed">{procedure.description}</p>
                        </div>
                    )}

                    <div>
                        <p className="text-xs font-semibold text-muted uppercase tracking-wide mb-2 flex items-center gap-1">
                            <Ruler className="h-3 w-3" /> Standardized parameters ({parameters.length})
                        </p>
                        {parameters.length === 0 ? (
                            <p className="text-xs text-muted">No parameters available for this procedure.</p>
                        ) : (
                            <div className="rounded-xl border border-line overflow-hidden">
                                <table className="w-full text-xs">
                                    <tbody>
                                        {parameters.map((parameter) => (
                                            <tr key={parameterKey(parameter)} className="border-t border-line first:border-t-0">
                                                <td className="px-3 py-2 align-top">
                                                    <div className="text-ink break-words font-medium">{parameter.name ?? parameterKey(parameter)}</div>
                                                    <div className="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                                        <code className="font-mono text-muted">{parameterKey(parameter)}</code>
                                                        {parameter.isMetadata && <Badge variant="outline" className="text-[10px]">metadata</Badge>}
                                                    </div>
                                                    {parameter.ontologyMapping && parameter.ontologyMapping.length > 0 && (
                                                        <div className="mt-1 flex flex-wrap gap-1">
                                                            {parameter.ontologyMapping.map((mapping, index) => (
                                                                <Badge key={`${mapping.id ?? index}`} variant="outline" className="text-[10px]">
                                                                    {mapping.id}{mapping.term ? ` · ${mapping.term}` : ''}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-muted align-top whitespace-nowrap text-right">
                                                    <div>{parameter.datatype ?? '—'}</div>
                                                    {parameter.unit && <div className="text-[10px]">{parameter.unit}</div>}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    )
}

interface ImpcBrowserPanelProps {
    appId: string
}

export function ImpcBrowserPanel({ appId }: ImpcBrowserPanelProps) {
    const [mode, setMode] = useState<BrowseMode>('procedures')

    return (
        <div className="space-y-4">
            <div className="inline-flex rounded-xl border border-line bg-surface p-0.5" role="tablist" aria-label="IMPReSS browser">
                <button
                    type="button"
                    role="tab"
                    aria-selected={mode === 'procedures'}
                    onClick={() => setMode('procedures')}
                    className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                        mode === 'procedures' ? 'bg-brand/10 text-brand' : 'text-muted hover:text-ink'
                    }`}
                >
                    <FlaskConical className="h-4 w-4" /> Procedures
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={mode === 'pipelines'}
                    onClick={() => setMode('pipelines')}
                    className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                        mode === 'pipelines' ? 'bg-brand/10 text-brand' : 'text-muted hover:text-ink'
                    }`}
                >
                    <Workflow className="h-4 w-4" /> Pipelines
                </button>
            </div>

            {mode === 'procedures' ? <ProceduresBrowser appId={appId} /> : <PipelinesBrowser appId={appId} />}
        </div>
    )
}

function ProceduresBrowser({ appId }: ImpcBrowserPanelProps) {
    const [searchTerm, setSearchTerm] = useState('')
    const [submittedTerm, setSubmittedTerm] = useState('')
    const [page, setPage] = useState(0)
    const [selectedKey, setSelectedKey] = useState<string | null>(null)
    // Keep keyboard focus from falling to <body> when a pagination button
    // disables itself at a boundary: move focus to the results region instead.
    const listRef = useRef<HTMLDivElement>(null)
    const goToPage = (next: number) => {
        setPage(next)
        setSelectedKey(null)
        listRef.current?.focus()
    }

    const offset = page * PAGE_SIZE

    const { data, isLoading, isError } = useQuery({
        queryKey: ['impc-procedures-search', appId, submittedTerm, page],
        queryFn: () => searchImpcProcedures(appId, submittedTerm, PAGE_SIZE, offset),
        enabled: submittedTerm.trim().length > 0,
    })

    const totalPages = data ? Math.ceil(data.totalItems / PAGE_SIZE) : 0

    function handleSearch(e: React.FormEvent) {
        e.preventDefault()
        setPage(0)
        setSelectedKey(null)
        setSubmittedTerm(searchTerm.trim())
    }

    const hasResults = data && data.procedures.length > 0

    return (
        <div className="space-y-4">
            <form onSubmit={handleSearch} className="flex gap-2">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted" />
                    <Input
                        className="pl-9"
                        placeholder="Search standardized procedures (e.g. open field, startle, grip strength…)"
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
                    Search IMPReSS to browse standardized phenotyping procedures and their parameters for behavioral studies.
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
                <div className="rounded-xl border border-error/40 bg-error/10 px-4 py-3 text-sm text-error">
                    Search failed. Check that the connected app is configured and the IMPReSS API is reachable.
                </div>
            )}

            {data && !isLoading && data.procedures.length === 0 && (
                <div className="rounded-xl border border-line bg-surface-2 px-4 py-8 text-center text-sm text-muted">
                    No procedures found for &ldquo;{submittedTerm}&rdquo;.
                </div>
            )}

            {hasResults && (
                <div className={`grid gap-4 ${selectedKey ? 'lg:grid-cols-2' : ''}`}>
                    <div ref={listRef} tabIndex={-1} aria-label="Procedure results" className="space-y-2 outline-none">
                        {data.procedures.map((procedure) => {
                            const detailId = procedureDetailId(procedure)
                            const displayKey = procedureKey(procedure)
                            const isSelected = selectedKey === detailId
                            return (
                                <button
                                    key={detailId}
                                    onClick={() => setSelectedKey(isSelected ? null : detailId)}
                                    className={`w-full text-left rounded-xl border px-4 py-3 transition-colors hover:border-brand hover:bg-brand/5 ${
                                        isSelected ? 'border-brand bg-brand/5' : 'border-line bg-surface'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="font-medium text-sm text-ink truncate">{procedureName(procedure)}</span>
                                                {procedure.isMandatory && (
                                                    <Badge variant="outline" className="text-xs shrink-0">Mandatory</Badge>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2 mt-0.5">
                                                {displayKey && <code className="text-xs text-muted font-mono">{displayKey}</code>}
                                                {procedure.level && <span className="text-xs text-muted">· {procedure.level}</span>}
                                            </div>
                                            {procedure.description && (
                                                <p className="mt-1 text-xs text-muted line-clamp-2">{procedure.description}</p>
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
                                    onClick={() => goToPage(page - 1)}
                                    disabled={page === 0}
                                >
                                    <ChevronLeft className="h-4 w-4 mr-1" /> Previous
                                </Button>
                                <span className="text-xs text-muted">
                                    Page {page + 1} of {totalPages} ({data.totalItems} procedures)
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => goToPage(page + 1)}
                                    disabled={page >= totalPages - 1}
                                >
                                    Next <ChevronRight className="h-4 w-4 ml-1" />
                                </Button>
                            </div>
                        )}
                    </div>

                    {selectedKey && (
                        <Card className="bg-surface-3 border-line self-start sticky top-4">
                            <ProcedureDetailPanel
                                appId={appId}
                                procedureId={selectedKey}
                                initialData={data.procedures.find((p) => procedureDetailId(p) === selectedKey)}
                                onClose={() => setSelectedKey(null)}
                            />
                        </Card>
                    )}
                </div>
            )}
        </div>
    )
}

function PipelinesBrowser({ appId }: ImpcBrowserPanelProps) {
    const [page, setPage] = useState(0)
    const listRef = useRef<HTMLDivElement>(null)
    const goToPage = (next: number) => {
        setPage(next)
        listRef.current?.focus()
    }

    const offset = page * PAGE_SIZE
    const { data, isLoading, isError } = useQuery({
        queryKey: ['impc-pipelines', appId, page],
        queryFn: () => listImpcPipelines(appId, PAGE_SIZE, offset),
    })

    const totalPages = data ? Math.ceil(data.totalItems / PAGE_SIZE) : 0

    return (
        <div className="space-y-4">
            <p className="text-sm text-muted">
                Browse IMPReSS phenotyping pipelines — the standardized screens that group procedures and
                parameters used across mouse behavioral and physiological studies.
            </p>

            {isLoading && (
                <div className="space-y-2">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <Skeleton key={i} className="h-12 w-full rounded-xl" />
                    ))}
                </div>
            )}

            {isError && (
                <div className="rounded-xl border border-error/40 bg-error/10 px-4 py-3 text-sm text-error">
                    Failed to load pipelines. Check that the connected app is configured and the IMPReSS API is reachable.
                </div>
            )}

            {data && !isLoading && data.pipelines.length === 0 && (
                <div className="rounded-xl border border-line bg-surface-2 px-4 py-8 text-center text-sm text-muted">
                    No pipelines returned by IMPReSS.
                </div>
            )}

            {data && data.pipelines.length > 0 && (
                <div ref={listRef} tabIndex={-1} aria-label="Pipeline results" className="space-y-2 outline-none">
                    {data.pipelines.map((pipeline) => {
                        const key = pipelineKey(pipeline)
                        return (
                            <div
                                key={key}
                                className="w-full rounded-xl border border-line bg-surface px-4 py-3"
                            >
                                <div className="flex items-center gap-2 flex-wrap">
                                    <Workflow className="h-4 w-4 text-muted shrink-0" />
                                    <span className="font-medium text-sm text-ink truncate">{pipelineName(pipeline)}</span>
                                    {key && <code className="text-xs text-muted font-mono">{key}</code>}
                                </div>
                                {pipeline.description && (
                                    <p className="mt-1 text-xs text-muted">{pipeline.description}</p>
                                )}
                            </div>
                        )
                    })}

                    {totalPages > 1 && (
                        <div className="flex items-center justify-between pt-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => goToPage(page - 1)}
                                disabled={page === 0}
                            >
                                <ChevronLeft className="h-4 w-4 mr-1" /> Previous
                            </Button>
                            <span className="text-xs text-muted">
                                Page {page + 1} of {totalPages} ({data.totalItems} pipelines)
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => goToPage(page + 1)}
                                disabled={page >= totalPages - 1}
                            >
                                Next <ChevronRight className="h-4 w-4 ml-1" />
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </div>
    )
}
