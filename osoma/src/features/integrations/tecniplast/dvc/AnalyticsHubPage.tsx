import { useState } from 'react'
import { PageHeader } from '@/components/PageHeader.tsx'
import { DVCIntegrationTasks } from './DVCIntegrationTasks.tsx'
import { DVCDataVisualizer } from './DVCDataVisualizer.tsx'
import { LocalDataUploader } from './LocalDataUploader.tsx'
import { DVCExtractionJobsPanel } from './DVCExtractionJobsPanel.tsx'
import { useQuery } from '@tanstack/react-query'
import { getConnectedApps } from '../../connected-apps/connected-apps.api.ts'
import { Loader2, CloudDownload, FileUp, History } from 'lucide-react'

export function AnalyticsHubPage() {
    const [isDataLoaded, setIsDataLoaded] = useState(false)
    const [extractedMetrics, setExtractedMetrics] = useState<string[]>([])
    const [extractedCages, setExtractedCages] = useState<string[]>([])
    const [totalRows, setTotalRows] = useState<number>(0)
    const [mode, setMode] = useState<'live' | 'local' | 'jobs'>('local')

    // Automatically fetch the configured Tecniplast logic so the user doesn't need to manually input IDs.
    const { data: appsData, isLoading } = useQuery({
        queryKey: ['connected-apps'],
        queryFn: getConnectedApps,
    })

    const tecniplastApp = appsData?.['hydra:member']?.find(a => a.code === 'tecniplast')

    const handleDataExtracted = (metrics: string[], cages: string[], rowCount: number) => {
        setExtractedMetrics(metrics)
        setExtractedCages(cages)
        setTotalRows(rowCount)
        setIsDataLoaded(true)
    }

    if (isLoading) {
        return (
            <div className="flex h-[400px] items-center justify-center">
                <Loader2 className="h-8 w-8 animate-spin text-indigo-500" />
            </div>
        )
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Analytics Hub"
                subtitle="Visualize and analyze DVC telemetry datasets."
            />

            {!isDataLoaded ? (
                <div className="space-y-6">
                    <div className="flex justify-center mb-8">
                        <div className="inline-flex rounded-xl bg-slate-100 p-1">
                            <button
                                onClick={() => setMode('local')}
                                className={`flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold transition-all ${mode === 'local'
                                    ? 'bg-white text-indigo-600 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800'
                                    }`}
                            >
                                <FileUp className="w-4 h-4" />
                                Local File Import
                            </button>
                            <button
                                onClick={() => setMode('live')}
                                className={`flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold transition-all ${mode === 'live'
                                    ? 'bg-white text-indigo-600 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800'
                                    }`}
                            >
                                <CloudDownload className="w-4 h-4" />
                                Live API Extraction
                            </button>
                            <button
                                onClick={() => setMode('jobs')}
                                className={`flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold transition-all ${mode === 'jobs'
                                    ? 'bg-white text-indigo-600 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800'
                                    }`}
                            >
                                <History className="w-4 h-4" />
                                Extraction Jobs
                            </button>
                        </div>
                    </div>

                    {mode === 'local' ? (
                        <LocalDataUploader onDataLoaded={handleDataExtracted} />
                    ) : mode === 'jobs' ? (
                        tecniplastApp ? (
                            <DVCExtractionJobsPanel
                                appId={tecniplastApp.id}
                                onDataExtracted={handleDataExtracted}
                            />
                        ) : (
                            <div className="p-8 text-center rounded-2xl border border-dashed text-slate-500 text-sm animate-in fade-in zoom-in-95 duration-300">
                                Tecniplast DVC Integration is not configured or available. Please enable it in the Connected Apps settings.
                            </div>
                        )
                    ) : (
                        tecniplastApp ? (
                            <div className="rounded-2xl border border-line bg-surface shadow-[0_0_40px_-15px_rgba(0,0,0,0.05)] overflow-hidden animate-in fade-in zoom-in-95 duration-300">
                                <DVCIntegrationTasks
                                    appId={tecniplastApp.id}
                                    tokenHint={tecniplastApp.tokenHint}
                                    onDataExtracted={handleDataExtracted}
                                />
                            </div>
                        ) : (
                            <div className="p-8 text-center rounded-2xl border border-dashed text-slate-500 text-sm animate-in fade-in zoom-in-95 duration-300">
                                Tecniplast DVC Integration is not configured or available. Please enable it in the Connected Apps settings.
                            </div>
                        )
                    )}
                </div>
            ) : (
                <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div className="flex items-center justify-between">
                        <p className="text-sm font-medium text-slate-500">
                            Successfully parsed <strong className="text-indigo-900">{totalRows.toLocaleString()}</strong> temporal records across <strong className="text-indigo-900">{extractedMetrics.length}</strong> metrics from the DVC Stream.
                        </p>
                        <button
                            onClick={() => { setIsDataLoaded(false); setExtractedMetrics([]); setExtractedCages([]); setTotalRows(0); }}
                            className="px-4 py-2 bg-indigo-50 rounded-xl text-sm text-indigo-600 hover:text-indigo-800 hover:bg-indigo-100 font-bold transition-colors"
                        >
                            &larr; Start New Extraction
                        </button>
                    </div>

                    <DVCDataVisualizer metrics={extractedMetrics} cages={extractedCages} />
                </div>
            )}
        </div>
    )
}
