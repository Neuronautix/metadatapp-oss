import { useMemo, useState } from 'react'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts'
import type { ParsedMetricData } from './DVCDataVisualizer.tsx'

interface SleepAnalysisProps {
    data: ParsedMetricData[]
    metric: string
    cages: string[]
}

export function SleepAnalysis({ data, metric, cages }: SleepAnalysisProps) {
    const [thresholdPct, setThresholdPct] = useState<number>(5) // 5% of max activity
    const [minImmobilitySec, setMinImmobilitySec] = useState<number>(40) // 40 seconds minimum

    const sleepStats = useMemo(() => {
        if (!data.length) return []

        const statsByCage: Record<string, { tst: number, bouts: number, totalTime: number }> = {}

        cages.forEach(cage => {
            const cageData = data
                .filter(d => d.cage === cage && !isNaN(parseFloat(d[metric])))
                .sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime())

            if (cageData.length < 2) return

            // 1. Calculate sampling interval in seconds
            const t1 = new Date(cageData[0].timestamp).getTime()
            const t2 = new Date(cageData[1].timestamp).getTime()
            const intervalSec = Math.max(1, (t2 - t1) / 1000)

            // 2. Find max activity for threshold calculation
            const maxActivity = Math.max(...cageData.map(d => parseFloat(d[metric])), 0.0001)
            const threshold = maxActivity * (thresholdPct / 100)

            let currentBoutDuration = 0

            let totalSleepTimeSec = 0
            let sleepBouts = 0

            cageData.forEach(row => {
                const val = parseFloat(row[metric])
                if (val <= threshold) {
                    currentBoutDuration += intervalSec
                } else {
                    if (currentBoutDuration >= minImmobilitySec) {
                        // Valid sleep bout ended
                        sleepBouts++
                        totalSleepTimeSec += currentBoutDuration
                    }
                    currentBoutDuration = 0
                }
            })

            // Check if trailing bout is valid
            if (currentBoutDuration >= minImmobilitySec) {
                sleepBouts++
                totalSleepTimeSec += currentBoutDuration
            }

            const totalTimeSec = cageData.length * intervalSec

            statsByCage[cage] = {
                tst: totalSleepTimeSec,
                bouts: sleepBouts,
                totalTime: totalTimeSec
            }
        })

        // Convert to array for chart
        return Object.entries(statsByCage).map(([cage, stats]) => ({
            cage: `Cage ${cage}`,
            tstHours: parseFloat((stats.tst / 3600).toFixed(2)),
            bouts: stats.bouts,
            avgBoutMin: stats.bouts > 0 ? parseFloat((stats.tst / 60 / stats.bouts).toFixed(2)) : 0,
            sleepEfficiency: parseFloat(((stats.tst / stats.totalTime) * 100).toFixed(1))
        }))

    }, [data, metric, cages, thresholdPct, minImmobilitySec])

    return (
        <div className="w-full flex flex-col gap-6">
            {/* Algorithm Controls */}
            <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-wrap gap-6 items-center">
                <div>
                    <h4 className="text-sm font-bold text-slate-700 mb-1">Sleep Detection Algorithm</h4>
                    <p className="text-xs text-slate-500">Define putative sleep based on prolonged immobility.</p>
                </div>

                <div className="flex flex-col gap-1 w-48">
                    <div className="flex justify-between text-xs font-semibold text-slate-600">
                        <span>Activity Threshold</span>
                        <span>{thresholdPct}% of Max</span>
                    </div>
                    <input
                        type="range"
                        min="0" max="25" step="1"
                        value={thresholdPct}
                        onChange={(e) => setThresholdPct(parseInt(e.target.value))}
                        className="w-full accent-indigo-600"
                    />
                </div>

                <div className="flex flex-col gap-1 w-48">
                    <div className="flex justify-between text-xs font-semibold text-slate-600">
                        <span>Min. Immobility</span>
                        <span>{minImmobilitySec} sec</span>
                    </div>
                    <input
                        type="range"
                        min="10" max="300" step="10"
                        value={minImmobilitySec}
                        onChange={(e) => setMinImmobilitySec(parseInt(e.target.value))}
                        className="w-full accent-indigo-600"
                    />
                </div>
            </div>

            {/* Charts Array */}
            {sleepStats.length > 0 ? (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 h-[400px]">
                    {/* TST Chart */}
                    <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm h-full flex flex-col">
                        <h4 className="text-sm font-bold text-slate-700 text-center mb-4">Total Sleep Time (Hours)</h4>
                        <div className="flex-1 min-h-0">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={sleepStats} margin={{ top: 5, right: 20, left: 0, bottom: 20 }}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                                    <XAxis dataKey="cage" tick={{ fill: '#64748b', fontSize: 12 }} axisLine={false} tickLine={false} />
                                    <YAxis tick={{ fill: '#64748b', fontSize: 12 }} axisLine={false} tickLine={false} />
                                    <Tooltip cursor={{ fill: '#f8fafc' }} contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }} />
                                    <Bar dataKey="tstHours" name="Total Sleep (Hr)" fill="#8b5cf6" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    {/* Sleep Architecture Chart */}
                    <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm h-full flex flex-col">
                        <h4 className="text-sm font-bold text-slate-700 text-center mb-4">Sleep Architecture</h4>
                        <div className="flex-1 min-h-0">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={sleepStats} margin={{ top: 5, right: 20, left: 0, bottom: 20 }}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                                    <XAxis dataKey="cage" tick={{ fill: '#64748b', fontSize: 12 }} axisLine={false} tickLine={false} />
                                    <YAxis yAxisId="left" tick={{ fill: '#64748b', fontSize: 12 }} axisLine={false} tickLine={false} />
                                    <YAxis yAxisId="right" orientation="right" tick={{ fill: '#64748b', fontSize: 12 }} axisLine={false} tickLine={false} />
                                    <Tooltip cursor={{ fill: '#f8fafc' }} contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }} />
                                    <Legend wrapperStyle={{ paddingTop: '10px' }} iconType="circle" />
                                    <Bar yAxisId="left" dataKey="bouts" name="Number of Bouts" fill="#0ea5e9" radius={[4, 4, 0, 0]} />
                                    <Bar yAxisId="right" dataKey="avgBoutMin" name="Avg Bout Length (Min)" fill="#10b981" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>
                </div>
            ) : (
                <div className="h-64 flex items-center justify-center text-slate-500 border rounded-xl bg-slate-50">
                    Not enough data to compute sleep architecture.
                </div>
            )}
        </div>
    )
}
