import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { Search, ExternalLink, ChevronRight, SlidersHorizontal, X, Network, Layers, Check, GitCompare, Download, Library, Trash2, Sparkles } from 'lucide-react'
import { useFeatureFlagContext } from '@/feature-flags/FeatureFlagProvider.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import {
    searchReferences,
    importReferences,
    listImportedReferences,
    deleteImportedReference,
    promoteReferenceToCde,
    findSimilarReferences,
    bestMatchReferences,
    type ReferenceResult,
    type ImportedReference,
    type SimilarReference,
} from './reference-hub.api.ts'
import { TYPE_LABELS, writeCompareItems } from './reference-hub.shared.ts'

const TYPE_BADGE_VARIANT: Record<string, string> = {
    strain: 'bg-blue-50 text-blue-700',
    measure: 'bg-violet-50 text-violet-700',
    procedure: 'bg-emerald-50 text-emerald-700',
    pipeline: 'bg-emerald-50 text-emerald-700',
    cde_element: 'bg-orange-50 text-orange-700',
    protocol: 'bg-yellow-50 text-yellow-700',
    template: 'bg-pink-50 text-pink-700',
    schema: 'bg-indigo-50 text-indigo-700',
    guideline: 'bg-red-50 text-red-700',
    ontology_class: 'bg-teal-50 text-teal-700',
}

const SELECTED_APPS_KEY = 'osoma.referenceHub.selectedApps'

// Keyless public reference databases that back the federated search. Always
// available — no activation or credentials required.
const REFERENCE_SOURCES: { code: string; name: string }[] = [
    { code: 'jax_phenome', name: 'JAX Phenome (MPD)' },
    { code: 'impc', name: 'IMPReSS (IMPC)' },
    { code: 'nih_cde', name: 'NIH CDE Repository' },
    { code: 'preclinicaltrials', name: 'PreclinicalTrials.eu' },
    { code: 'mnms', name: 'MNMS (Neuroimaging)' },
    { code: 'guidelines_hub', name: 'ARRIVE / PREPARE / EQIPD' },
]
const ALL_SOURCE_CODES = REFERENCE_SOURCES.map((s) => s.code)

function readStoredSelection(): string[] {
    if (typeof window === 'undefined') return ALL_SOURCE_CODES
    try {
        const raw = window.localStorage.getItem(SELECTED_APPS_KEY)
        const parsed = raw ? JSON.parse(raw) : null
        if (!Array.isArray(parsed)) return ALL_SOURCE_CODES
        const valid = parsed.filter((x): x is string => typeof x === 'string' && ALL_SOURCE_CODES.includes(x))
        return valid.length > 0 ? valid : ALL_SOURCE_CODES
    } catch {
        return ALL_SOURCE_CODES
    }
}

function persistSelection(codes: string[]) {
    if (typeof window === 'undefined') return
    try {
        window.localStorage.setItem(SELECTED_APPS_KEY, JSON.stringify(codes))
    } catch {
        // ignore quota / serialization issues
    }
}

export function ReferenceHubPage() {
    const [tab, setTab] = useState<'search' | 'library'>('search')

    return (
        <div className="space-y-6">
            <PageHeader
                title="Reference Hub"
                subtitle="Search every connected reference database at once — strains, phenotype measures, standardized procedures, Common Data Elements and protocols — then compare and import them into your library with links back to each source of truth."
            />

            <div className="inline-flex rounded-xl border border-line bg-surface p-0.5" role="tablist" aria-label="Reference Hub views">
                <TabButton active={tab === 'search'} onClick={() => setTab('search')} icon={Search} label="Search" />
                <TabButton active={tab === 'library'} onClick={() => setTab('library')} icon={Library} label="My Library" />
            </div>

            {tab === 'search' ? <SearchView /> : <LibraryView />}
        </div>
    )
}

