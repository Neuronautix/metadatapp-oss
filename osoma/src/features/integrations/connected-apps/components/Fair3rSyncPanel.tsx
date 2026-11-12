import { useState, useEffect } from 'react'

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
    fetchInvestigationFdf,
    fetchInvestigationDataciteXml,
    validateFdfPayload,
    fetchFair3rDatasets,
    pushInvestigationToFair3r,
    getStudiesForInvestigation,
    type FdfValidationResult,
} from '../connected-apps.api.ts'
import { getInvestigations } from '@/features/core/investigations/investigation.api.ts'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { downloadBlob } from '@/lib/download.ts'
import {
    FileJson,
    FileText,
    CheckCircle2,
    XCircle,
    ArrowDownToLine,
    ArrowUpFromLine,
    RefreshCw,
    ExternalLink,
    Database,
    FlaskConical,
} from 'lucide-react'

interface Fair3rSyncPanelProps {
    /** The connected-app UUID, used to proxy push/list calls through the backend. */
    appId: string
    /** The validation.fair3r.fr base URL from the ConnectedApp config. */
    externalUrl?: string
    /** Called when the user selects a dataset from the "Datasets in FAIR3R" list. */
    onDatasetSelect?: (name: string) => void
}

export function Fair3rSyncPanel({ appId, externalUrl, onDatasetSelect }: Fair3rSyncPanelProps) {
    const { toast } = useToast()
    const queryClient = useQueryClient()
    const [selectedInvestigationId, setSelectedInvestigationId] = useState<string | null>(null)
    const [selectedDatasetName, setSelectedDatasetName] = useState<string | null>(null)
    const [selectedStudyIds, setSelectedStudyIds] = useState<string[]>([])
    const [validationResult, setValidationResult] = useState<FdfValidationResult | null>(null)
    const [fetchedFdf, setFetchedFdf] = useState<Record<string, unknown> | null>(null)
    const [confirmingPush, setConfirmingPush] = useState(false)
    const [lastPushResult, setLastPushResult] = useState<{ ckanName: string; packageUrl?: string; pushedAt: Date } | null>(null)

    const { data: investigations } = useQuery({
        queryKey: ['investigations'],
        queryFn: getInvestigations,
    })

    const { data: datasetsData, isLoading: datasetsLoading, isFetching: datasetsFetching, isError: datasetsError, refetch: refetchDatasets } = useQuery({
        queryKey: ['fair3r-datasets', appId],
        queryFn: () => fetchFair3rDatasets(appId),
        retry: false,
    })

    const investigationOptions = investigations?.data ?? []
    const resolvedId = selectedInvestigationId

    const { data: studiesData, isLoading: studiesLoading } = useQuery({
        queryKey: ['studies-for-investigation', resolvedId],
        queryFn: () => getStudiesForInvestigation(resolvedId!),
        enabled: !!resolvedId,
    })

    const studies = studiesData?.data ?? []

    const datasets = datasetsData?.datasets ?? []
    const resolvedDatasetName = selectedDatasetName ?? datasets[0] ?? null
    const refreshDatasetQueries = async () => {
        await refetchDatasets()
        if (resolvedDatasetName) {
            await queryClient.invalidateQueries({ queryKey: ['fair3r-dataset-details', appId, resolvedDatasetName] })
        }
    }

    const toggleStudy = (id: string) => {
        setSelectedStudyIds((current) =>
            current.includes(id) ? current.filter((s) => s !== id) : [...current, id]
        )
    }

    const allStudiesSelected = studies.length > 0 && studies.every((s) => selectedStudyIds.includes(s.id))
    const toggleAllStudies = () => {
        if (allStudiesSelected) {
            setSelectedStudyIds([])
        } else {
            setSelectedStudyIds(studies.map((s) => s.id))
        }
    }

    const handleInvestigationChange = (id: string) => {
        setSelectedInvestigationId(id)
        setSelectedStudyIds([])
    }

    // Fetch FDF JSON from Metadatapp
    const fetchFdfMutation = useMutation({
        mutationFn: () => {
            if (!resolvedId) throw new Error('Select an investigation first.')
            return fetchInvestigationFdf(resolvedId)
        },
        onSuccess: (data) => {
            setFetchedFdf(data)
            toast({ title: 'FDF JSON fetched', description: 'Dataset metadata loaded from Metadatapp.' })
        },
        onError: (err: Error) => {
            toast({ title: 'Fetch failed', description: err.message, variant: 'error' })
        },
    })

    // Validate the fetched FDF JSON payload
    const validateMutation = useMutation({
        mutationFn: () => {
            if (!fetchedFdf) throw new Error('Fetch the FDF JSON first.')
            return validateFdfPayload(fetchedFdf)
        },
        onSuccess: (result) => {
            setValidationResult(result)
            if (result.valid) {
                toast({ title: 'Validation passed', description: 'FDF JSON is schema-compliant.' })
            } else {
                toast({
                    title: 'Validation failed',
                    description: `${result.errors.length} issue(s) found.`,
                    variant: 'error',
                })
            }
        },
        onError: (err: Error) => {
            toast({ title: 'Validation error', description: err.message, variant: 'error' })
        },
    })

    // Download DataCite XML
    const dataciteMutation = useMutation({
        mutationFn: () => {
            if (!resolvedId) throw new Error('Select an investigation first.')
            return fetchInvestigationDataciteXml(resolvedId)
        },
        onSuccess: (blob) => {
            downloadBlob(blob, `investigation-${resolvedId}-datacite.xml`)
            toast({ title: 'DataCite XML downloaded', description: 'Compatible with DOI registration agencies.' })
        },
        onError: (err: Error) => {
            toast({ title: 'Download failed', description: err.message, variant: 'error' })
        },
    })

    // Push investigation to FAIR3R via backend API
    const pushFdfMutation = useMutation({
        mutationFn: () => {
            if (!resolvedId) throw new Error('Select an investigation first.')
            return pushInvestigationToFair3r(appId, resolvedId, resolvedDatasetName ?? undefined, selectedStudyIds)
        },
        onSuccess: (pushResult) => {
            setValidationResult(null)
            setConfirmingPush(false)
            setLastPushResult({
                ckanName: pushResult.ckanName,
                packageUrl: pushResult.packageUrl ?? undefined,
                pushedAt: new Date(),
            })
            toast({
                title: 'Pushed to FAIR3R',
                description: pushResult.packageUrl
                    ? `Dataset published: ${pushResult.ckanName}`
                    : `Dataset published (ID: ${pushResult.ckanName})`,
            })
            setSelectedDatasetName(pushResult.ckanName)
            onDatasetSelect?.(pushResult.ckanName)
            queryClient.invalidateQueries({ queryKey: ['fair3r-datasets', appId] })
            queryClient.invalidateQueries({ queryKey: ['fair3r-dataset-details', appId, pushResult.ckanName] })
        },
        onError: (err: Error) => {
            setConfirmingPush(false)
            toast({ title: 'Push failed', description: err.message, variant: 'error' })
        },
    })

    useEffect(() => {
        if (!confirmingPush) return
        const timer = setTimeout(() => setConfirmingPush(false), 4000)
        return () => clearTimeout(timer)
    }, [confirmingPush])

    const noInvestigation = !resolvedId

    return (
        <div className="space-y-5">
            {/* FAIR3R datasets list */}
            <div>
                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <div className="flex min-w-0 flex-1 items-center gap-2">
                        <Database className="h-3.5 w-3.5 text-muted" />
                        <span className="text-xs font-bold uppercase tracking-widest text-muted">
                            Datasets in FAIR3R
                        </span>
                        {!datasetsLoading && (
                            <Badge variant="outline" className="text-xs">
                                {datasets.length}
                            </Badge>
                        )}
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 px-2 text-xs"
                        disabled={datasetsFetching}
                        onClick={() => void refreshDatasetQueries()}
                    >
                        <RefreshCw className={`mr-1.5 h-3.5 w-3.5 ${datasetsFetching ? 'animate-spin' : ''}`} />
                        Refresh
                    </Button>
                </div>
                {datasetsLoading ? (
                    <p className="text-xs text-muted">Loading…</p>
                ) : datasetsError ? (
                    <p className="text-xs text-error">Unable to load datasets — check connection and token.</p>
                ) : datasets.length === 0 ? (
                    <p className="text-xs text-muted">No datasets found.</p>
                ) : (
                    <div className="grid max-h-44 gap-2 overflow-y-auto sm:grid-cols-2">
                        {datasets.map((name) => (
                            <div
                                key={name}
                                className={`flex min-w-0 items-center gap-2 rounded-lg border p-2 transition ${resolvedDatasetName === name ? 'border-brand/50 bg-brand/10' : 'border-line bg-surface hover:border-brand/30'}`}
                            >
                                <button
                                    type="button"
                                    className={`min-w-0 flex-1 truncate text-left text-xs font-semibold ${resolvedDatasetName === name ? 'text-brand' : 'text-ink'}`}
                                    onClick={() => {
                                        setSelectedDatasetName(name)
                                        onDatasetSelect?.(name)
                                    }}
                                >
                                    {name}
                                </button>
                                {externalUrl && (
                                    <a
                                        href={`${externalUrl.replace(/\/$/, '')}/dataset/${name}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-muted hover:text-ink"
                                        aria-label={`Open ${name} in FAIR3R`}
                                    >
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Selected dataset row */}
            {resolvedDatasetName && (
                <div className="rounded-lg border border-line bg-surface px-3 py-2 text-xs text-muted">
                    <span className="font-semibold text-ink">Selected dataset:</span>{' '}
                    {externalUrl ? (
                        <a
                            href={`${externalUrl.replace(/\/$/, '')}/dataset/${resolvedDatasetName}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-link hover:text-link/80 inline-flex items-center gap-1"
                        >
                            {resolvedDatasetName}
                            <ExternalLink className="h-3 w-3" />
                        </a>
                    ) : (
                        <span className="text-ink">{resolvedDatasetName}</span>
                    )}
                </div>
            )}

            <div className="border-t border-line pt-4">
                <div className="flex items-center gap-2 mb-3">
                    <span className="text-sm font-semibold text-ink">Target investigation</span>
                    <Badge variant="outline" className="text-xs">
                        DataCite 4.x / FAIR3R v3.0
                    </Badge>
                </div>

                {investigationOptions.length > 0 ? (
                    <div className="flex items-center gap-2">
                        <select
                            className="flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand"
                            value={resolvedId ?? ''}
                            onChange={(e) => handleInvestigationChange(e.target.value)}
                        >
                            <option value="" disabled>Select an investigation…</option>
                            {investigationOptions.map((inv) => (
                                <option key={inv.id} value={inv.id}>
                                    {inv.name}
                                </option>
                            ))}
                        </select>
                        <Button
                            variant="outline"
                            size="sm"
                            className="shrink-0 text-xs"
                            disabled={fetchFdfMutation.isPending || noInvestigation}
                            onClick={() => fetchFdfMutation.mutate()}
                        >
                            <ArrowDownToLine className="mr-1.5 h-3 w-3" />
                            {fetchFdfMutation.isPending ? 'Fetching…' : 'Preview FDF'}
                        </Button>
                    </div>
                ) : (
                    <p className="text-xs text-muted">No investigations found.</p>
                )}
            </div>

            {/* Studies in this investigation */}
            {resolvedId && (
                <div className="border-t border-line pt-4">
                    <div className="flex items-center justify-between gap-3 mb-3">
                        <div className="flex items-center gap-2">
                            <FlaskConical className="h-3.5 w-3.5 text-muted" />
                            <span className="text-sm font-semibold text-ink">Studies in this investigation</span>
                        </div>
                        {studies.length > 0 && (
                            <div className="flex items-center gap-2">
                                <Badge variant="outline" className="text-xs">
                                    {selectedStudyIds.length}/{studies.length}
                                </Badge>
                                <button
                                    type="button"
                                    className="text-xs text-link hover:text-link/80"
                                    onClick={toggleAllStudies}
                                >
                                    {allStudiesSelected ? 'Deselect all' : 'Select all'}
                                </button>
                            </div>
                        )}
                    </div>

                    {studiesLoading ? (
                        <p className="text-xs text-muted">Loading studies…</p>
                    ) : studies.length === 0 ? (
                        <p className="text-xs text-muted">No studies found for this investigation.</p>
                    ) : (
                        <ul className="space-y-1.5 max-h-48 overflow-y-auto">
                            {studies.map((study) => {
                                const checked = selectedStudyIds.includes(study.id)
                                return (
                                    <li key={study.id}>
                                        <label className={`flex items-start gap-3 cursor-pointer rounded-lg border p-2.5 transition ${checked ? 'border-brand/40 bg-brand/10' : 'border-line bg-surface hover:border-brand/30'}`}>
                                            <input
                                                type="checkbox"
                                                className="mt-0.5 h-4 w-4 rounded border-line bg-surface text-brand focus:ring-brand flex-shrink-0"
                                                checked={checked}
                                                onChange={() => toggleStudy(study.id)}
                                            />
                                            <span className="min-w-0">
                                                <span className="block text-xs font-semibold text-ink truncate">
                                                    {study.name}
                                                </span>
                                                <span className="block text-xs text-muted">
                                                    {study.assayName}
                                                    {study.status && (
                                                        <span className="ml-2 capitalize">{study.status}</span>
                                                    )}
                                                </span>
                                            </span>
                                        </label>
                                    </li>
                                )
                            })}
                        </ul>
                    )}
                </div>
            )}

            <div className="space-y-2">
                <div>
                    <Button
                        className="w-full"
                        disabled={pushFdfMutation.isPending || noInvestigation}
                        onClick={() => {
                            if (!confirmingPush) {
                                setConfirmingPush(true)
                            } else {
                                setConfirmingPush(false)
                                pushFdfMutation.mutate()
                            }
                        }}
                    >
                        <ArrowUpFromLine className="mr-2 h-4 w-4" />
                        {pushFdfMutation.isPending ? 'Pushing…' : confirmingPush ? 'Confirm push →' : 'Push to FAIR3R'}
                    </Button>
                    {fetchedFdf && (
                        validationResult?.valid === true ? (
                            <p className="mt-1 flex items-center gap-1 text-xs text-success">
                                <CheckCircle2 className="h-3 w-3" />
                                Schema valid ✓
                            </p>
                        ) : (
                            <p className="mt-1 text-xs text-muted">Tip: validate the FDF before pushing</p>
                        )
                    )}
                </div>

                <Button
                    variant="outline"
                    className="w-full"
                    disabled={dataciteMutation.isPending || noInvestigation}
                    onClick={() => dataciteMutation.mutate()}
                >
                    <FileText className="mr-2 h-4 w-4" />
                    {dataciteMutation.isPending ? 'Generating…' : 'Export DataCite XML'}
                </Button>

                <Button
                    variant="outline"
                    className="w-full"
                    disabled={validateMutation.isPending || !fetchedFdf}
                    onClick={() => validateMutation.mutate()}
                >
                    <RefreshCw className="mr-2 h-4 w-4" />
                    {validateMutation.isPending ? 'Validating…' : 'Verify (semantic)'}
                </Button>
            </div>

            {lastPushResult && (
                <div className="rounded-xl border border-emerald-500/40 bg-emerald-950/40 p-3 text-xs">
                    <div className="flex items-center gap-2 text-emerald-300 font-semibold">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        Last published: {lastPushResult.ckanName}
                    </div>
                    {lastPushResult.packageUrl && (
                        <a
                            href={lastPushResult.packageUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-1 flex items-center gap-1 text-indigo-400 hover:text-indigo-300"
                        >
                            View on FAIR3R <ExternalLink className="h-3 w-3" />
                        </a>
                    )}
                    <p className="mt-1 text-slate-400">{lastPushResult.pushedAt.toLocaleString()}</p>
                </div>
            )}

            {/* Validation result */}
            {validationResult && (
                <div className={`rounded-xl border p-3 text-xs ${validationResult.valid ? 'border-success/40 bg-success/10' : 'border-error/40 bg-error/10'}`}>
                    <div className="flex items-center gap-2 font-semibold">
                        {validationResult.valid ? (
                            <CheckCircle2 className="h-4 w-4 text-success" />
                        ) : (
                            <XCircle className="h-4 w-4 text-error" />
                        )}
                        <span className={validationResult.valid ? 'text-success' : 'text-error'}>
                            {validationResult.valid ? 'Schema-valid FDF JSON' : `${validationResult.errors.length} validation error(s)`}
                        </span>
                    </div>
                    {validationResult.errors.length > 0 && (
                        <ul className="mt-2 space-y-1 pl-6 text-error list-disc">
                            {validationResult.errors.map((e, i) => (
                                <li key={i}>{e}</li>
                            ))}
                        </ul>
                    )}
                    {validationResult.schema && (
                        <p className="mt-2 text-muted">
                            Schema:{' '}
                            <a href={validationResult.schema} target="_blank" rel="noopener noreferrer" className="underline text-link">
                                {validationResult.schema}
                            </a>
                        </p>
                    )}
                </div>
            )}

            {fetchedFdf && (
                <div className="rounded-xl border border-line bg-surface p-3 space-y-2">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs font-semibold text-ink flex items-center gap-1.5">
                            <FileJson className="h-3.5 w-3.5" />
                            FDF Preview
                        </span>
                        {externalUrl && (
                            <a
                                href={externalUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-1 text-xs text-link hover:text-link/80"
                            >
                                Open FAIR3R
                                <ExternalLink className="h-3 w-3" />
                            </a>
                        )}
                    </div>

                    {(['title', 'identifier', 'schemaVersion', 'version'] as const).map(key => {
                        const val = fetchedFdf?.[key]
                        if (!val) return null
                        return (
                            <div key={key} className="flex justify-between text-xs gap-2">
                                <span className="text-muted shrink-0">{key}</span>
                                <span className="text-ink truncate text-right">{String(val)}</span>
                            </div>
                        )
                    })}

                    {Array.isArray(fetchedFdf?.['creators']) && (
                        <div className="flex justify-between text-xs gap-2">
                            <span className="text-muted shrink-0">creators</span>
                            <span className="text-ink">{(fetchedFdf['creators'] as unknown[]).length} listed</span>
                        </div>
                    )}

                    <Button
                        variant="outline"
                        size="sm"
                        className="w-full mt-2 text-xs"
                        onClick={() => downloadBlob(
                            new Blob([JSON.stringify(fetchedFdf, null, 2)], { type: 'application/json' }),
                            'fdf-investigation.json'
                        )}
                    >
                        <ArrowDownToLine className="mr-1.5 h-3 w-3" />
                        Download full FDF JSON
                    </Button>
                </div>
            )}
        </div>
    )
}
