import { useMemo } from 'react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card.tsx'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts'
import { BarChart2 } from 'lucide-react'
import type { ParsedMetricData } from './dvcDataWorker.ts'
import * as ss from 'simple-statistics'

interface DVCDistributionPlotsProps {
    data: ParsedMetricData[] // Raw (unbinned) data is best here to show distributions
    metric: string
    cages: string[]
    groups: string[]
}

export function DVCDistributionPlots({ data, metric, groups }: DVCDistributionPlotsProps) {
    const colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#f43f5e', '#8b5cf6']

    const groupStats = useMemo(() => {
        if (!groups.length) return []

        // Extract all raw metric values per group
        const groupValues: Record<string, number[]> = {}
        const cageValues: Record<string, number[]> = {}

        data.forEach(row => {
            if (!row.group || !row.cage) return
            const val = parseFloat(row[metric])
            if (isNaN(val)) return

            if (!groupValues[row.group]) groupValues[row.group] = []
            if (!cageValues[row.cage]) cageValues[row.cage] = []

            groupValues[row.group].push(val)
            cageValues[row.cage].push(val)
        })

        // Instead of calculating on thousands of raw points (which represent e.g. 15min bins),
        // we first aggregate by cage (e.g. mean activity per cage), 
        // then plot the distribution of cages within the group. 
        // This is the standard in systems neuroscience to avoid pseudoreplication.

        const cageMeans: Record<string, number> = {}
        Object.entries(cageValues).forEach(([cage, vals]) => {
            cageMeans[cage] = ss.mean(vals)
        })

        return groups.map((grp, index) => {
            // Find cages that belong to this group (by looking at any row in the raw data)
            const grpCages = new Set(data.filter(r => r.group === grp).map(r => r.cage))

            const means = Array.from(grpCages).map(c => cageMeans[c]).filter(v => v !== undefined && !isNaN(v))

            if (means.length === 0) return { group: grp, mean: 0, sd: 0, sem: 0, q1: 0, median: 0, q3: 0, min: 0, max: 0, color: colors[index % colors.length], points: [] }

            const mean = ss.mean(means)
            const sd = means.length > 1 ? ss.standardDeviation(means) : 0
            const sem = means.length > 1 ? sd / Math.sqrt(means.length) : 0

            const sorted = [...means].sort((a, b) => a - b)
            const q1 = ss.quantileSorted(sorted, 0.25)
            const median = ss.quantileSorted(sorted, 0.5)
            const q3 = ss.quantileSorted(sorted, 0.75)

            // For scatter overlay (Swarm plot imitation)
            const points = means.map(val => ({
                group: grp,
                value: parseFloat(val.toFixed(2)),
                // Add jitter to x-axis for swarm effect
                xJitter: index + (Math.random() - 0.5) * 0.4
            }))

            return {
                group: grp,
                mean: parseFloat(mean.toFixed(2)),
                sd: parseFloat(sd.toFixed(2)),
                sem: parseFloat(sem.toFixed(2)),
                q1: parseFloat(q1.toFixed(2)),
                median: parseFloat(median.toFixed(2)),
                q3: parseFloat(q3.toFixed(2)),
                min: parseFloat(sorted[0].toFixed(2)),
                max: parseFloat(sorted[sorted.length - 1].toFixed(2)),
                errorMax: parseFloat(mean.toFixed(2)) + parseFloat(sem.toFixed(2)), // For ErrorBar plotting
                errorMin: parseFloat(mean.toFixed(2)) - parseFloat(sem.toFixed(2)),
                color: colors[index % colors.length],
                points
            }
        })
    }, [data, metric, groups])


    if (!groups.length) {
        return (
            <div className="h-[400px] flex items-center justify-center text-slate-400 bg-slate-50/50 rounded-xl border border-slate-100">
                Data groups are required to generate distribution plots. Please configure groups in Settings.
            </div>
        )
    }

    return (
        <Card className="border-none shadow-none bg-transparent">
            <CardHeader className="px-0 pt-0">
                <CardTitle className="text-lg text-slate-800 flex items-center gap-2">
                    <BarChart2 className="w-5 h-5 text-indigo-500" /> Grand Average & Distributions
                </CardTitle>
                <CardDescription>
                    Comparing the overall <strong>{metric}</strong> across groups.
                    <br />Note: Data is first averaged per cage to prevent pseudoreplication, then grouped. Error bars show SEM.
                </CardDescription>
            </CardHeader>
            <CardContent className="px-0 relative min-h-[350px]">
                <div className="h-[350px] w-full max-w-4xl mx-auto">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={groupStats}
                            margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
                            barSize={60}
                        >
                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                            <XAxis dataKey="group" axisLine={false} tickLine={false} tick={{ fontSize: 13, fontWeight: 'bold', fill: '#475569' }} />
                            <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 12, fill: '#64748b' }} />
                            <Tooltip
                                cursor={{ fill: '#f8fafc' }}
                                contentStyle={{ borderRadius: '8px', border: '1px solid #e2e8f0', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }}
                                formatter={(value: any, name: any) => {
                                    if (value === undefined) return ['', name]
                                    if (name === 'mean') return [value, 'Mean']
                                    if (name === 'median') return [value, 'Median']
                                    if (name === 'sd') return [value, 'SD']
                                    if (name === 'sem') return [value, 'SEM']
                                    return [value, name]
                                }}
                            />

                            <Bar dataKey="mean" animationDuration={1000}>
                                {groupStats.map((entry, index) => (
                                    <Cell key={`cell-${index}`} fill={entry.color} fillOpacity={0.8} />
                                ))}
                                {/* Recharts doesn't natively do asymmetric error bounds easily without complex data keys, so we map upper/lower bounds manually if needed, or use a separate component */}
                            </Bar>

                            {/* Hack to Overlay scatter points (Individual Cages) inside the BarChart using ComposedChart isn't strictly necessary if we use a separate ScatterChart, but we can do it via a fake Line or just build a real ComposedChart */}
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    )
}
