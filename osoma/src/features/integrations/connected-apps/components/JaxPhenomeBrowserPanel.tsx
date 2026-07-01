import { useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
    searchJaxPhenomeMeasures,
    getJaxPhenomeMeasure,
    listJaxPhenomeStrains,
} from '../connected-apps.api.ts'
import type { JaxPhenomeMeasure, JaxPhenomeStrain } from '../connected-apps.api.ts'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { Search, ChevronRight, ChevronLeft, X, Info, Ruler, FlaskConical, Dna } from 'lucide-react'

const PAGE_SIZE = 20

type BrowseMode = 'measures' | 'strains'

function measureKey(measure: JaxPhenomeMeasure): string {
    return String(measure.id ?? measure.measnum ?? '')
}

function extractName(measure: JaxPhenomeMeasure): string {
    return measure.name ?? measureKey(measure) ?? 'Untitled measure'
}

function strainKey(strain: JaxPhenomeStrain): string {
    return String(strain.id ?? strain.strainid ?? '')
}

function strainName(strain: JaxPhenomeStrain): string {
    return strain.name ?? strainKey(strain) ?? 'Untitled strain'
}

// Fields surfaced first in the detail view; remaining keys are rendered generically.
const PRIMARY_DETAIL_FIELDS = ['name', 'description', 'units', 'projectsym', 'id', 'measnum'] as const

function formatValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—'
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value)
}

interface MeasureDetailPanelProps {
    appId: string
    measureId: string
    initialData?: JaxPhenomeMeasure
    onClose: () => void
}

