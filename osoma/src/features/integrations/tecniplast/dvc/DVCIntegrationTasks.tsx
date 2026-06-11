import { useEffect, useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Check, AlertCircle, Download, RefreshCw, FileText, ArrowRight, Eye, EyeOff, KeyRound, ShieldCheck } from 'lucide-react'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import {
    testApiKey,
    getMetrics,
    searchCages,
    searchAnimals,
    submitTask,
    getTaskState,
    type Metric,
    type GlobResult,
    TaskAggregationType,
    TaskPartitionType,
    TaskStateEnum,
    type DvcTestApiKeyResponse,
} from './dvc.integration.api.ts'
import { updateConnectedApp } from '../../connected-apps/connected-apps.api.ts'
import { type DvcExtractionJob, upsertDvcExtractionJobs } from './dvc.job-history.ts'
import { downloadTaskZip, extractTaskForVisualization } from './dvc.task-results.ts'
import { buildDvcSubmissionPlan, createQueuedJobFromSubmission, type TargetKind, type WindowMode } from './dvc.submission-plan.ts'

const DEFAULT_TEST_CAGES = [
    'S81P-40332',
    'S81P-40287',
    'S81P-40648',
]

const padDatePart = (value: number) => String(value).padStart(2, '0')

const toDateTimeLocal = (value: string | null | undefined) => {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    return `${date.getFullYear()}-${padDatePart(date.getMonth() + 1)}-${padDatePart(date.getDate())}T${padDatePart(date.getHours())}:${padDatePart(date.getMinutes())}:${padDatePart(date.getSeconds())}`
}

const formatWindowLabel = (start: string, stop: string) =>
    `${new Date(start).toLocaleDateString()} -> ${new Date(stop).toLocaleDateString()}`

interface DVCIntegrationTasksProps {
    appId: string
    tokenHint?: string
    onDataExtracted?: (metrics: string[], cages: string[], rowCount: number) => void
}

