import { useState, useEffect, useMemo } from 'react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card.tsx'
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip as RechartsTooltip, ResponsiveContainer, Legend } from 'recharts'
import { Activity, Loader2 } from 'lucide-react'
import { dvcWorker } from './dvcWorkerClient.ts'

interface DVCPeriodogramProps {
    binning: 'raw' | '15m' | '1h' | '1d'
    metric: string
    groups: string[]
    customGroupsUpdated: number
}

export function DVCPeriodogram({ binning, metric, groups, customGroupsUpdated }: DVCPeriodogramProps) {
    const [periodogramData, setPeriodogramData] = useState<any[]>([])
    const [isLoading, setIsLoading] = useState(false)

    const colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#f43f5e', '#8b5cf6']

    const groupColorMap = useMemo(() => {
        const map: Record<string, string> = {}
        groups.forEach((g, i) => { map[g] = colors[i % colors.length] })
        map['All Cages'] = '#6366f1'
        map['Unknown'] = '#94a3b8'
        return map
    }, [groups])

    useEffect(() => {
        let isMounted = true
        if (!metric) return

        setIsLoading(true)

        dvcWorker.getPeriodogram(binning, metric)
            .then(data => {
                if (isMounted) {
                    setPeriodogramData(data || [])
                    setIsLoading(false)
                }
            })
            .catch(err => {
                console.error("Periodogram Error:", err)
                if (isMounted) setIsLoading(false)
            })

        return () => { isMounted = false }
    }, [binning, metric, customGroupsUpdated])

    // Average the spectra across groups
    const groupedSpectra = useMemo(() => {
        if (!periodogramData.length) return []

        const groupMap: Record<string, { count: number, spectralSum: Record<string, number> }> = {}

        periodogramData.forEach(cageData => {
            const grp = cageData.group || 'Unknown'
            if (!groupMap[grp]) {
                groupMap[grp] = { count: 0, spectralSum: {} }
            }

            groupMap[grp].count++
            cageData.spectrum.forEach((pt: any) => {
                const pStr = pt.period.toString()
                if (!groupMap[grp].spectralSum[pStr]) groupMap[grp].spectralSum[pStr] = 0
                groupMap[grp].spectralSum[pStr] += pt.power
            })
        })

        // Format for Recharts LineChart
        // We want an array of objects like { period: 20.0, 'WT': 15.2, 'KO': 12.1 }
        const periods = periodogramData[0]?.spectrum.map((p: any) => p.period) || []

        return periods.map((period: number) => {
            const row: any = { period }
            const pStr = period.toString()

            Object.keys(groupMap).forEach(grp => {
                const avgPower = groupMap[grp].spectralSum[pStr] / groupMap[grp].count
                row[grp] = parseFloat(avgPower.toFixed(3))
            })
            return row
        })
    }, [periodogramData])

    // Find overall dominant period per group for display
    const groupDominantPeriods = useMemo(() => {
        if (!groupedSpectra.length) return {}

        const dominant: Record<string, { period: number, power: number }> = {}

        Object.keys(groupedSpectra[0]).filter(k => k !== 'period').forEach(grp => {
            let maxPower = -1
            let maxPeriod = 0

            groupedSpectra.forEach((row: any) => {
                if (row[grp] > maxPower) {
                    maxPower = row[grp]
                    maxPeriod = row.period
                }
            })

            dominant[grp] = { period: maxPeriod, power: maxPower }
        })

        return dominant
    }, [groupedSpectra])

    return (
        <Card className="mt-6 border-indigo-100 shadow-sm animate-in fade-in slide-in-from-bottom-4 duration-500">
            <CardHeader className="pb-4 border-b border-indigo-50">
                <CardTitle className="text-xl text-indigo-900 flex items-center gap-2">
                    <Activity className="w-5 h-5 text-indigo-600" /> Circadian Periodogram
                </CardTitle>
                <CardDescription>
                    Spectral power analysis across 20-30 hour periods to detect dominant circadian rhythms for {metric}.
                </CardDescription>
            </CardHeader>
            <CardContent className="pt-6 relative min-h-[400px]">
                {isLoading ? (
                    <div className="absolute inset-0 flex flex-col items-center justify-center bg-white/80 backdrop-blur-sm z-10">
                        <Loader2 className="w-8 h-8 text-indigo-500 animate-spin mb-4" />
                        <span className="text-sm text-slate-500 font-medium">Computing spectral densities...</span>
                    </div>
                ) : groupedSpectra.length === 0 ? (
                    <div className="h-[400px] flex items-center justify-center text-sm text-slate-400">
                        Insufficient data to compute periodogram for {metric}.
                    </div>
                ) : (
                    <div className="flex flex-col gap-6">
                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                            {Object.entries(groupDominantPeriods).map(([grp, info]) => (
                                <div key={grp} className="bg-slate-50 border border-slate-100 rounded-lg p-3">
                                    <div className="text-xs font-semibold text-slate-500 mb-1 flex items-center gap-1.5">
                                        <div className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: groupColorMap[grp] || groupColorMap['Unknown'] }}></div>
                                        {grp} Dominant
                                    </div>
                                    <div className="text-lg font-bold text-indigo-900">
                                        {info.period.toFixed(1)} <span className="text-sm font-medium text-slate-500">hours</span>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="h-[350px] w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={groupedSpectra} margin={{ top: 10, right: 30, left: 10, bottom: 20 }}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                                    <XAxis
                                        dataKey="period"
                                        type="number"
                                        domain={[20, 30]}
                                        tickCount={11}
                                        tick={{ fontSize: 12, fill: '#64748b' }}
                                        axisLine={false}
                                        tickLine={false}
                                        label={{ value: 'Period (Hours)', position: 'bottom', fontSize: 13, fontWeight: 'bold', fill: '#64748b' }}
                                    />
                                    <YAxis
                                        tick={{ fontSize: 12, fill: '#64748b' }}
                                        axisLine={false}
                                        tickLine={false}
                                        label={{ value: 'Spectral Power', angle: -90, position: 'insideLeft', fontSize: 13, fontWeight: 'bold', fill: '#64748b' }}
                                    />
                                    <RechartsTooltip
                                        contentStyle={{ borderRadius: '8px', border: '1px solid #e2e8f0', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }}
                                        labelFormatter={(val) => `Period: ${Number(val).toFixed(1)}h`}
                                    />
                                    <Legend wrapperStyle={{ paddingTop: '20px' }} />
                                    {Object.keys(groupDominantPeriods).map((grp) => (
                                        <Line
                                            key={grp}
                                            type="monotone"
                                            dataKey={grp}
                                            stroke={groupColorMap[grp] || groupColorMap['Unknown']}
                                            strokeWidth={3}
                                            dot={false}
                                            activeDot={{ r: 6, strokeWidth: 0 }}
                                        />
                                    ))}
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    )
}