function MeasureDetailPanel({ appId, measureId, initialData, onClose }: MeasureDetailPanelProps) {
    const { data: measure, isLoading, isError } = useQuery({
        queryKey: ['jax-phenome-measure', appId, measureId],
        queryFn: () => getJaxPhenomeMeasure(appId, measureId),
        // Seed from the selected row so the panel renders instantly while the
        // full measure is fetched in the background.
        initialData,
    })

    const extraEntries = measure
        ? Object.entries(measure).filter(
            ([key, value]) =>
                !PRIMARY_DETAIL_FIELDS.includes(key as (typeof PRIMARY_DETAIL_FIELDS)[number]) &&
                value !== null &&
                value !== undefined &&
                value !== ''
        )
        : []

    return (
        <div className="flex flex-col h-full">
            <div className="flex items-center justify-between mb-4">
                <h4 className="font-display font-semibold text-ink truncate mr-2">
                    {isLoading ? 'Loading…' : measure ? extractName(measure) : measureId}
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
                <p className="text-sm text-error">Failed to load measure details.</p>
            )}

            {measure && (
                <div className="space-y-4 overflow-y-auto flex-1 text-sm">
                    <div className="flex flex-wrap gap-2 items-center">
                        <code className="rounded bg-surface-2 px-2 py-0.5 text-xs font-mono text-ink">{measureKey(measure) || measureId}</code>
                        {measure.units && (
                            <Badge variant="outline">{measure.units}</Badge>
                        )}
                        {measure.projectsym && (
                            <span className="text-xs text-muted">{measure.projectsym}</span>
                        )}
                    </div>

                    {measure.description && (
                        <div>
                            <p className="text-xs font-semibold text-muted uppercase tracking-wide mb-1 flex items-center gap-1">
                                <Info className="h-3 w-3" /> Description
                            </p>
                            <p className="text-ink leading-relaxed">{measure.description}</p>
                        </div>
                    )}

                    {extraEntries.length > 0 && (
                        <div>
                            <p className="text-xs font-semibold text-muted uppercase tracking-wide mb-2 flex items-center gap-1">
                                <Ruler className="h-3 w-3" /> Fields
                            </p>
                            <div className="rounded-xl border border-line overflow-hidden">
                                <table className="w-full text-xs">
                                    <tbody>
                                        {extraEntries.map(([key, value]) => (
                                            <tr key={key} className="border-t border-line first:border-t-0">
                                                <td className="px-3 py-2 font-mono text-muted align-top whitespace-nowrap">{key}</td>
                                                <td className="px-3 py-2 text-ink break-words">{formatValue(value)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    )
}

interface JaxPhenomeBrowserPanelProps {
    appId: string
}

export function JaxPhenomeBrowserPanel({ appId }: JaxPhenomeBrowserPanelProps) {
    const [mode, setMode] = useState<BrowseMode>('measures')

    return (
        <div className="space-y-4">
            <div className="inline-flex rounded-xl border border-line bg-surface p-0.5" role="tablist" aria-label="JAX Phenome browser">
                <button
                    type="button"
                    role="tab"
                    aria-selected={mode === 'measures'}
                    onClick={() => setMode('measures')}
                    className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                        mode === 'measures' ? 'bg-brand/10 text-brand' : 'text-muted hover:text-ink'
                    }`}
                >
                    <Ruler className="h-4 w-4" /> Measures
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={mode === 'strains'}
                    onClick={() => setMode('strains')}
                    className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                        mode === 'strains' ? 'bg-brand/10 text-brand' : 'text-muted hover:text-ink'
                    }`}
                >
                    <Dna className="h-4 w-4" /> Strains
                </button>
            </div>

            {mode === 'measures' ? <MeasuresBrowser appId={appId} /> : <StrainsBrowser appId={appId} />}
        </div>
    )
}

function MeasuresBrowser({ appId }: JaxPhenomeBrowserPanelProps) {
    const [searchTerm, setSearchTerm] = useState('')
    const [submittedTerm, setSubmittedTerm] = useState('')
    const [page, setPage] = useState(0)
    const [selectedMeasureId, setSelectedMeasureId] = useState<string | null>(null)
    // Keep keyboard focus from falling to <body> when a pagination button
    // disables itself at a boundary: move focus to the results region instead.
    const listRef = useRef<HTMLDivElement>(null)
    const goToPage = (next: number) => {
        setPage(next)
        setSelectedMeasureId(null)
        listRef.current?.focus()
    }

    const offset = page * PAGE_SIZE

    const { data, isLoading, isError } = useQuery({
        queryKey: ['jax-phenome-search', appId, submittedTerm, page],
        queryFn: () => searchJaxPhenomeMeasures(appId, submittedTerm, PAGE_SIZE, offset),
        enabled: submittedTerm.trim().length > 0,
    })

    const totalPages = data ? Math.ceil(data.totalItems / PAGE_SIZE) : 0

    function handleSearch(e: React.FormEvent) {
        e.preventDefault()
        setPage(0)
        setSelectedMeasureId(null)
        setSubmittedTerm(searchTerm.trim())
    }

    // MPD resolves free text to ontology terms; clicking a term re-scopes the
    // search to that exact term id (the client resolves it directly).
    function selectTerm(termId: string) {
        setPage(0)
        setSelectedMeasureId(null)
        setSubmittedTerm(termId)
    }

    const hasResults = data && data.measures.length > 0
    const ontologyTerms = data?.ontologyTerms ?? []
    const resolvedTermId = typeof data?.resolvedTerm?.id === 'string' ? data.resolvedTerm.id : null

    return (
        <div className="space-y-4">
            <form onSubmit={handleSearch} className="flex gap-2">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted" />
                    <Input
                        className="pl-9"
                        placeholder="Search phenotype measures (e.g. body weight, glucose, activity…)"
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
                    Search the Mouse Phenome Database to browse curated phenotype measures across mouse strains for physiology and behavior research.
                </div>
            )}

            {ontologyTerms.length > 0 && (
                <div className="space-y-1.5">
                    <p className="text-xs text-muted">Matched ontology terms — pick one to refine the measures:</p>
                    <div className="flex flex-wrap gap-1.5" role="group" aria-label="Matched ontology terms">
                        {ontologyTerms.map((term) => {
                            const isActive = resolvedTermId === term.id
                            return (
                                <button
                                    key={term.id}
                                    type="button"
                                    onClick={() => selectTerm(term.id)}
                                    aria-pressed={isActive}
                                    title={`${term.descrip} (${term.id})`}
                                    className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition-colors ${
                                        isActive
                                            ? 'border-brand bg-brand/10 text-brand'
                                            : 'border-line bg-surface text-muted hover:border-brand hover:text-ink'
                                    }`}
                                >
                                    <span className="truncate max-w-[14rem]">{term.descrip}</span>
                                    <code className="font-mono text-[10px] opacity-70">{term.id}</code>
                                </button>
                            )
                        })}
                    </div>
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
                    Search failed. Check that the connected app is configured and the Mouse Phenome Database API is reachable.
                </div>
            )}

            {data && !isLoading && data.measures.length === 0 && (
                <div className="rounded-xl border border-line bg-surface-2 px-4 py-8 text-center text-sm text-muted">
                    No measures found for &ldquo;{submittedTerm}&rdquo;.
                </div>
            )}

            {hasResults && (
                <div className={`grid gap-4 ${selectedMeasureId ? 'lg:grid-cols-2' : ''}`}>
                    <div ref={listRef} tabIndex={-1} aria-label="Measure results" className="space-y-2 outline-none">
                        {data.measures.map((measure) => {
                            const key = measureKey(measure)
                            const name = extractName(measure)
                            const isSelected = selectedMeasureId === key
                            return (
                                <button
                                    key={key}
                                    onClick={() => setSelectedMeasureId(isSelected ? null : key)}
                                    className={`w-full text-left rounded-xl border px-4 py-3 transition-colors hover:border-brand hover:bg-brand/5 ${
                                        isSelected ? 'border-brand bg-brand/5' : 'border-line bg-surface'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="font-medium text-sm text-ink truncate">{name}</span>
                                                {measure.units && (
                                                    <Badge variant="outline" className="text-xs shrink-0">
                                                        {measure.units}
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2 mt-0.5">
                                                {key && <code className="text-xs text-muted font-mono">{key}</code>}
                                                {measure.projectsym && (
                                                    <span className="text-xs text-muted">· {measure.projectsym}</span>
                                                )}
                                            </div>
                                            {measure.description && (
                                                <p className="mt-1 text-xs text-muted line-clamp-2">{measure.description}</p>
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
                                    Page {page + 1} of {totalPages} ({data.totalItems} measures)
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

                    {selectedMeasureId && (
                        <Card className="bg-surface-3 border-line self-start sticky top-4">
                            <MeasureDetailPanel
                                appId={appId}
                                measureId={selectedMeasureId}
                                initialData={data.measures.find((m) => measureKey(m) === selectedMeasureId)}
                                onClose={() => setSelectedMeasureId(null)}
                            />
                        </Card>
                    )}
                </div>
            )}
        </div>
    )
}

// Strains have no upstream detail endpoint, so the detail view renders the row
// fields directly — no extra fetch, no flash of loading state.
const PRIMARY_STRAIN_FIELDS = ['name', 'symbol', 'id', 'strainid'] as const

function StrainDetailPanel({ strain, onClose }: { strain: JaxPhenomeStrain; onClose: () => void }) {
    const extraEntries = Object.entries(strain).filter(
        ([key, value]) =>
            !PRIMARY_STRAIN_FIELDS.includes(key as (typeof PRIMARY_STRAIN_FIELDS)[number]) &&
            value !== null &&
            value !== undefined &&
            value !== ''
    )

    return (
        <div className="flex flex-col h-full">
            <div className="flex items-center justify-between mb-4">
                <h4 className="font-display font-semibold text-ink truncate mr-2">{strainName(strain)}</h4>
                <button onClick={onClose} className="text-muted hover:text-ink shrink-0" aria-label="Close strain detail">
                    <X className="h-4 w-4" />
                </button>
            </div>

            <div className="space-y-4 overflow-y-auto flex-1 text-sm">
                <div className="flex flex-wrap gap-2 items-center">
                    <code className="rounded bg-surface-2 px-2 py-0.5 text-xs font-mono text-ink">{strainKey(strain) || '—'}</code>
                    {strain.symbol && <Badge variant="outline">{strain.symbol}</Badge>}
                </div>

                {extraEntries.length > 0 && (
                    <div>
                        <p className="text-xs font-semibold text-muted uppercase tracking-wide mb-2 flex items-center gap-1">
                            <Info className="h-3 w-3" /> Fields
                        </p>
                        <div className="rounded-xl border border-line overflow-hidden">
                            <table className="w-full text-xs">
                                <tbody>
                                    {extraEntries.map(([key, value]) => (
                                        <tr key={key} className="border-t border-line first:border-t-0">
                                            <td className="px-3 py-2 font-mono text-muted align-top whitespace-nowrap">{key}</td>
                                            <td className="px-3 py-2 text-ink break-words">{formatValue(value)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </div>
    )
}

function StrainsBrowser({ appId }: JaxPhenomeBrowserPanelProps) {
    const [page, setPage] = useState(0)
    const [selectedStrainKey, setSelectedStrainKey] = useState<string | null>(null)
    const listRef = useRef<HTMLDivElement>(null)
    const goToPage = (next: number) => {
        setPage(next)
        setSelectedStrainKey(null)
        listRef.current?.focus()
    }

    const offset = page * PAGE_SIZE
    const { data, isLoading, isError } = useQuery({
        queryKey: ['jax-phenome-strains', appId, page],
        queryFn: () => listJaxPhenomeStrains(appId, PAGE_SIZE, offset),
    })

    const totalPages = data ? Math.ceil(data.totalItems / PAGE_SIZE) : 0
    const selectedStrain = data?.strains.find((s) => strainKey(s) === selectedStrainKey) ?? null

    return (
        <div className="space-y-4">
            <p className="text-sm text-muted">
                Browse curated mouse strains from the Mouse Phenome Database. Strains are the genetic backgrounds
                that JAX phenotype measures are recorded against.
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
                    Failed to load strains. Check that the connected app is configured and the Mouse Phenome Database API is reachable.
                </div>
            )}

            {data && !isLoading && data.strains.length === 0 && (
                <div className="rounded-xl border border-line bg-surface-2 px-4 py-8 text-center text-sm text-muted">
                    No strains returned by the Mouse Phenome Database.
                </div>
            )}

            {data && data.strains.length > 0 && (
                <div className={`grid gap-4 ${selectedStrain ? 'lg:grid-cols-2' : ''}`}>
                    <div ref={listRef} tabIndex={-1} aria-label="Strain results" className="space-y-2 outline-none">
                        {data.strains.map((strain) => {
                            const key = strainKey(strain)
                            const isSelected = selectedStrainKey === key
                            return (
                                <button
                                    key={key}
                                    onClick={() => setSelectedStrainKey(isSelected ? null : key)}
                                    className={`w-full text-left rounded-xl border px-4 py-3 transition-colors hover:border-brand hover:bg-brand/5 ${
                                        isSelected ? 'border-brand bg-brand/5' : 'border-line bg-surface'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <FlaskConical className="h-4 w-4 text-muted shrink-0" />
                                                <span className="font-medium text-sm text-ink truncate">{strainName(strain)}</span>
                                                {strain.symbol && (
                                                    <Badge variant="outline" className="text-xs shrink-0">
                                                        {strain.symbol}
                                                    </Badge>
                                                )}
                                            </div>
                                            {key && <code className="mt-0.5 block text-xs text-muted font-mono">{key}</code>}
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
                                    Page {page + 1} of {totalPages} ({data.totalItems} strains)
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

                    {selectedStrain && (
                        <Card className="bg-surface-3 border-line self-start sticky top-4">
                            <StrainDetailPanel strain={selectedStrain} onClose={() => setSelectedStrainKey(null)} />
                        </Card>
                    )}
                </div>
            )}
        </div>
    )
}