export function DVCIntegrationTasks({ appId, tokenHint, onDataExtracted }: DVCIntegrationTasksProps) {
    const queryClient = useQueryClient()
    const { toast } = useToast()
    const debugLog = (message: string, payload?: unknown) => {
        if (!import.meta.env.DEV) {
            return
        }

        console.debug(`[DVCIntegrationTasks] ${message}`, payload)
    }

    // --- Connection State ---
    const [isProxyValid, setIsProxyValid] = useState<boolean | null>(null)
    const [isTestingProxy, setIsTestingProxy] = useState(true)
    const [proxyError, setProxyError] = useState<string | null>(null)
    const [lastTestResult, setLastTestResult] = useState<DvcTestApiKeyResponse | null>(null)
    const [lastTestedAt, setLastTestedAt] = useState<string | null>(null)
    const [storedTokenHint, setStoredTokenHint] = useState<string | undefined>(tokenHint)
    const [draftApiKey, setDraftApiKey] = useState('')

    // --- Wizard State ---
    const [step, setStep] = useState<1 | 2 | 3 | 4>(1)

    // --- Step 1: Input State ---
    const [targetKind, setTargetKind] = useState<TargetKind>('CAGE')
    const [cagesInput, setCagesInput] = useState(DEFAULT_TEST_CAGES.join('\n'))
    const [isValidating, setIsValidating] = useState(false)
    const [validCages, setValidCages] = useState<GlobResult[]>([])
    const [selectedTargetIds, setSelectedTargetIds] = useState<string[]>([])
    const [minDate, setMinDate] = useState<string>('')
    const [maxDate, setMaxDate] = useState<string>('')
    const [windowMode, setWindowMode] = useState<WindowMode>('MAX_PER_TARGET')

    // --- Step 2: Config State ---
    const [metrics, setMetrics] = useState<Metric[]>([])
    const [metricsError, setMetricsError] = useState<string | null>(null)
    const [isRefreshingMetrics, setIsRefreshingMetrics] = useState(false)
    const [metricsVisible, setMetricsVisible] = useState(false)
    const [metricsLastLoadedAt, setMetricsLastLoadedAt] = useState<string | null>(null)
    const [selectedMetrics, setSelectedMetrics] = useState<string[]>([])
    const [startDate, setStartDate] = useState('')
    const [endDate, setEndDate] = useState('')
    const [filename, setFilename] = useState('dvc-export')
    const [aggregation, setAggregation] = useState<TaskAggregationType>(TaskAggregationType.HOUR)
    const [partition, setPartition] = useState<TaskPartitionType>(TaskPartitionType.SINGLE_FILE_BY_CAGE)
    const [dateWarning, setDateWarning] = useState<string | null>(null)

    // --- Step 3 & 4: Submission State ---
    const [isSubmitting, setIsSubmitting] = useState(false)
    const [tasks, setTasks] = useState<DvcExtractionJob[]>([])
    const [isPiping, setIsPiping] = useState<number | null>(null)
    const [isDownloadingTask, setIsDownloadingTask] = useState<number | null>(null)
    const [isPollingQueue, setIsPollingQueue] = useState(false)
    const [lastQueueCheckAt, setLastQueueCheckAt] = useState<string | null>(null)
    const [submitError, setSubmitError] = useState<string | null>(null)
    const queuePollInFlightRef = useRef(false)

    // --- Effects ---

    const runProxyTest = async () => {
        setIsTestingProxy(true)
        setProxyError(null)
        try {
            const result = await testApiKey(appId)
            debugLog('test-api-key response', result)
            setLastTestResult(result)
            setLastTestedAt(new Date().toISOString())
            setIsProxyValid(true)

            return result
        } catch (e) {
            console.error(e)
            setLastTestResult(null)
            setProxyError(e instanceof Error ? e.message : 'Unknown connection error')
            setIsProxyValid(false)
            throw e
        } finally {
            setIsTestingProxy(false)
        }
    }

    const runMetricsRefresh = async () => {
        setIsRefreshingMetrics(true)
        setMetricsError(null)
        try {
            const result = await getMetrics(appId)
            debugLog('metrics response', result)
            setMetrics(result)
            setMetricsLastLoadedAt(new Date().toISOString())
            return result
        } catch (e) {
            console.error(e)
            setMetricsError(e instanceof Error ? e.message : 'Unable to load metrics.')
            throw e
        } finally {
            setIsRefreshingMetrics(false)
        }
    }

    useEffect(() => {
        setStoredTokenHint(tokenHint)
    }, [tokenHint])

    useEffect(() => {
        // The test routine is intentionally re-triggered when the app identity or stored hint changes.
        void runProxyTest()
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [appId, tokenHint])

    useEffect(() => {
        if (isProxyValid) {
            void runMetricsRefresh()
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isProxyValid, appId, tokenHint])

    // Validate 90 days range limit
    useEffect(() => {
        if (startDate && endDate) {
            const start = new Date(startDate).getTime()
            const end = new Date(endDate).getTime()
            const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24))
            if (diffDays > 90) {
                setDateWarning(`Selected range is ${diffDays} days. Requests >90 days may be rejected by DVC.`)
            } else {
                setDateWarning(null)
            }
        }
    }, [startDate, endDate])

    useEffect(() => {
        if (!tasks.length) {
            return
        }

        upsertDvcExtractionJobs(tasks)
    }, [tasks])

    const activeTaskIds = tasks
        .filter((task) =>
            typeof task.id === 'number' &&
            Number.isFinite(task.id) &&
            (task.state === TaskStateEnum.SUBMITTED || task.state === TaskStateEnum.RUNNING)
        )
        .map((task) => task.id)
    const activeTaskIdsKey = activeTaskIds.join(',')

    // Polling Queue
    useEffect(() => {
        if (!activeTaskIdsKey || !isProxyValid) return

        const activeIds = activeTaskIdsKey
            .split(',')
            .map((value) => Number(value))
            .filter((value) => Number.isFinite(value))

        let isCancelled = false

        const pollQueue = async () => {
            if (isCancelled || queuePollInFlightRef.current) {
                return
            }

            queuePollInFlightRef.current = true
            setIsPollingQueue(true)
            const checkedAt = new Date().toISOString()

            try {
                const results = await Promise.allSettled(
                    activeIds.map(async (taskId) => {
                        const states = await getTaskState(appId, taskId)
                        return {
                            taskId,
                            nextState: states[0] ?? null,
                        }
                    })
                )

                if (isCancelled) {
                    return
                }

                setTasks((prev) => prev.map((task) => {
                    const result = results.find((entry) => entry.status === 'fulfilled' && entry.value.taskId === task.id)
                    if (!result || result.status !== 'fulfilled') {
                        return activeIds.includes(task.id)
                            ? { ...task, lastCheckedAt: checkedAt }
                            : task
                    }

                    return result.value.nextState
                        ? { ...task, ...result.value.nextState, lastCheckedAt: checkedAt }
                        : { ...task, lastCheckedAt: checkedAt }
                }))
                setLastQueueCheckAt(checkedAt)
            } finally {
                queuePollInFlightRef.current = false
                setIsPollingQueue(false)
            }
        }

        void pollQueue()
        const interval = setInterval(() => {
            void pollQueue()
        }, 5000)

        return () => {
            isCancelled = true
            clearInterval(interval)
            queuePollInFlightRef.current = false
        }
    }, [activeTaskIdsKey, appId, isProxyValid])

    const saveKeyMutation = useMutation({
        mutationFn: async () => {
            const nextKey = draftApiKey.trim()

            if (!nextKey) {
                throw new Error('Enter a new API key before saving.')
            }

            return updateConnectedApp(appId, { token: nextKey })
        },
        onSuccess: async (updatedApp) => {
            setDraftApiKey('')
            setStoredTokenHint(updatedApp.tokenHint || storedTokenHint)
            queryClient.invalidateQueries({ queryKey: ['connected-apps'] })
            queryClient.invalidateQueries({ queryKey: ['connected-apps', appId] })

            toast({
                title: 'API key saved',
                description: 'The Tecniplast key was updated. Running a live connectivity test now.',
            })

            try {
                await runProxyTest()
                await runMetricsRefresh()
                toast({
                    title: 'Connection healthy',
                    description: 'Tecniplast responded successfully with the updated key.',
                })
            } catch (error) {
                toast({
                    title: 'Key saved but test failed',
                    description: error instanceof Error ? error.message : 'Unable to validate the updated key.',
                    variant: 'error',
                })
            }
        },
        onError: (error: Error) => {
            toast({
                title: 'Failed to save API key',
                description: error.message,
                variant: 'error',
            })
        },
    })

    // --- Handlers ---

    const handleRetestConnection = async () => {
        try {
            await runProxyTest()
            toast({
                title: 'Connection checked',
                description: 'Tecniplast responded successfully.',
            })
        } catch (error) {
            toast({
                title: 'Connection check failed',
                description: error instanceof Error ? error.message : 'Unable to validate the current API key.',
                variant: 'error',
            })
        }
    }

    const handleToggleMetrics = async () => {
        const nextVisible = !metricsVisible
        setMetricsVisible(nextVisible)

        if (nextVisible && metrics.length === 0 && isProxyValid) {
            try {
                await runMetricsRefresh()
            } catch {
                // toast handled by explicit refresh action when needed
            }
        }
    }

    const handleRefreshMetrics = async () => {
        try {
            await runMetricsRefresh()
            toast({
                title: 'Metrics refreshed',
                description: 'The latest Tecniplast metrics list is now loaded.',
            })
        } catch (error) {
            toast({
                title: 'Metrics refresh failed',
                description: error instanceof Error ? error.message : 'Unable to load metrics from Tecniplast.',
                variant: 'error',
            })
        }
    }

    const handleValidateCages = async () => {
        if (!cagesInput.trim()) return

        setIsValidating(true)
        // Extract array from space, comma or newline separated text
        const patterns = cagesInput
            .split(/[\n,\s]+/)
            .map(s => s.trim())
            .filter(Boolean)

        try {
            const result = targetKind === 'CAGE'
                ? await searchCages(appId, { patterns })
                : {
                    cages: await searchAnimals(appId, { patterns }),
                    min_date: null,
                    max_date: null,
                }
            debugLog('search-cages raw payload', result)

            // Normalize backend response properly, taking into account DVC variations.
            const cagesReturned = Array.isArray(result.cages) ? result.cages : (Array.isArray(result) ? result : [])
            setValidCages(cagesReturned as GlobResult[])
            setSelectedTargetIds(cagesReturned.map((cage) => cage.humanReadableId))

            const fallbackMin = cagesReturned.reduce<string | null>((currentMin, cage) => {
                if (!cage.registrationEventDate) return currentMin
                return !currentMin || cage.registrationEventDate < currentMin ? cage.registrationEventDate : currentMin
            }, null)
            const fallbackMax = cagesReturned.reduce<string | null>((currentMax, cage) => {
                if (!cage.lastEventDate) return currentMax
                return !currentMax || cage.lastEventDate > currentMax ? cage.lastEventDate : currentMax
            }, null)

            const nextMinDate = toDateTimeLocal(result.min_date ?? fallbackMin)
            const nextMaxDate = toDateTimeLocal(result.max_date ?? fallbackMax)

            setMinDate(nextMinDate)
            setMaxDate(nextMaxDate)
            setStartDate(nextMinDate)
            setEndDate(nextMaxDate)
        } catch (e) {
            console.error(e)
            setValidCages([])
            setSelectedTargetIds([])
        } finally {
            setIsValidating(false)
        }
    }

    const toggleTargetSelection = (targetId: string) => {
        setSelectedTargetIds((prev) =>
            prev.includes(targetId)
                ? prev.filter((value) => value !== targetId)
                : [...prev, targetId]
        )
    }

    const handleSelectAllTargets = () => {
        setSelectedTargetIds(validCages.map((target) => target.humanReadableId))
    }

    const handleClearTargetSelection = () => {
        setSelectedTargetIds([])
    }

    const toggleMetric = (id: string) => {
        setSelectedMetrics((prev) =>
            prev.includes(id) ? prev.filter((m) => m !== id) : [...prev, id]
        )
    }

    const handleSubmit = async () => {
        const selectedTargets = validCages.filter((target) => selectedTargetIds.includes(target.humanReadableId))
        if (!selectedTargets.length || !selectedMetrics.length) return
        if (windowMode === 'SHARED' && (!startDate || !endDate)) return

        setIsSubmitting(true)
        setSubmitError(null)
        try {
            const plannedSubmissions = buildDvcSubmissionPlan({
                appId,
                targetKind,
                windowMode,
                selectedTargets,
                selectedMetrics,
                filename,
                aggregation,
                partition: partition,
                startDate,
                endDate,
            })

            const queuedTasks: DvcExtractionJob[] = []

            for (const pending of plannedSubmissions) {
                const response = await submitTask(appId, pending.payload)
                queuedTasks.push(createQueuedJobFromSubmission(pending, response.taskId))
            }

            upsertDvcExtractionJobs(queuedTasks)
            setTasks((prev) => [...queuedTasks, ...prev])
            setStep(4) // Move to monitoring step
        } catch (e) {
            console.error(e)
            const message = e instanceof Error ? e.message : 'Failed to submit task'
            setSubmitError(message)
            toast({
                title: 'Task submission failed',
                description: message,
                variant: 'error',
            })
        } finally {
            setIsSubmitting(false)
        }
    }

    const handleZipDownload = async (taskId: number, outputName = filename) => {
        setIsDownloadingTask(taskId)
        try {
            await downloadTaskZip(appId, taskId, outputName)
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown download error'
            alert(`Failed to download the extraction ZIP: ${message}`)
        } finally {
            setIsDownloadingTask(null)
        }
    }

    const handleExtractAndVisualize = async (taskId: number) => {
        if (!onDataExtracted) {
            void handleZipDownload(taskId)
            return
        }

        setIsPiping(taskId)
        try {
            await extractTaskForVisualization(appId, taskId, onDataExtracted)
        } catch (e: unknown) {
            console.error('Error extracting zip blob:', e)
            const message = e instanceof Error ? e.message : 'Unknown extraction error'
            alert(`Failed to extract data via API token stream: ${message}`)
        } finally {
            setIsPiping(null)
        }
    }

    // --- Renderers ---

    const activeTaskCount = tasks.filter((task) => task.state === TaskStateEnum.SUBMITTED || task.state === TaskStateEnum.RUNNING).length
    const allTasksCompleted = tasks.length > 0 && tasks.every((task) => task.state === TaskStateEnum.COMPLETED)
    const hasFailedTasks = tasks.some((task) => task.state === TaskStateEnum.FAILED)
    const pollingCardClassName = allTasksCompleted
        ? 'border-emerald-200'
        : hasFailedTasks
            ? 'border-amber-200'
            : 'border-indigo-200'
    const pollingHeaderClassName = allTasksCompleted
        ? 'bg-emerald-50/80'
        : hasFailedTasks
            ? 'bg-amber-50/80'
            : 'bg-indigo-50/50'
    const pollingTitleClassName = allTasksCompleted
        ? 'text-emerald-900'
        : hasFailedTasks
            ? 'text-amber-900'
            : 'text-indigo-900'

    return (
        <div className="space-y-6 p-6">
            <Card className="border-indigo-100 bg-white/90 shadow-sm">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base text-indigo-950">
                        <KeyRound className="h-4 w-4" />
                        DVC Connection Console
                    </CardTitle>
                    <CardDescription>
                        Review the last saved API key hint, replace it live, verify the DVC endpoint, and inspect the current metrics catalog.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                            <div className="space-y-3">
                            <div className="grid gap-2">
                                <Label htmlFor="target-kind">Resource type</Label>
                                <select
                                    id="target-kind"
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                    value={targetKind}
                                    onChange={(event) => setTargetKind(event.target.value as TargetKind)}
                                >
                                    <option value="CAGE">Cages</option>
                                    <option value="ANIMAL">Animals</option>
                                </select>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-medium text-slate-700">Last saved API key</span>
                                <Badge variant="outline" className="font-mono">
                                    {storedTokenHint || 'No API key saved'}
                                </Badge>
                                <Badge variant={isProxyValid ? 'default' : 'outline'}>
                                    {isTestingProxy ? 'Testing…' : isProxyValid ? 'Responding' : 'Not responding'}
                                </Badge>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="dvc-api-key">Update API key</Label>
                                <Input
                                    id="dvc-api-key"
                                    type="password"
                                    placeholder="Paste a replacement Tecniplast API key"
                                    value={draftApiKey}
                                    onChange={(event) => setDraftApiKey(event.target.value)}
                                />
                                <p className="text-xs text-slate-500">
                                    Save runs a live connectivity check immediately. The key input is cleared after a successful update.
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    onClick={() => saveKeyMutation.mutate()}
                                    disabled={saveKeyMutation.isPending || isTestingProxy || !draftApiKey.trim()}
                                >
                                    {saveKeyMutation.isPending ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <ShieldCheck className="mr-2 h-4 w-4" />}
                                    Save & Test Live
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={handleRetestConnection}
                                    disabled={isTestingProxy || saveKeyMutation.isPending}
                                >
                                    {isTestingProxy ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <Check className="mr-2 h-4 w-4" />}
                                    Retest Saved Key
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={handleToggleMetrics}
                                    disabled={!isProxyValid && !metrics.length}
                                >
                                    {metricsVisible ? <EyeOff className="mr-2 h-4 w-4" /> : <Eye className="mr-2 h-4 w-4" />}
                                    {metricsVisible ? 'Hide Live Metrics' : `Show Live Metrics${metrics.length ? ` (${metrics.length})` : ''}`}
                                </Button>
                                {metricsVisible && (
                                    <Button
                                        variant="outline"
                                        onClick={handleRefreshMetrics}
                                        disabled={isRefreshingMetrics || !isProxyValid}
                                    >
                                        {isRefreshingMetrics ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <RefreshCw className="mr-2 h-4 w-4" />}
                                        Refresh Metrics
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                            <p className="font-medium text-slate-800">Live test result</p>
                            <p className="mt-1 text-xs text-slate-500">
                                {lastTestedAt ? `Last checked ${new Date(lastTestedAt).toLocaleString()}` : 'No live test has completed yet.'}
                            </p>
                            <div className="mt-3 max-w-xl overflow-x-auto rounded-lg bg-slate-950 p-3 font-mono text-xs text-slate-100">
                                {lastTestResult
                                    ? JSON.stringify(lastTestResult, null, 2)
                                    : proxyError || 'Awaiting test response.'}
                            </div>
                        </div>
                    </div>

                    {metricsVisible && (
                        <div className="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h3 className="text-sm font-semibold text-slate-900">Live metrics catalog</h3>
                                    <p className="text-xs text-slate-500">
                                        {metricsLastLoadedAt
                                            ? `${metrics.length} metrics loaded at ${new Date(metricsLastLoadedAt).toLocaleString()}`
                                            : 'Metrics have not been loaded yet.'}
                                    </p>
                                </div>
                                {metricsError && (
                                    <Badge variant="outline" className="text-amber-700">
                                        {metricsError}
                                    </Badge>
                                )}
                            </div>
                            <div className="max-h-72 overflow-auto rounded-xl border bg-white">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Metric ID</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Description</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {metrics.map((metric) => (
                                            <TableRow key={metric.id}>
                                                <TableCell className="font-mono text-xs">{metric.id}</TableCell>
                                                <TableCell><Badge variant="outline">{metric.type}</Badge></TableCell>
                                                <TableCell className="text-sm text-slate-600">{metric.description}</TableCell>
                                            </TableRow>
                                        ))}
                                        {metrics.length === 0 && (
                                            <TableRow>
                                                <TableCell colSpan={3} className="text-center text-sm text-slate-500">
                                                    No metrics available yet.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {!isTestingProxy && isProxyValid === false && (
                <EmptyState
                    title="Integration Disconnected"
                    description={proxyError
                        ? `Proxy/API check failed: ${proxyError}`
                        : 'The DVC Analytics integration is currently waiting for a valid API key.'}
                />
            )}

            {isProxyValid !== false && (
                <>
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="font-display text-2xl text-indigo-900">Extraction Wizard</h2>
                    <p className="text-sm text-slate-500">
                        Export metrics securely from DVC Analytics.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant={step >= 1 ? 'default' : 'outline'}>1. Extract</Badge>
                    <ArrowRight className="h-4 w-4 text-slate-300" />
                    <Badge variant={step >= 2 ? 'default' : 'outline'}>2. Configure</Badge>
                    <ArrowRight className="h-4 w-4 text-slate-300" />
                    <Badge variant={step >= 3 ? 'default' : 'outline'}>3. Generate</Badge>
                </div>
            </div>

            {/* Step 1: Input */}
            {step === 1 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Step 1: Input Targets</CardTitle>
                        <CardDescription>
                            {targetKind === 'CAGE'
                                ? 'Paste cage IDs. The default list is preloaded with the three live test cages you asked for.'
                                : 'Paste animal IDs. The API will return the available recording window for each resolved animal.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Textarea
                            placeholder={targetKind === 'CAGE' ? DEFAULT_TEST_CAGES.join('\n') : 'A12345\nA12346'}
                            value={cagesInput}
                            onChange={(e) => setCagesInput(e.target.value)}
                            className="min-h-[120px] font-mono text-sm"
                        />
                        <div className="flex justify-between items-center">
                            <span className="text-sm text-slate-400">
                                {validCages.length > 0 && `${validCages.length} valid ${targetKind === 'CAGE' ? 'cages' : 'animals'} validated.`}
                            </span>
                            <div className="space-x-2">
                                <Button variant="secondary" onClick={handleValidateCages} disabled={isValidating || !cagesInput}>
                                    {isValidating ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <Check className="mr-2 h-4 w-4" />}
                                    Check Availability
                                </Button>
                                {validCages.length > 0 && (
                                    <Button onClick={() => setStep(2)}>
                                        Proceed to Configuration
                                    </Button>
                                )}
                            </div>
                        </div>

                        {validCages.length > 0 && (
                            <div className="mt-4 space-y-4 rounded border p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">Resolved recording windows</p>
                                        <p className="text-xs text-slate-500">
                                            Overall shared window: {minDate && maxDate ? formatWindowLabel(minDate, maxDate) : 'Unavailable'}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" onClick={handleSelectAllTargets}>Select all</Button>
                                        <Button variant="outline" size="sm" onClick={handleClearTargetSelection}>Clear</Button>
                                    </div>
                                </div>

                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-12"></TableHead>
                                            <TableHead>{targetKind === 'CAGE' ? 'Cage ID' : 'Animal ID'}</TableHead>
                                            <TableHead>Start</TableHead>
                                            <TableHead>Stop</TableHead>
                                            <TableHead>Available Window</TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {validCages.map((cage) => {
                                            const isSelected = selectedTargetIds.includes(cage.humanReadableId)
                                            return (
                                                <TableRow key={cage.uuid}>
                                                    <TableCell>
                                                        <input
                                                            type="checkbox"
                                                            checked={isSelected}
                                                            onChange={() => toggleTargetSelection(cage.humanReadableId)}
                                                        />
                                                    </TableCell>
                                                    <TableCell className="font-mono">{cage.humanReadableId}</TableCell>
                                                    <TableCell>{cage.registrationEventDate ? new Date(cage.registrationEventDate).toLocaleString() : 'N/A'}</TableCell>
                                                    <TableCell>{cage.lastEventDate ? new Date(cage.lastEventDate).toLocaleString() : 'N/A'}</TableCell>
                                                    <TableCell className="text-xs text-slate-600">
                                                        {cage.registrationEventDate && cage.lastEventDate
                                                            ? formatWindowLabel(cage.registrationEventDate, cage.lastEventDate)
                                                            : 'Unavailable'}
                                                    </TableCell>
                                                    <TableCell><Badge variant="outline">{cage.status}</Badge></TableCell>
                                                </TableRow>
                                            )
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Step 2: Configuration */}
            {step === 2 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Step 2: Task Configuration</CardTitle>
                        <CardDescription>
                            Choose whether to extract each selected {targetKind === 'CAGE' ? 'cage' : 'animal'} over its full available recording window, or apply one shared start/stop interval to the selected subset.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                            <div className="rounded-xl border border-slate-200 p-4">
                                <div className="flex items-start gap-3">
                                    <input
                                        id="window-mode-max"
                                        type="radio"
                                        checked={windowMode === 'MAX_PER_TARGET'}
                                        onChange={() => setWindowMode('MAX_PER_TARGET')}
                                    />
                                    <div>
                                        <Label htmlFor="window-mode-max">Use each target's maximal recording window</Label>
                                        <p className="mt-1 text-sm text-slate-500">
                                            One DVC task per selected {targetKind === 'CAGE' ? 'cage' : 'animal'}, automatically bounded by that target's registration and last event dates.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div className="rounded-xl border border-slate-200 p-4">
                                <div className="flex items-start gap-3">
                                    <input
                                        id="window-mode-shared"
                                        type="radio"
                                        checked={windowMode === 'SHARED'}
                                        onChange={() => setWindowMode('SHARED')}
                                    />
                                    <div>
                                        <Label htmlFor="window-mode-shared">Apply one shared window to the selected subset</Label>
                                        <p className="mt-1 text-sm text-slate-500">
                                            Useful when you want aligned time ranges across all selected {targetKind === 'CAGE' ? 'cages' : 'animals'}.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <p className="text-sm font-semibold text-slate-900">
                                Selected {targetKind === 'CAGE' ? 'cages' : 'animals'}: {selectedTargetIds.length} / {validCages.length}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                Shared available boundary across the current selection: {minDate && maxDate ? formatWindowLabel(minDate, maxDate) : 'Unavailable'}
                            </p>
                        </div>

                        {windowMode === 'SHARED' && (
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Data Boundary: Start</Label>
                                    <Input
                                        type="datetime-local"
                                        step={1}
                                        value={startDate}
                                        min={minDate}
                                        onChange={(e) => setStartDate(e.target.value)}
                                    />
                                    {minDate && <p className="text-xs text-slate-500">Earliest known available: {new Date(minDate).toLocaleString()}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label>Data Boundary: Stop</Label>
                                    <Input
                                        type="datetime-local"
                                        step={1}
                                        value={endDate}
                                        max={maxDate}
                                        onChange={(e) => setEndDate(e.target.value)}
                                    />
                                    {maxDate && <p className="text-xs text-slate-500">Latest known available: {new Date(maxDate).toLocaleString()}</p>}
                                </div>
                            </div>
                        )}

                        {windowMode === 'MAX_PER_TARGET' && (
                            <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                                <p className="text-sm font-semibold text-slate-900">Per-target extraction plan</p>
                                <div className="mt-3 max-h-56 overflow-auto rounded-lg border bg-white">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>{targetKind === 'CAGE' ? 'Cage ID' : 'Animal ID'}</TableHead>
                                                <TableHead>Auto Start</TableHead>
                                                <TableHead>Auto Stop</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {validCages
                                                .filter((target) => selectedTargetIds.includes(target.humanReadableId))
                                                .map((target) => (
                                                    <TableRow key={target.uuid}>
                                                        <TableCell className="font-mono text-xs">{target.humanReadableId}</TableCell>
                                                        <TableCell>{new Date(target.registrationEventDate).toLocaleString()}</TableCell>
                                                        <TableCell>{new Date(target.lastEventDate).toLocaleString()}</TableCell>
                                                    </TableRow>
                                                ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        )}

                        {dateWarning && (
                            <div className="flex items-center gap-2 text-amber-600 bg-amber-50 p-3 rounded-md text-sm">
                                <AlertCircle className="h-5 w-5" />
                                {dateWarning}
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label>Metrics Selection</Label>
                            <div className="grid grid-cols-2 lg:grid-cols-3 gap-2 border rounded-md p-4 bg-slate-50/50">
                                {metrics.map((m) => (
                                    <div key={m.id} className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            id={`metric-${m.id}`}
                                            checked={selectedMetrics.includes(m.id)}
                                            onChange={() => toggleMetric(m.id)}
                                            className="rounded border-slate-300"
                                        />
                                        <Label htmlFor={`metric-${m.id}`} className="text-sm font-normal">
                                            {m.id}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Aggregation</Label>
                                <select
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                    value={aggregation}
                                    onChange={(e) => setAggregation(e.target.value as TaskAggregationType)}
                                >
                                    <option value={TaskAggregationType.MINUTE}>Minute</option>
                                    <option value={TaskAggregationType.HOUR}>Hour</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <Label>Partition</Label>
                                <select
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                    value={partition}
                                    onChange={(e) => setPartition(e.target.value as TaskPartitionType)}
                                >
                                    {Object.values(TaskPartitionType).map((t) => (
                                        <option key={t} value={t}>{t}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Filename Preface</Label>
                            <Input value={filename} onChange={(e) => setFilename(e.target.value)} />
                        </div>

                        <div className="flex justify-between">
                            <Button variant="outline" onClick={() => setStep(1)}>Back</Button>
                            <Button onClick={() => setStep(3)} disabled={selectedMetrics.length === 0 || selectedTargetIds.length === 0 || (windowMode === 'SHARED' && (!startDate || !endDate))}>
                                Review & Submit
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Step 3: Review & Submit */}
            {step === 3 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Step 3: Submit Payload Details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="rounded-md bg-slate-900 text-white p-4 font-mono text-sm overflow-x-auto">
                            <p>Resource Type: {targetKind}</p>
                            <p>Targets: {selectedTargetIds.length} selected</p>
                            <p>Metrics: {selectedMetrics.join(', ')}</p>
                            <p>
                                Range: {windowMode === 'MAX_PER_TARGET'
                                    ? 'Maximal per selected target'
                                    : `${new Date(startDate).toLocaleString()} - ${new Date(endDate).toLocaleString()}`}
                            </p>
                            <p>Aggregation: {aggregation}</p>
                            <p>Partition Format: {partition}</p>
                        </div>
                        {submitError && (
                            <div className="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                {submitError}
                            </div>
                        )}

                        <div className="flex justify-between">
                            <Button variant="outline" onClick={() => setStep(2)}>Back</Button>
                            <Button onClick={handleSubmit} disabled={isSubmitting}>
                                {isSubmitting ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <FileText className="mr-2 h-4 w-4" />}
                                Initiate Server Polling
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Step 4: Monitoring */}
            {step === 4 && tasks.length > 0 && (
                <Card className={pollingCardClassName}>
                    <CardHeader className={pollingHeaderClassName}>
                        <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <CardTitle className={`text-base ${pollingTitleClassName}`}>Step 4: Realtime Polling</CardTitle>
                                <CardDescription>
                                    {allTasksCompleted
                                        ? 'All selected extraction jobs have completed successfully.'
                                        : hasFailedTasks
                                            ? 'Some extraction jobs failed. Review the table below for the latest state.'
                                            : 'We are querying the proxy queue for completed tasks.'}
                                </CardDescription>
                            </div>
                            <div className="rounded-xl border border-white/80 bg-white/70 px-4 py-3 text-sm shadow-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant={allTasksCompleted ? 'default' : hasFailedTasks ? 'critical' : 'outline'}>
                                        {allTasksCompleted
                                            ? 'Completed'
                                            : hasFailedTasks
                                                ? 'Needs attention'
                                                : `${activeTaskCount} active`}
                                    </Badge>
                                    {isPollingQueue ? (
                                        <div className="flex items-center gap-2 text-slate-600">
                                            <RefreshCw className="h-4 w-4 animate-spin text-indigo-500" />
                                            <span className="font-medium">Fetching live status</span>
                                            <span className="inline-flex items-center gap-1">
                                                <span className="h-1.5 w-1.5 rounded-full bg-indigo-400 animate-pulse" />
                                                <span className="h-1.5 w-1.5 rounded-full bg-indigo-400 animate-pulse [animation-delay:150ms]" />
                                                <span className="h-1.5 w-1.5 rounded-full bg-indigo-400 animate-pulse [animation-delay:300ms]" />
                                            </span>
                                        </div>
                                    ) : (
                                        <span className={`font-medium ${allTasksCompleted ? 'text-emerald-700' : 'text-slate-600'}`}>
                                            {allTasksCompleted ? 'Polling complete' : 'Next automatic queue check in up to 5 seconds'}
                                        </span>
                                    )}
                                </div>
                                <p className="mt-2 text-xs text-slate-500">
                                    {isPollingQueue
                                        ? (lastQueueCheckAt
                                            ? `Polling now. Last completed check ${new Date(lastQueueCheckAt).toLocaleString()}`
                                            : 'First queue check is in progress now.')
                                        : (lastQueueCheckAt
                                            ? `Last completed check ${new Date(lastQueueCheckAt).toLocaleString()}`
                                            : 'Queue check will start immediately after submission.')}
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Task ID</TableHead>
                                    <TableHead>Target Scope</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Window</TableHead>
                                    <TableHead>Size</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tasks.map((task) => {
                                    const isDone = task.state === TaskStateEnum.COMPLETED
                                    const isActive = task.state === TaskStateEnum.SUBMITTED || task.state === TaskStateEnum.RUNNING

                                    return (
                                        <TableRow key={task.id}>
                                            <TableCell className="font-medium">#{task.id}</TableCell>
                                            <TableCell>{task.targetLabel || '-'}</TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={isDone ? 'default' : (task.state === TaskStateEnum.FAILED ? 'critical' : 'outline')}
                                                    className={isActive ? 'animate-pulse' : ''}
                                                >
                                                    {task.state}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-xs text-slate-600">
                                                {task.requestedStart && task.requestedStop
                                                    ? formatWindowLabel(task.requestedStart, task.requestedStop)
                                                    : '-'}
                                            </TableCell>
                                            <TableCell>
                                                {task.fileSize ? `${(task.fileSize / 1024 / 1024).toFixed(2)} MB` : '-'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={!isDone || isDownloadingTask !== null}
                                                        onClick={() => void handleZipDownload(task.id, task.outputFilename)}
                                                    >
                                                        {isDownloadingTask === task.id ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}
                                                        Download ZIP
                                                    </Button>
                                                    {onDataExtracted && (
                                                        <Button
                                                            size="sm"
                                                            disabled={!isDone || isPiping !== null || isDownloadingTask !== null}
                                                            onClick={() => handleExtractAndVisualize(task.id)}
                                                            className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                                        >
                                                            {isPiping === task.id ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <FileText className="mr-2 h-4 w-4" />}
                                                            Extract & Visualize
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )
                                })}
                            </TableBody>
                        </Table>

                        <div className="mt-8">
                            <Button variant="outline" className="w-full" onClick={() => { setStep(1); setCagesInput(DEFAULT_TEST_CAGES.join('\n')); setTasks([]); setValidCages([]); setSelectedTargetIds([]); }}>
                                Start New Export Wizard
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}
                </>
            )}
        </div>
    )
}
