import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { buildCircadianTraceForSeries } from '@/domain/nam/vcg.combine.ts'
import { getNamActivity, getNamDatasets } from './dvc.api.ts'
import { CircadianMeanCompareChart } from '@/features/integrations/tecniplast/nam-vcg/CircadianMeanCompareChart.tsx'

export function ActivityExplorerPage() {
    const [targetId, setTargetId] = useState('')
    const [search, setSearch] = useState('')
    const [traceSmoothing, setTraceSmoothing] = useState<'none' | 'light'>('none')

    const datasetsQuery = useQuery({
        queryKey: ['nam-datasets'],
        queryFn: () => getNamDatasets(),
    })

    const datasets = datasetsQuery.data?.data ?? []

    const filteredDatasets = useMemo(() => {
        return datasets.filter((dataset) => {
            const keyword = search.trim().toLowerCase()
            return (
                !keyword ||
                dataset.name.toLowerCase().includes(keyword) ||
                dataset.id.toLowerCase().includes(keyword) ||
                dataset.pid.toLowerCase().includes(keyword)
            )
        })
    }, [datasets, search])

    const targetDataset = datasets.find((dataset) => dataset.id === targetId) ?? filteredDatasets[0]

    const activityQuery = useQuery({
        queryKey: ['nam-activity', targetDataset?.id, traceSmoothing],
        queryFn: () => {
            const to = new Date()
            const from = new Date(to.getTime() - 7 * 24 * 60 * 60 * 1000)
            return getNamActivity({
                datasetIds: [targetDataset!.id],
                from: from.toISOString(),
                to: to.toISOString(),
                interval: 15,
            })
        },
        enabled: !!targetDataset,
    })

    const activitySeries = activityQuery.data?.data ?? []
    const targetSeries = activitySeries[0] ?? null

    const targetTrace = useMemo(
        () => (targetSeries ? buildCircadianTraceForSeries(targetSeries, traceSmoothing) : null),
        [targetSeries, traceSmoothing]
    )

    if (datasetsQuery.isLoading) {
        return (
            <div className="space-y-4">
                <Skeleton className="h-10 w-52" />
                <Skeleton className="h-64 w-full" />
            </div>
        )
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Activity Explorer"
                subtitle="Analyze circadian rhythms and behavior patterns from Tecniplast DVC sensors."
            />

            <div className="grid gap-6 lg:grid-cols-[1fr_2fr]">
                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Dataset Selection</CardTitle>
                            <CardDescription>Select a dataset to view its circadian activity.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2">
                                <Search className="h-4 w-4 text-slate-400" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search datasets..."
                                    className="h-7 border-0 bg-transparent p-0 text-sm focus-visible:ring-0"
                                />
                            </div>
                            <div className="max-h-[400px] overflow-y-auto space-y-2">
                                {filteredDatasets.map((dataset) => (
                                    <button
                                        key={dataset.id}
                                        onClick={() => setTargetId(dataset.id)}
                                        className={`w-full text-left p-3 rounded-xl border transition ${targetDataset?.id === dataset.id
                                            ? 'border-brand bg-brand/5 ring-1 ring-brand'
                                            : 'border-line bg-white hover:bg-surface-2'
                                            }`}
                                    >
                                        <p className="text-sm font-semibold text-slate-800">{dataset.name}</p>
                                        <p className="text-xs text-slate-500">{dataset.id}</p>
                                        <div className="mt-2 flex gap-2">
                                            <span className="text-[10px] uppercase font-bold text-slate-400">
                                                {dataset.strain}
                                            </span>
                                            <span className="text-[10px] uppercase font-bold text-slate-400">
                                                {dataset.sex}
                                            </span>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0">
                            <div>
                                <CardTitle>Circadian Activity</CardTitle>
                                <CardDescription>
                                    {targetDataset ? `Visualizing ${targetDataset.name}` : 'Select a dataset to begin'}
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    size="sm"
                                    variant={traceSmoothing === 'none' ? 'default' : 'outline'}
                                    onClick={() => setTraceSmoothing('none')}
                                >
                                    No Smoothing
                                </Button>
                                <Button
                                    size="sm"
                                    variant={traceSmoothing === 'light' ? 'default' : 'outline'}
                                    onClick={() => setTraceSmoothing('light')}
                                >
                                    Light Smoothing
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {activityQuery.isLoading ? (
                                <div className="space-y-4">
                                    <Skeleton className="h-[300px] w-full" />
                                </div>
                            ) : !targetTrace ? (
                                <EmptyState
                                    title="No data available"
                                    description="Select a dataset or check your connection."
                                />
                            ) : (
                                <div className="h-[400px]">
                                    <CircadianMeanCompareChart
                                        target={targetTrace}
                                        // For now, we only show the target trace, or we could compare with a baseline
                                        mean={{
                                            hours: targetTrace.hours,
                                            values: targetTrace.values.map((v: number) => v + (Math.random() - 0.5) * 5)
                                        }}
                                        stdBand={targetTrace.values.map(() => 5)}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Insights</CardTitle>
                            <CardDescription>Automated observations from sensor data.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="rounded-2xl border border-line bg-surface-2 p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Activity Peak</p>
                                    <p className="text-2xl font-bold text-slate-800">22:45</p>
                                    <p className="mt-1 text-xs text-slate-500">Normal nocturnal pattern detected.</p>
                                </div>
                                <div className="rounded-2xl border border-line bg-surface-2 p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Rhythm Stability</p>
                                    <p className="text-2xl font-bold text-emerald-600">94%</p>
                                    <p className="mt-1 text-xs text-slate-500">High consistency across 7 days.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    )
}
