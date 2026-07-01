import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { ExternalLink, Search } from 'lucide-react'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { formatDate } from '@/lib/format.ts'
import { getInvestigations } from '@/features/core/investigations/investigation.api.ts'
import { getStudies } from '@/features/core/studies/study.api.ts'
import {
    fetchPreclinicalTrialsProtocols,
    importPreclinicalTrialsProtocol,
    type PreclinicalTrialsImportTarget,
    type PreclinicalTrialsProtocolSummary,
} from '../connected-apps.api.ts'

type TargetMode = PreclinicalTrialsImportTarget['target']
type SortField = 'id' | 'title' | 'registrationDate' | 'startDate' | 'researchField' | 'species'

const sortLabels: Record<SortField, string> = {
    id: 'PCT ID',
    title: 'Title',
    registrationDate: 'Registered',
    startDate: 'Start',
    researchField: 'Field',
    species: 'Species',
}

export function PreclinicalTrialsProtocolPanel({ appId }: { appId: string }) {
    const queryClient = useQueryClient()
    const { toast } = useToast()
    const [search, setSearch] = useState('')
    const [sortField, setSortField] = useState<SortField>('registrationDate')
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc')
    const [targetMode, setTargetMode] = useState<TargetMode>('new-investigation')
    const [investigationId, setInvestigationId] = useState('')
    const [studyId, setStudyId] = useState('')

    const protocolsQuery = useQuery({
        queryKey: ['connected-apps', appId, 'preclinicaltrials', 'protocols'],
        queryFn: () => fetchPreclinicalTrialsProtocols(appId),
    })
    const investigationsQuery = useQuery({
        queryKey: ['investigations'],
        queryFn: getInvestigations,
    })
    const studiesQuery = useQuery({
        queryKey: ['studies', 'preclinicaltrials-link-targets'],
        queryFn: () => getStudies({ pageSize: 200 }),
    })

    const importMutation = useMutation({
        mutationFn: ({ protocolId, target }: { protocolId: string; target: PreclinicalTrialsImportTarget }) =>
            importPreclinicalTrialsProtocol(appId, protocolId, target),
        onSuccess: (result) => {
            toast({
                title: result.target.type === 'study' ? 'Protocol linked' : 'Protocol imported',
                description: result.target.name,
            })
            queryClient.invalidateQueries({ queryKey: ['connected-apps', appId, 'preclinicaltrials', 'protocols'] })
            queryClient.invalidateQueries({ queryKey: ['connected-apps', appId] })
            queryClient.invalidateQueries({ queryKey: ['investigations'] })
            queryClient.invalidateQueries({ queryKey: ['studies'] })
        },
        onError: (err: Error) => {
            toast({
                title: 'Protocol action failed',
                description: err.message,
                variant: 'error',
            })
        },
    })

    const protocols = useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase()
        const filtered = (protocolsQuery.data?.protocols ?? []).filter((protocol) => {
            if (!normalizedSearch) return true
            return [
                protocol.id,
                protocol.title,
                protocol.researchField,
                protocol.species,
                protocol.strain,
                protocol.status,
            ].some((value) => (value ?? '').toLowerCase().includes(normalizedSearch))
        })

        return [...filtered].sort((left, right) => {
            const leftValue = String(left[sortField] ?? '')
            const rightValue = String(right[sortField] ?? '')
            const result = leftValue.localeCompare(rightValue, undefined, { numeric: true, sensitivity: 'base' })
            return sortDirection === 'asc' ? result : -result
        })
    }, [protocolsQuery.data?.protocols, search, sortDirection, sortField])

    const selectedTarget = (): PreclinicalTrialsImportTarget | null => {
        if (targetMode === 'new-investigation') return { target: 'new-investigation' }
        if (targetMode === 'existing-investigation' && investigationId) {
            return { target: 'existing-investigation', investigationId }
        }
        if (targetMode === 'existing-study' && studyId) return { target: 'existing-study', studyId }
        return null
    }

    const runAction = (protocol: PreclinicalTrialsProtocolSummary) => {
        const target = selectedTarget()
        if (!target) {
            toast({
                title: 'Choose a destination',
                description: targetMode === 'existing-study' ? 'Select a study before linking.' : 'Select an investigation before linking.',
                variant: 'error',
            })
            return
        }
        importMutation.mutate({ protocolId: protocol.id, target })
    }

    const toggleSort = (field: SortField) => {
        if (sortField === field) {
            setSortDirection((current) => current === 'asc' ? 'desc' : 'asc')
            return
        }
        setSortField(field)
        setSortDirection(field === 'registrationDate' || field === 'startDate' ? 'desc' : 'asc')
    }

    return (
        <Card className="border-line bg-surface-3">
            <div className="flex flex-col gap-4 border-b border-line pb-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 className="font-display text-lg font-semibold text-ink">PreclinicalTrials.eu Protocols</h3>
                    <p className="mt-1 text-sm text-muted">Select a published protocol, then import it as a new investigation or link it to existing Metadatapp work.</p>
                </div>
                <Button variant="outline" onClick={() => protocolsQuery.refetch()} disabled={protocolsQuery.isFetching}>
                    {protocolsQuery.isFetching ? 'Refreshing...' : 'Refresh Protocols'}
                </Button>
            </div>

            <div className="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_220px]">
                <div className="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2">
                    <Search className="h-4 w-4 text-muted" />
                    <input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search PCT ID, title, field, species"
                        className="h-8 min-w-0 flex-1 bg-transparent text-sm text-ink outline-none"
                    />
                </div>
                <select
                    value={targetMode}
                    onChange={(event) => setTargetMode(event.target.value as TargetMode)}
                    className="h-12 rounded-xl border border-line bg-surface px-3 text-sm text-ink"
                >
                    <option value="new-investigation">Import as new investigation</option>
                    <option value="existing-investigation">Sync to existing investigation</option>
                    <option value="existing-study">Link to existing study</option>
                </select>
                {targetMode === 'existing-investigation' ? (
                    <select
                        value={investigationId}
                        onChange={(event) => setInvestigationId(event.target.value)}
                        className="h-12 rounded-xl border border-line bg-surface px-3 text-sm text-ink"
                    >
                        <option value="">Select investigation</option>
                        {(investigationsQuery.data?.data ?? []).map((investigation) => (
                            <option key={investigation.id} value={investigation.id}>{investigation.name}</option>
                        ))}
                    </select>
                ) : targetMode === 'existing-study' ? (
                    <select
                        value={studyId}
                        onChange={(event) => setStudyId(event.target.value)}
                        className="h-12 rounded-xl border border-line bg-surface px-3 text-sm text-ink"
                    >
                        <option value="">Select study</option>
                        {(studiesQuery.data?.data ?? []).map((study) => (
                            <option key={study.id} value={study.id}>{study.name}</option>
                        ))}
                    </select>
                ) : (
                    <Button variant="outline" asChild className="h-12">
                        <Link to="/investigations">View Investigations</Link>
                    </Button>
                )}
            </div>

            <div className="mt-4 overflow-x-auto rounded-xl border border-line bg-white">
                {protocolsQuery.isLoading ? (
                    <div className="space-y-2 p-4">
                        {Array.from({ length: 6 }).map((_, index) => (
                            <Skeleton key={index} className="h-12 w-full" />
                        ))}
                    </div>
                ) : protocolsQuery.isError ? (
                    <div className="p-4 text-sm text-error">{protocolsQuery.error.message}</div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {(['id', 'title', 'registrationDate', 'startDate', 'researchField', 'species'] as SortField[]).map((field) => (
                                    <TableHead key={field}>
                                        <button type="button" className="flex items-center gap-2 text-left" onClick={() => toggleSort(field)}>
                                            {sortLabels[field]}
                                            <span className="text-[10px] text-muted">{sortField === field ? sortDirection : 'sort'}</span>
                                        </button>
                                    </TableHead>
                                ))}
                                <TableHead>Status</TableHead>
                                <TableHead>Linked</TableHead>
                                <TableHead>Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {protocols.slice(0, 100).map((protocol) => (
                                <TableRow key={protocol.id}>
                                    <TableCell className="font-mono text-xs">{protocol.id}</TableCell>
                                    <TableCell className="min-w-80">
                                        <div className="flex items-start gap-2">
                                            <span className="font-medium text-ink">{protocol.title ?? 'Untitled protocol'}</span>
                                            {protocol.repositoryLink && (
                                                <a href={protocol.repositoryLink} target="_blank" rel="noopener noreferrer" className="mt-0.5 text-muted hover:text-ink">
                                                    <ExternalLink className="h-3.5 w-3.5" />
                                                </a>
                                            )}
                                        </div>
                                        <p className="mt-1 text-xs text-muted">{protocol.strain ?? protocol.sex ?? ''}</p>
                                    </TableCell>
                                    <TableCell>{protocol.registrationDate ? formatDate(protocol.registrationDate) : 'N/A'}</TableCell>
                                    <TableCell>{protocol.startDate ? formatDate(protocol.startDate) : 'N/A'}</TableCell>
                                    <TableCell>{protocol.researchField ?? 'N/A'}</TableCell>
                                    <TableCell>{protocol.species ?? 'N/A'}</TableCell>
                                    <TableCell><Badge variant="outline">{protocol.status ?? 'unknown'}</Badge></TableCell>
                                    <TableCell>
                                        <div className="flex flex-col gap-1 text-xs text-muted">
                                            {protocol.linkedInvestigation ? <span>Investigation: {protocol.linkedInvestigation.name}</span> : null}
                                            {protocol.linkedStudy ? <span>Study: {protocol.linkedStudy.name}</span> : null}
                                            {!protocol.linkedInvestigation && !protocol.linkedStudy ? <span>Not linked</span> : null}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            size="sm"
                                            onClick={() => runAction(protocol)}
                                            disabled={importMutation.isPending}
                                        >
                                            {targetMode === 'existing-study' ? 'Link' : targetMode === 'existing-investigation' ? 'Sync' : 'Import'}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
            {protocols.length > 100 && (
                <p className="mt-3 text-xs text-muted">Showing first 100 matching protocols. Refine search to narrow the list.</p>
            )}
        </Card>
    )
}
