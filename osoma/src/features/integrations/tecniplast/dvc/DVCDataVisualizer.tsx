import { useEffect, useMemo, useState } from 'react'
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
    ComposedChart,
    Area,
    Scatter
} from 'recharts'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card.tsx'
import { Activity, Users, Grid3X3, Map, Moon, BarChart2, BrainCircuit, Download, Link, Waves } from 'lucide-react'
import { ActivityActogram } from './ActivityActogram.tsx'
import { SpatialHeatmap } from './SpatialHeatmap.tsx'
import { SleepAnalysis } from './SleepAnalysis.tsx'
import { dvcWorker } from './dvcWorkerClient.ts'
import { Loader2, Settings2 } from 'lucide-react'
import { DVCGroupBuilder } from './DVCGroupBuilder.tsx'
import { DVCDistributionPlots } from './DVCDistributionPlots.tsx'
import { DVCAdvancedAnalysis } from './DVCAdvancedAnalysis.tsx'
import { DVCCorrelationAnalysis } from './DVCCorrelationAnalysis.tsx'
import { DVCPeriodogram } from './DVCPeriodogram.tsx'
import { exportCsv } from '@/lib/export.ts'

export interface ParsedMetricData {
    timestamp: string
    rawTime: number
    cage: string
    group?: string
    [metric: string]: any
}

interface DVCDataVisualizerProps {
    metrics: string[]
    cages: string[]
}