function TabButton({ active, onClick, icon: Icon, label }: { active: boolean; onClick: () => void; icon: React.ComponentType<{ className?: string }>; label: string }) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            onClick={onClick}
            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                active ? 'bg-brand/10 text-brand' : 'text-muted hover:text-ink'
            }`}
        >
            <Icon className="h-4 w-4" /> {label}
        </button>
    )
}

function SearchView() {
    const queryClient = useQueryClient()
    const navigate = useNavigate()
    const { toast } = useToast()
    const { isEnabled } = useFeatureFlagContext()
    const semanticEnabled = isEnabled('feature.referenceHub.semantic')

    const [query, setQuery] = useState('')
    const [submittedQuery, setSubmittedQuery] = useState('')
    const [bestMatchId, setBestMatchId] = useState<string | null>(null)
    const [bestMatchReason, setBestMatchReason] = useState<string>('')
    const [selectedCodes, setSelectedCodes] = useState<string[]>(() => readStoredSelection())
    const [showSourcePicker, setShowSourcePicker] = useState(false)
    const [openResult, setOpenResult] = useState<ReferenceResult | null>(null)
    // Selection for compare/import — keyed by result id, kept across searches.
    const [selected, setSelected] = useState<Record<string, ReferenceResult>>({})

    const { data: results, isLoading, isError } = useQuery({
        queryKey: ['reference-search', submittedQuery, selectedCodes],
        queryFn: () => searchReferences(submittedQuery, selectedCodes, 20),
        enabled: submittedQuery.trim().length > 0 && selectedCodes.length > 0,
    })

    const importMutation = useMutation({
        mutationFn: (items: ReferenceResult[]) => importReferences(items),
        onSuccess: (res) => {
            void queryClient.invalidateQueries({ queryKey: ['imported-references'] })
            setSelected({})
            toast({ title: 'Imported to library', description: `${res.count} reference${res.count === 1 ? '' : 's'} saved — find them under My Library.` })
        },
        onError: () => toast({ title: 'Import failed', description: 'Could not save the selected references. Try again.' }),
    })

    const bestMatchMutation = useMutation({
        mutationFn: (items: ReferenceResult[]) => bestMatchReferences(submittedQuery, items, 3),
        onSuccess: (matches) => {
            const top = matches[0]
            if (top) {
                setBestMatchId(top.id)
                setBestMatchReason(top.reason)
            } else {
                toast({ title: 'No best match', description: 'Could not rank the results for this query.' })
            }
        },
        onError: () => toast({ title: 'Best-match failed', description: 'Could not rank the search results. Try again.' }),
    })

    const selectedItems = useMemo(() => Object.values(selected), [selected])

    function goToCompare() {
        writeCompareItems(selectedItems)
        navigate('/reference-hub/compare')
    }

    function toggleSource(code: string) {
        setSelectedCodes((current) => {
            const next = current.includes(code) ? current.filter((c) => c !== code) : [...current, code]
            persistSelection(next)
            return next
        })
    }

    function toggleSelect(item: ReferenceResult) {
        setSelected((current) => {
            const next = { ...current }
            if (next[item.id]) delete next[item.id]
            else next[item.id] = item
            return next
        })
    }

    function handleSearch(e: React.FormEvent) {
        e.preventDefault()
        setOpenResult(null)
        setBestMatchId(null)
        setBestMatchReason('')
        bestMatchMutation.reset()
        setSubmittedQuery(query.trim())
    }

    const grouped = useMemo(() => groupBySource(results ?? []), [results])
    const hasResults = (results?.length ?? 0) > 0

    return (
        <div className="space-y-4">
            <form onSubmit={handleSearch} className="flex gap-2">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted" />
                    <Input
                        className="pl-9"
                        placeholder="Search across connected apps (e.g. glucose, blood, open field, C57BL/6J)…"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                </div>
                <Button type="button" variant="outline" onClick={() => setShowSourcePicker((v) => !v)}>
                    <SlidersHorizontal className="h-4 w-4 mr-1" />
                    Sources ({selectedCodes.length}/{REFERENCE_SOURCES.length})
                </Button>
                <Button type="submit" disabled={query.trim().length === 0 || selectedCodes.length === 0 || isLoading}>
                    Search
                </Button>
            </form>

            {showSourcePicker && (
                <Card className="bg-surface-3 border-line">
                    <p className="text-xs font-semibold text-muted uppercase tracking-wide mb-3">Search these sources</p>
                    <div className="flex flex-wrap gap-2">
                        {REFERENCE_SOURCES.map((source) => {
                            const on = selectedCodes.includes(source.code)
                            return (
                                <button
                                    key={source.code}
                                    type="button"
                                    onClick={() => toggleSource(source.code)}
                                    aria-pressed={on}
                                    className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors ${
                                        on ? 'border-brand bg-brand/10 text-brand' : 'border-line bg-surface text-muted hover:text-ink'
                                    }`}
                                >
                                    <Network className="h-4 w-4" />
                                    {source.name}
                                </button>
                            )
                        })}
                    </div>
                    <p className="mt-3 text-xs text-muted">
                        These are public reference databases — always available, no credentials or activation needed.
                    </p>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Connected apps (ElabFTW, CEDAR, OSF, BioPortal) are also searched when configured.
                    </p>
                </Card>
            )}

            {!submittedQuery && (
                <EmptyState
                    title="Search the Reference Hub"
                    description="Enter a term to query all selected public reference databases. Select results to compare them side-by-side and import them into your library."
                />
            )}

            {isLoading && (
                <div className="space-y-2">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <Skeleton key={i} className="h-14 w-full rounded-xl" />
                    ))}
                </div>
            )}

            {isError && (
                <div className="rounded-xl border border-error/40 bg-error/10 px-4 py-3 text-sm text-error">
                    Search failed. One or more reference databases may be temporarily unreachable — try again.
                </div>
            )}

            {submittedQuery && results && !isLoading && !hasResults && (
                <EmptyState title="No results" description={`No reference resources matched "${submittedQuery}" in the selected sources.`} />
            )}

            {hasResults && semanticEnabled && (
                <div className="flex items-center gap-2">
                    <Button size="sm" variant="outline" onClick={() => bestMatchMutation.mutate(results ?? [])} disabled={bestMatchMutation.isPending}>
                        <Sparkles className="h-4 w-4 mr-1" /> {bestMatchMutation.isPending ? 'Ranking…' : 'Suggest best match'}
                    </Button>
                    {bestMatchId && <span className="text-xs text-muted">AI ranked the top match — highlighted below.</span>}
                </div>
            )}

            {hasResults && (
                <div className={`grid gap-4 ${openResult ? 'lg:grid-cols-2' : ''}`}>
                    <div className="space-y-5">
                        {grouped.map((group) => (
                            <div key={group.source}>
                                <div className="flex items-center gap-2 mb-2">
                                    <Badge variant="outline" className="text-xs">
                                        <Layers className="h-3 w-3 mr-1" />
                                        {group.sourceName}
                                    </Badge>
                                    <span className="text-xs text-muted">{group.items.length} result{group.items.length === 1 ? '' : 's'}</span>
                                </div>
                                <div className="space-y-2">
                                    {group.items.map((item) => (
                                        <ResultRow
                                            key={item.id}
                                            item={item}
                                            selectable
                                            checked={Boolean(selected[item.id])}
                                            onToggleSelect={() => toggleSelect(item)}
                                            open={openResult?.id === item.id}
                                            onOpen={() => setOpenResult((prev) => (prev?.id === item.id ? null : item))}
                                            bestMatch={bestMatchId === item.id ? { reason: bestMatchReason } : undefined}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>

                    {openResult && (
                        <Card className="bg-surface-3 border-line self-start sticky top-4">
                            <ResultDetail result={openResult} onClose={() => setOpenResult(null)} />
                        </Card>
                    )}
                </div>
            )}

            {selectedItems.length > 0 && (
                <div className="sticky bottom-4 z-10 mx-auto flex w-fit items-center gap-3 rounded-full border border-line bg-surface px-4 py-2 shadow-panel">
                    <span className="text-sm font-medium text-ink">{selectedItems.length} selected</span>
                    <Button size="sm" variant="outline" onClick={goToCompare} disabled={selectedItems.length < 2}>
                        <GitCompare className="h-4 w-4 mr-1" /> Compare
                    </Button>
                    <Button size="sm" onClick={() => importMutation.mutate(selectedItems)} disabled={importMutation.isPending}>
                        <Download className="h-4 w-4 mr-1" /> Import
                    </Button>
                    <button className="text-xs text-muted hover:text-ink" onClick={() => setSelected({})}>
                        Clear
                    </button>
                </div>
            )}
        </div>
    )
}

function LibraryView() {
    const queryClient = useQueryClient()
    const { toast } = useToast()
    const { isEnabled } = useFeatureFlagContext()
    const standardizeEnabled = isEnabled('feature.referenceHub.standardize')
    const semanticEnabled = isEnabled('feature.referenceHub.semantic')
    const [openResult, setOpenResult] = useState<ImportedReference | null>(null)
    const [similarFor, setSimilarFor] = useState<ImportedReference | null>(null)

    const { data: items, isLoading } = useQuery({ queryKey: ['imported-references'], queryFn: listImportedReferences })

    const deleteMutation = useMutation({
        mutationFn: (id: string) => deleteImportedReference(id),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['imported-references'] })
            setOpenResult(null)
            toast({ title: 'Removed from library' })
        },
    })

    const promoteMutation = useMutation({
        mutationFn: (id: string) => promoteReferenceToCde(id),
        onSuccess: (cde) => toast({ title: 'Promoted to CDE', description: `“${cde.label}” is now a reusable Common Data Element in the Crosswalk Studio.` }),
        onError: () => toast({ title: 'Promotion failed', description: 'Could not create a CDE from this reference. Try again.' }),
    })

    if (isLoading) {
        return (
            <div className="space-y-2">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Skeleton key={i} className="h-14 w-full rounded-xl" />
                ))}
            </div>
        )
    }

    if (!items || items.length === 0) {
        return <EmptyState title="Your library is empty" description="Search the Reference Hub and import resources to reuse them here, with their source links preserved." />
    }

    const grouped = groupBySource(items)

    return (
        <div className={`grid gap-4 ${openResult ? 'lg:grid-cols-2' : ''}`}>
            <div className="space-y-5">
                {grouped.map((group) => (
                    <div key={group.source}>
                        <div className="flex items-center gap-2 mb-2">
                            <Badge variant="outline" className="text-xs">
                                <Layers className="h-3 w-3 mr-1" />
                                {group.sourceName}
                            </Badge>
                            <span className="text-xs text-muted">{group.items.length} saved</span>
                        </div>
                        <div className="space-y-2">
                            {(group.items as ImportedReference[]).map((item) => (
                                <ResultRow
                                    key={item.id}
                                    item={item}
                                    open={openResult?.id === item.id}
                                    onOpen={() => setOpenResult((prev) => (prev?.id === item.id ? null : item))}
                                    onDelete={() => deleteMutation.mutate(item.id)}
                                    onPromote={standardizeEnabled && item.canonicalTermIri ? () => promoteMutation.mutate(item.id) : undefined}
                                    promoting={promoteMutation.isPending && promoteMutation.variables === item.id}
                                    onFindSimilar={semanticEnabled ? () => setSimilarFor(item) : undefined}
                                />
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            {openResult && (
                <Card className="bg-surface-3 border-line self-start sticky top-4">
                    <ResultDetail result={openResult} onClose={() => setOpenResult(null)} />
                </Card>
            )}

            {similarFor && <SimilarReferencesDialog reference={similarFor} onClose={() => setSimilarFor(null)} />}
        </div>
    )
}

function SimilarReferencesDialog({ reference, onClose }: { reference: ImportedReference; onClose: () => void }) {
    const { data, isLoading, isError } = useQuery({
        queryKey: ['similar-references', reference.id],
        queryFn: () => findSimilarReferences(reference.id, 8),
    })

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-label="Similar references">
            <Card className="bg-surface w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                <div className="flex items-center justify-between mb-3">
                    <div className="min-w-0">
                        <h3 className="font-display text-lg font-semibold text-ink truncate">Similar to “{reference.title}”</h3>
                        <p className="text-xs text-muted">Ranked by semantic similarity across your library.</p>
                    </div>
                    <button onClick={onClose} className="text-muted hover:text-ink shrink-0" aria-label="Close similar">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div className="overflow-y-auto flex-1 -mx-1 px-1" data-testid="similar-references-results">
                    {isLoading && (
                        <div className="space-y-2">
                            {Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-12 w-full rounded-xl" />)}
                        </div>
                    )}
                    {isError && <p className="text-sm text-error">Could not load similar references. Try again.</p>}
                    {data && data.length === 0 && (
                        <EmptyState title="No similar items" description="Import more references to build up the library — similarity needs neighbours to compare against." />
                    )}
                    {data && data.length > 0 && (
                        <div className="space-y-2">
                            {data.map((item) => <SimilarRow key={item.id} item={item} />)}
                        </div>
                    )}
                </div>

                <div className="flex justify-end pt-3">
                    <Button variant="outline" onClick={onClose}>Close</Button>
                </div>
            </Card>
        </div>
    )
}

function SimilarRow({ item }: { item: SimilarReference }) {
    return (
        <div className="flex items-start gap-3 rounded-xl border border-line bg-surface px-4 py-3">
            <Badge variant="outline" className="text-[10px] shrink-0 mt-0.5">{Math.round(item.score * 100)}%</Badge>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-medium text-sm text-ink truncate">{item.title}</span>
                    <Badge variant="outline" className="text-xs shrink-0">{TYPE_LABELS[item.type] ?? item.type}</Badge>
                    <span className="text-xs text-muted">{item.sourceName}</span>
                </div>
                {item.description && <p className="mt-1 text-xs text-muted line-clamp-2">{item.description}</p>}
            </div>
            {item.externalUrl && (
                <a href={item.externalUrl} target="_blank" rel="noreferrer" title="Open source of truth" className="text-muted hover:text-brand p-1 shrink-0">
                    <ExternalLink className="h-4 w-4" />
                </a>
            )}
        </div>
    )
}

interface ResultRowProps {
    item: ReferenceResult
    selectable?: boolean
    checked?: boolean
    onToggleSelect?: () => void
    open: boolean
    onOpen: () => void
    onDelete?: () => void
    onPromote?: () => void
    promoting?: boolean
    onFindSimilar?: () => void
    bestMatch?: { reason: string }
}

function ResultRow({ item, selectable, checked, onToggleSelect, open, onOpen, onDelete, onPromote, promoting, onFindSimilar, bestMatch }: ResultRowProps) {
    return (
        <div
            className={`w-full rounded-xl border px-4 py-3 transition-colors ${
                open ? 'border-brand bg-brand/5' : checked ? 'border-brand/60 bg-brand/5' : 'border-line bg-surface hover:border-brand hover:bg-brand/5'
            }`}
        >
            <div className="flex items-start gap-3">
                {selectable && (
                    <button
                        type="button"
                        role="checkbox"
                        aria-checked={checked}
                        aria-label={`Select ${item.title}`}
                        onClick={onToggleSelect}
                        className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border ${
                            checked ? 'border-brand bg-brand text-white' : 'border-line bg-surface'
                        }`}
                    >
                        {checked && <Check className="h-3 w-3" />}
                    </button>
                )}
                <button onClick={onOpen} className="text-left min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-medium text-sm text-ink truncate">{item.title}</span>
                        <Badge variant="outline" className={`text-xs shrink-0 ${TYPE_BADGE_VARIANT[item.type] ?? 'bg-gray-50 text-gray-600'}`}>
                            {TYPE_LABELS[item.type] ?? item.type}
                        </Badge>
                        {bestMatch && (
                            <span
                                title={bestMatch.reason}
                                className="inline-flex items-center gap-0.5 rounded-full bg-brand/10 px-2 py-0.5 text-[10px] font-medium text-brand shrink-0"
                            >
                                <Sparkles className="h-2.5 w-2.5" /> Best match
                            </span>
                        )}
                        {item.subtitle && <span className="text-xs text-muted">{item.subtitle}</span>}
                    </div>
                    {item.description && <p className="mt-1 text-xs text-muted line-clamp-2">{item.description}</p>}
                </button>
                <div className="flex items-center gap-1 shrink-0">
                    {item.externalUrl && (
                        <a
                            href={item.externalUrl}
                            target="_blank"
                            rel="noreferrer"
                            title="Open source of truth"
                            className="text-muted hover:text-brand p-1"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <ExternalLink className="h-4 w-4" />
                        </a>
                    )}
                    {onFindSimilar && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation()
                                onFindSimilar()
                            }}
                            title="Find similar references in your library"
                            aria-label={`Find references similar to ${item.title}`}
                            className="text-muted hover:text-brand p-1"
                        >
                            <Network className="h-4 w-4" />
                        </button>
                    )}
                    {onPromote && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation()
                                onPromote()
                            }}
                            disabled={promoting}
                            title="Promote to a reusable Common Data Element"
                            aria-label={`Promote ${item.title} to CDE`}
                            className="text-muted hover:text-brand p-1 disabled:opacity-50"
                        >
                            <Sparkles className="h-4 w-4" />
                        </button>
                    )}
                    {onDelete && (
                        <button onClick={onDelete} aria-label="Remove from library" className="text-muted hover:text-error p-1">
                            <Trash2 className="h-4 w-4" />
                        </button>
                    )}
                    <button onClick={onOpen} aria-label="Details" className="text-muted hover:text-ink p-1">
                        <ChevronRight className={`h-4 w-4 transition-transform ${open ? 'rotate-90' : ''}`} />
                    </button>
                </div>
            </div>
        </div>
    )
}

function ResultDetail({ result, onClose }: { result: ReferenceResult; onClose: () => void }) {
    const fields = Object.entries(result.raw ?? {}).filter(
        ([, value]) => value !== null && value !== undefined && value !== '' && typeof value !== 'object',
    )

    return (
        <div className="flex flex-col h-full">
            <div className="flex items-center justify-between mb-4">
                <h4 className="font-display font-semibold text-ink truncate mr-2">{result.title}</h4>
                <button onClick={onClose} className="text-muted hover:text-ink shrink-0" aria-label="Close detail">
                    <X className="h-4 w-4" />
                </button>
            </div>

            <div className="space-y-4 overflow-y-auto flex-1 text-sm">
                <div className="flex flex-wrap gap-2 items-center">
                    <Badge variant="outline">{result.sourceName}</Badge>
                    <Badge variant="outline">{TYPE_LABELS[result.type] ?? result.type}</Badge>
                    {result.externalUrl && (
                        <a href={result.externalUrl} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs text-brand hover:underline">
                            Source of truth <ExternalLink className="h-3 w-3" />
                        </a>
                    )}
                </div>

                {result.description && <p className="text-ink leading-relaxed">{result.description}</p>}

                {fields.length > 0 && (
                    <div className="rounded-xl border border-line overflow-hidden">
                        <table className="w-full text-xs">
                            <tbody>
                                {fields.map(([key, value]) => (
                                    <tr key={key} className="border-t border-line first:border-t-0">
                                        <td className="px-3 py-2 font-mono text-muted align-top whitespace-nowrap">{key}</td>
                                        <td className="px-3 py-2 text-ink break-words">{String(value)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    )
}

type SourceGroup = { source: string; sourceName: string; items: ReferenceResult[] }

function groupBySource(results: ReferenceResult[]): SourceGroup[] {
    const groups = new Map<string, SourceGroup>()
    for (const result of results) {
        const group = groups.get(result.source) ?? { source: result.source, sourceName: result.sourceName, items: [] }
        group.items.push(result)
        groups.set(result.source, group)
    }
    return Array.from(groups.values())
}