export function DVCDataVisualizer({ metrics, cages }: DVCDataVisualizerProps) {
    const [selectedMetric, setSelectedMetric] = useState(metrics[0] || '')
    const [viewMode, setViewMode] = useState<'individual' | 'group' | 'distributions' | 'advanced' | 'actogram' | 'heatmap' | 'sleep' | 'correlation' | 'periodogram'>('individual')
    const [binning, setBinning] = useState<'raw' | '15m' | '1h' | '1d'>('1h')

    const [binnedData, setBinnedData] = useState<ParsedMetricData[]>([])
    const [isLoading, setIsLoading] = useState(true)
    const [hasGroups, setHasGroups] = useState(false)
    const [hasSpatialData, setHasSpatialData] = useState(false)
    const [showGroupBuilder, setShowGroupBuilder] = useState(false)
    const [customGroups, setCustomGroups] = useState<Record<string, string>>({})
    const [errorBarType, setErrorBarType] = useState<'SD' | 'SEM' | '95% CI'>('SEM')
    const [groupStatsData, setGroupStatsData] = useState<any[]>([])
    const [anovaData, setAnovaData] = useState<any>(null)
    const [timeMode, setTimeMode] = useState<'relative' | 'absolute'>('relative')

    // Fetch base binned data
    useEffect(() => {
        setIsLoading(true)
        dvcWorker.getBinnedData(binning, metrics).then((data) => {
            setBinnedData(data)
            if (data.length > 0) {
                // If we have custom groups, they override native
                const activeGroups = Object.keys(customGroups).length > 0
                    ? data.some(d => customGroups[d.cage] !== undefined)
                    : data.some(d => d.group)
                setHasGroups(activeGroups)
                setHasSpatialData('v_1' in data[0] || 'e1' in data[0])
            } else {
                setHasGroups(false)
                setHasSpatialData(false)
            }
            setIsLoading(false)
        }).catch(err => {
            console.error("Worker fetch error:", err)
            setIsLoading(false)
        })
    }, [binning, metrics, customGroups])

    // Effect to fetch advanced statistics (Mean, SEM, P-values) from worker
    useEffect(() => {
        if (!hasGroups) return
        setIsLoading(true)

        // 1. Send current custom groups to worker
        dvcWorker.setCustomGroups(customGroups).then(() => {
            // 2. Fetch the advanced statistics for the selected metric
            return Promise.all([
                dvcWorker.getGroupStatistics(binning, selectedMetric),
                dvcWorker.getANOVA(binning, selectedMetric)
            ])
        }).then(([stats, anova]) => {
            setGroupStatsData(stats)
            setAnovaData(anova)
            setIsLoading(false)
        }).catch(err => {
            console.error("Stats worker/ANOVA error:", err)
            setIsLoading(false)
        })

    }, [binning, selectedMetric, hasGroups, customGroups])

    // ── Individual Chart Data ─────────────────────────────────────────────
    // Two modes:
    //  'relative' – align all cages to hours since their own recording start
    //               (correct when cages were recorded at different calendar dates)
    //  'absolute' – use actual wall-clock timestamps
    const individualChartData = useMemo(() => {
        const binMs: Record<typeof binning, number> = {
            raw: 60_000,
            '15m': 900_000,
            '1h': 3_600_000,
            '1d': 86_400_000,
        }
        const snap = binMs[binning]

        const grouped: Record<string, any> = {}

        if (timeMode === 'relative') {
            binnedData.forEach(row => {
                if (!row.cage) return
                const relMs = row.relTimeMs ?? 0
                const slotMs = Math.round(relMs / snap) * snap
                const slotKey = String(slotMs)

                if (!grouped[slotKey]) {
                    const h = slotMs / 3_600_000
                    const label = h < 24
                        ? `${h.toFixed(binning === 'raw' ? 2 : 0)}h`
                        : `Day ${Math.floor(h / 24) + 1} ${String(Math.floor(h % 24)).padStart(2, '0')}:00`
                    grouped[slotKey] = { relSlotMs: slotMs, sortKey: slotMs, xLabel: label }
                }

                const val = parseFloat(row[selectedMetric])
                if (!isNaN(val)) grouped[slotKey][row.cage] = val
            })
        } else {
            // Absolute mode: use wall-clock timestamp string as key
            binnedData.forEach(row => {
                if (!row.timestamp || !row.cage) return
                const tsOpts: any = binning === '1d'
                    ? { month: 'short', day: 'numeric' }
                    : { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }
                const label = new Date(row.timestamp).toLocaleString(undefined, tsOpts)
                if (!grouped[label]) {
                    grouped[label] = { sortKey: row.rawTime, xLabel: label }
                }
                const val = parseFloat(row[selectedMetric])
                if (!isNaN(val)) grouped[label][row.cage] = val
            })
        }

        return Object.values(grouped).sort((a: any, b: any) => a.sortKey - b.sortKey)
    }, [binnedData, selectedMetric, binning, timeMode])

    const groups = useMemo(() => {
        const g = new Set<string>()
        if (Object.keys(customGroups).length > 0) {
            Object.values(customGroups).forEach(grp => g.add(grp))
        } else {
            binnedData.forEach(r => { if (r.group) g.add(r.group) })
        }
        return Array.from(g)
    }, [binnedData, customGroups])

    // Group Chart Data is now fetched dynamically from worker (groupStatsData)
    // We just map the errorBarType to the requested bounds
    const groupChartData = useMemo(() => {
        if (!groupStatsData.length) return []

        return groupStatsData.map(row => {
            const mapped = { ...row }
            groups.forEach(grp => {
                if (mapped[`${grp}_mean`] !== undefined) {
                    if (errorBarType === 'SD') mapped[`${grp}_range`] = row[`${grp}_range_sd`]
                    if (errorBarType === 'SEM') mapped[`${grp}_range`] = row[`${grp}_range_sem`]
                    if (errorBarType === '95% CI') mapped[`${grp}_range`] = row[`${grp}_range_ci95`]
                }
            })
            // Map the significance to a Y-value so it renders on the chart
            // We'll place it at the maximum value for that timepoint + a little padding
            if (mapped.significance) {
                let maxVal = 0
                groups.forEach(grp => {
                    const topRange = mapped[`${grp}_range`]?.[1] || 0
                    if (topRange > maxVal) maxVal = topRange
                })
                mapped.significanceY = maxVal * 1.05 // 5% above the highest error bar
            }
            return mapped
        })
    }, [groupStatsData, groups, errorBarType])

    const colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#f43f5e', '#8b5cf6']

    const handleExportCSV = () => {
        if (!groupChartData.length) return

        // Define columns
        const baseCols = ['Timestamp', 'Time (ms)']
        const groupCols = groups.flatMap(g => [`${g}_Mean`, `${g}_SD`, `${g}_SEM`, `${g}_95CI`])
        const statCols = ['Highest_Significance', 't_Stat', 'df', 'p_Value']
        const headers = [...baseCols, ...groupCols, ...statCols]

        const rows = groupChartData.map(row => {
            const rowData: any[] = [row.timestamp, row.rawTime]

            groups.forEach(g => {
                rowData.push(
                    row[`${g}_mean`] ?? '',
                    row[`${g}_range_sd`] ? (row[`${g}_range_sd`][1] - row[`${g}_mean`]).toFixed(3) : '',
                    row[`${g}_range_sem`] ? (row[`${g}_range_sem`][1] - row[`${g}_mean`]).toFixed(3) : '',
                    row[`${g}_range_ci95`] ? (row[`${g}_range_ci95`][1] - row[`${g}_mean`]).toFixed(3) : ''
                )
            })

            rowData.push(
                row.significance || '',
                row.tStat ?? '',
                row.df ?? '',
                row.pVal ?? ''
            )
            return rowData.join(',')
        })

        const csvContent = [headers.join(','), ...rows].join('\n')
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
        const url = URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.setAttribute('href', url)
        link.setAttribute('download', `DVC_Group_Stats_${selectedMetric}_${binning}.csv`)
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
    }

    // Require group property for Group view
    let actualViewMode = (viewMode === 'group' && !hasGroups) ? 'individual' : viewMode
    // Replace 'distributions' view if no groups
    if ((viewMode === 'distributions' || viewMode === 'advanced') && !hasGroups) actualViewMode = 'individual'

    const exportRows: Array<Record<string, unknown>> = (() => {
        if (actualViewMode === 'group') {
            return groupChartData
        }

        if (actualViewMode === 'individual') {
            return individualChartData
        }

        return binnedData.map((row) => {
            const metricValue = row[selectedMetric]
            const baseRow: Record<string, unknown> = {
                timestamp: row.timestamp,
                rawTime: row.rawTime,
                cage: row.cage,
                group: row.group ?? '',
                selectedMetric,
                metricValue: metricValue ?? '',
            }

            if (actualViewMode === 'heatmap') {
                Object.entries(row).forEach(([key, value]) => {
                    if (key.startsWith('v_') || /^e\d+$/i.test(key)) {
                        baseRow[key] = value
                    }
                })
            }

            return baseRow
        })
    })()

    const handleExportSelectedResults = () => {
        if (!exportRows.length) return

        exportCsv(exportRows, `dvc-${actualViewMode}-${selectedMetric || 'dataset'}-${binning}`)
    }

    if (!binnedData.length && !isLoading) return null

    return (
        <Card className="mt-6 border-indigo-200 shadow-sm animate-in fade-in slide-in-from-bottom-4 duration-500">
            <CardHeader className="bg-indigo-50/50 pb-4">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-indigo-100 pb-4">
                    <div>
                        <CardTitle className="text-xl text-indigo-900">Dataset Visualization</CardTitle>
                        <CardDescription>Interactive telemetry view extracted from memory.</CardDescription>
                    </div>

                    <div className="flex gap-2">
                        <div className="flex bg-white rounded-lg border border-slate-200 p-1 mr-4 overflow-x-auto min-w-0 flex-nowrap">
                            <button
                                onClick={() => setViewMode('individual')}
                                className={`flex items-center whitespace-nowrap gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${actualViewMode === 'individual' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'
                                    }`}
                            >
                                <Activity className="w-3.5 h-3.5" /> Individual Cages
                            </button>
                            <button
                                onClick={() => setViewMode('actogram')}
                                className={`flex items-center whitespace-nowrap gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${actualViewMode === 'actogram' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'
                                    }`}
                            >
                                <Grid3X3 className="w-3.5 h-3.5" /> Raster / Actogram
                            </button>
                            <button
                                onClick={() => setViewMode('sleep')}
                                className={`flex items-center whitespace-nowrap gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${actualViewMode === 'sleep' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'
                                    }`}
                            >
                                <Moon className="w-3.5 h-3.5" /> Sleep Analysis
                            </button>
                            {hasSpatialData && (
                                <button
                                    onClick={() => setViewMode('heatmap')}
                                    className={`flex items-center whitespace-nowrap gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${actualViewMode === 'heatmap' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'
                                        }`}
                                >
                                    <Map className="w-3.5 h-3.5" /> Spatial Heatmap
                                </button>
                            )}
                            {hasGroups && (
                                <button
                                    onClick={() => setViewMode('group')}
                                    className={`flex items-center whitespace-nowrap gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${actualViewMode === 'group' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'
                                        }`}
                                >
                                    <Users className="w-3.5 h-3.5" /> Group Average
                                </button>
                            )}
                            {hasGroups && (
                                <button
                                    onClick={() => setViewMode('distributions')}
                                    className={`flex items-center whitespace-nowrap gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${actualViewMode === 'distributions' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'
                                        }`}
                                >
                                    <BarChart2 className="w-3.5 h-3.5" /> Distributions
                                </button>
                            )}
                            {hasGroups && (
                                <button
                                    onClick={() => setViewMode('advanced')}
                                    className={`flex items-center whitespace-nowrap gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${actualViewMode === 'advanced' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'
                                        }`}
                                >
                                    <BrainCircuit className="w-3.5 h-3.5" /> Advanced
                                </button>
                            )}
                            {hasGroups && (
                                <button
                                    onClick={() => setViewMode('periodogram')}
                                    className={`flex items-center whitespace-nowrap gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${actualViewMode === 'periodogram' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'
                                        }`}
                                >
                                    <Waves className="w-3.5 h-3.5" /> Periodogram
                                </button>
                            )}
                            <button
                                onClick={() => setViewMode('correlation')}
                                className={`flex items-center whitespace-nowrap gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${actualViewMode === 'correlation' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'
                                    }`}
                            >
                                <Link className="w-3.5 h-3.5" /> Correlation
                            </button>
                        </div>
                        {exportRows.length > 0 && (
                            <button
                                onClick={actualViewMode === 'group' && hasGroups ? handleExportCSV : handleExportSelectedResults}
                                className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-all border bg-white text-indigo-600 border-indigo-200 hover:bg-indigo-50"
                                title={actualViewMode === 'group' && hasGroups ? 'Download group statistics as CSV' : 'Download the currently selected plotted data as CSV'}
                            >
                                <Download className="w-3.5 h-3.5" /> Export Data
                            </button>
                        )}
                        <button
                            onClick={() => setShowGroupBuilder(!showGroupBuilder)}
                            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold transition-all border ${showGroupBuilder ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'}`}
                        >
                            <Settings2 className="w-3.5 h-3.5" /> Cohort Settings
                        </button>
                    </div>
                </div>

                {showGroupBuilder && (
                    <div className="mb-4">
                        <DVCGroupBuilder
                            cages={cages}
                            currentGroups={customGroups}
                            onGroupsChange={(g) => setCustomGroups(g)}
                        />
                    </div>
                )}

                <div className="pt-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex flex-wrap gap-2">
                        {metrics.map(m => (
                            <button
                                key={m}
                                onClick={() => setSelectedMetric(m)}
                                className={`px-3 py-1 rounded-full text-xs font-semibold transition-all ${selectedMetric === m
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'bg-white text-indigo-600 border border-indigo-200 hover:bg-indigo-50'
                                    }`}
                            >
                                {m}
                            </button>
                        ))}
                    </div>

                    {(actualViewMode === 'individual' || actualViewMode === 'group') && (
                        <div className="flex items-center gap-4">
                            {actualViewMode === 'group' && (
                                <div className="flex items-center gap-2">
                                    <span className="text-xs font-semibold text-slate-500 uppercase tracking-widest">Error Bars:</span>
                                    <div className="flex bg-slate-100 p-1 rounded-lg">
                                        {(['SD', 'SEM', '95% CI'] as const).map(b => (
                                            <button
                                                key={b}
                                                onClick={() => setErrorBarType(b)}
                                                className={`px-3 py-1 rounded-md text-xs font-bold transition-all ${errorBarType === b
                                                    ? 'bg-white text-indigo-700 shadow-sm'
                                                    : 'text-slate-500 hover:text-slate-800'
                                                    }`}
                                            >
                                                {b}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="flex items-center gap-2">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-widest">Binning:</span>
                                <div className="flex bg-slate-100 p-1 rounded-lg">
                                    {(['raw', '15m', '1h', '1d'] as const).map(b => (
                                        <button
                                            key={b}
                                            onClick={() => setBinning(b)}
                                            className={`px-3 py-1 rounded-md text-xs font-bold transition-all ${binning === b
                                                ? 'bg-white text-indigo-700 shadow-sm'
                                                : 'text-slate-500 hover:text-slate-800'
                                                }`}
                                        >
                                            {b === 'raw' ? 'Raw' : b}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {actualViewMode === 'individual' && (
                                <div className="flex items-center gap-2">
                                    <span className="text-xs font-semibold text-slate-500 uppercase tracking-widest">Time axis:</span>
                                    <div className="flex bg-slate-100 p-1 rounded-lg">
                                        <button
                                            onClick={() => setTimeMode('relative')}
                                            title="Align all cages by hours since their own recording start (recommended when cages were recorded at different dates)"
                                            className={`px-3 py-1 rounded-md text-xs font-bold transition-all ${timeMode === 'relative'
                                                ? 'bg-white text-indigo-700 shadow-sm'
                                                : 'text-slate-500 hover:text-slate-800'
                                                }`}
                                        >
                                            Relative
                                        </button>
                                        <button
                                            onClick={() => setTimeMode('absolute')}
                                            title="Use real calendar timestamps on the X axis"
                                            className={`px-3 py-1 rounded-md text-xs font-bold transition-all ${timeMode === 'absolute'
                                                ? 'bg-white text-indigo-700 shadow-sm'
                                                : 'text-slate-500 hover:text-slate-800'
                                                }`}
                                        >
                                            Real time
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent className="pt-8 relative min-h-[450px]">
                {isLoading ? (
                    <div className="absolute inset-0 flex items-center justify-center bg-white/50 backdrop-blur-sm z-10">
                        <Loader2 className="w-8 h-8 text-indigo-500 animate-spin" />
                    </div>
                ) : actualViewMode === 'actogram' ? (
                    <ActivityActogram data={binnedData} metric={selectedMetric} cages={cages} />
                ) : actualViewMode === 'heatmap' ? (
                    <SpatialHeatmap data={binnedData} cages={cages} />
                ) : actualViewMode === 'sleep' ? (
                    <SleepAnalysis data={binnedData} metric={selectedMetric} cages={cages} />
                ) : actualViewMode === 'distributions' ? (
                    <DVCDistributionPlots data={binnedData} metric={selectedMetric} cages={cages} groups={groups} />
                ) : actualViewMode === 'advanced' ? (
                    <DVCAdvancedAnalysis
                        binning={binning}
                        metric={selectedMetric}
                        groups={groups}
                        customGroupsUpdated={Object.keys(customGroups).length}
                    />
                ) : actualViewMode === 'periodogram' ? (
                    <DVCPeriodogram
                        binning={binning}
                        metric={selectedMetric}
                        groups={groups}
                        customGroupsUpdated={Object.keys(customGroups).length}
                    />
                ) : actualViewMode === 'correlation' ? (
                    <DVCCorrelationAnalysis
                        binning={binning}
                        metric1={selectedMetric}
                        allMetrics={metrics}
                        groups={groups}
                        customGroupsUpdated={Object.keys(customGroups).length}
                    />
                ) : (
                    <div className="h-[450px] w-full">
                        {actualViewMode === 'individual' ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={individualChartData} margin={{ top: 5, right: 30, left: 0, bottom: 25 }}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                                    <XAxis
                                        dataKey="xLabel"
                                        tick={{ fill: '#64748b', fontSize: 11 }}
                                        minTickGap={60}
                                        tickMargin={10}
                                        axisLine={false}
                                        tickLine={false}
                                        label={timeMode === 'relative'
                                            ? { value: 'Time since recording start (per cage)', position: 'insideBottom', offset: -15, fill: '#94a3b8', fontSize: 11 }
                                            : undefined
                                        }
                                    />
                                    <YAxis
                                        tick={{ fill: '#64748b', fontSize: 12 }}
                                        axisLine={false}
                                        tickLine={false}
                                        tickFormatter={(val) => typeof val === 'number' ? val.toLocaleString() : val}
                                    />
                                    <Tooltip
                                        labelStyle={{ color: '#1e293b', fontWeight: 'bold', marginBottom: 8 }}
                                        contentStyle={{ borderRadius: '12px', border: '1px solid #e2e8f0', boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)' }}
                                    />
                                    <Legend wrapperStyle={{ paddingTop: '20px' }} iconType="circle" />
                                    {cages.map((cage, index) => (
                                        <Line
                                            key={cage}
                                            type="monotone"
                                            dataKey={cage}
                                            name={`Cage ${cage}`}
                                            stroke={colors[index % colors.length]}
                                            strokeWidth={2}
                                            dot={false}
                                            activeDot={{ r: 6, strokeWidth: 0, fill: colors[index % colors.length] }}
                                        />
                                    ))}
                                </LineChart>
                            </ResponsiveContainer>
                        ) : (
                            <ResponsiveContainer width="100%" height="100%">
                                <ComposedChart data={groupChartData} margin={{ top: 5, right: 30, left: 0, bottom: 25 }}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                                    <XAxis
                                        dataKey="timestamp"
                                        tick={{ fill: '#64748b', fontSize: 12 }}
                                        minTickGap={50}
                                        tickMargin={10}
                                        axisLine={false}
                                        tickLine={false}
                                    />
                                    <YAxis
                                        tick={{ fill: '#64748b', fontSize: 12 }}
                                        axisLine={false}
                                        tickLine={false}
                                        tickFormatter={(val) => typeof val === 'number' ? val.toLocaleString() : val}
                                    />
                                    <Tooltip
                                        labelStyle={{ color: '#1e293b', fontWeight: 'bold', marginBottom: 8 }}
                                        contentStyle={{ borderRadius: '12px', border: '1px solid #e2e8f0', boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)' }}
                                    />
                                    <Legend wrapperStyle={{ paddingTop: '20px' }} iconType="circle" />
                                    {groups.map((grp, index) => (
                                        <Area
                                            key={`area-${grp}`}
                                            type="monotone"
                                            dataKey={`${grp}_range`}
                                            name={`${grp} (± ${errorBarType})`}
                                            stroke="none"
                                            fill={colors[index % colors.length]}
                                            fillOpacity={0.15}
                                        />
                                    ))}
                                    {groups.map((grp, index) => (
                                        <Line
                                            key={`line-${grp}`}
                                            type="monotone"
                                            dataKey={`${grp}_mean`}
                                            name={`${grp} Mean`}
                                            stroke={colors[index % colors.length]}
                                            strokeWidth={3}
                                            dot={false}
                                            activeDot={{ r: 6, strokeWidth: 0, fill: colors[index % colors.length] }}
                                        />
                                    ))}
                                    {/* Statistical Significance markers overlay */}
                                    <Scatter
                                        dataKey="significanceY"
                                        name="Significance (p < 0.05*, p < 0.01**, p < 0.001***)"
                                        fill="#0f172a"
                                        shape={(props: any) => {
                                            const { cx, cy, payload } = props
                                            if (!payload.significance) return null
                                            return (
                                                <text x={cx} y={cy} textAnchor="middle" fill="#0f172a" fontSize="16" fontWeight="bold">
                                                    {payload.significance}
                                                </text>
                                            )
                                        }}
                                        isAnimationActive={false}
                                    />
                                </ComposedChart>
                            </ResponsiveContainer>
                        )}
                    </div>
                )}
            </CardContent>

            {/* Statistical Reporting Tables */}
            {actualViewMode === 'group' && anovaData && (
                <div className="border-t border-indigo-100 bg-slate-50/50 p-6">
                    <h3 className="text-sm font-bold text-indigo-900 mb-4 flex items-center gap-2">
                        <BrainCircuit className="w-4 h-4 text-indigo-600" /> Overall Group Effect (Two-Way RM-ANOVA)
                    </h3>
                    <div className="overflow-x-auto mb-6">
                        <table className="w-full text-sm text-left">
                            <thead className="text-xs text-slate-500 uppercase bg-white border-b border-indigo-100">
                                <tr>
                                    <th className="px-4 py-3 font-bold">Effect Source</th>
                                    <th className="px-4 py-3 font-bold">Significance</th>
                                    <th className="px-4 py-3 text-slate-600">F-Statistic</th>
                                    <th className="px-4 py-3 text-slate-600">df1, df2</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr className="border-b border-slate-100 bg-white hover:bg-slate-50">
                                    <td className="px-4 py-2 font-medium text-slate-700">Main Effect: Group</td>
                                    <td className="px-4 py-2 font-bold text-indigo-600 text-lg">{anovaData.mainGroup.sig}</td>
                                    <td className="px-4 py-2 text-slate-600">{anovaData.mainGroup.F}</td>
                                    <td className="px-4 py-2 text-slate-600">{anovaData.mainGroup.df1}, {anovaData.mainGroup.df2}</td>
                                </tr>
                                <tr className="border-b border-slate-100 bg-white hover:bg-slate-50">
                                    <td className="px-4 py-2 font-medium text-slate-700">Main Effect: Time</td>
                                    <td className="px-4 py-2 font-bold text-indigo-600 text-lg">{anovaData.mainTime.sig}</td>
                                    <td className="px-4 py-2 text-slate-600">{anovaData.mainTime.F}</td>
                                    <td className="px-4 py-2 text-slate-600">{anovaData.mainTime.df1}, {anovaData.mainTime.df2}</td>
                                </tr>
                                <tr className="border-b border-slate-100 bg-white hover:bg-slate-50">
                                    <td className="px-4 py-2 font-medium text-slate-700">Interaction: Group × Time</td>
                                    <td className="px-4 py-2 font-bold text-indigo-600 text-lg">{anovaData.interaction.sig}</td>
                                    <td className="px-4 py-2 text-slate-600">{anovaData.interaction.F}</td>
                                    <td className="px-4 py-2 text-slate-600">{anovaData.interaction.df1}, {anovaData.interaction.df2}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {actualViewMode === 'group' && groupChartData.some(d => d.significance) && (
                <div className="border-t border-indigo-100 bg-slate-50/50 p-6 rounded-b-xl">
                    <h3 className="text-sm font-bold text-indigo-900 mb-4 flex items-center gap-2">
                        <BarChart2 className="w-4 h-4 text-indigo-600" /> Hourly Significance Tests (Welch's t-test)
                    </h3>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left">
                            <thead className="text-xs text-slate-500 uppercase bg-white border-b border-indigo-100">
                                <tr>
                                    <th className="px-4 py-3 font-bold">Time Point</th>
                                    <th className="px-4 py-3 font-bold">Significance</th>
                                    <th className="px-4 py-3 text-slate-600">t-statistic</th>
                                    <th className="px-4 py-3 text-slate-600">Degrees of Freedom (df)</th>
                                    <th className="px-4 py-3 text-indigo-700 font-bold">Approx. p-value</th>
                                </tr>
                            </thead>
                            <tbody>
                                {groupChartData.filter(d => d.significance).map((row, idx) => (
                                    <tr key={`stat-${idx}`} className="border-b border-slate-100 bg-white hover:bg-slate-50 transition-colors">
                                        <td className="px-4 py-2 font-medium text-slate-700">{row.timestamp}</td>
                                        <td className="px-4 py-2 font-bold text-indigo-600 text-lg">{row.significance}</td>
                                        <td className="px-4 py-2 text-slate-600">{row.tStat}</td>
                                        <td className="px-4 py-2 text-slate-600">{row.df}</td>
                                        <td className="px-4 py-2 font-semibold text-slate-800">{row.pVal}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </Card>
    )
}
