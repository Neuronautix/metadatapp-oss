import { useState, useEffect, useMemo } from 'react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card.tsx'
import { ScatterChart, Scatter, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts'
import { Link, Loader2 } from 'lucide-react'
import { dvcWorker } from './dvcWorkerClient.ts'

interface DVCCorrelationAnalysisProps {
    binning: 'raw' | '15m' | '1h' | '1d'
    metric1: string
    allMetrics: string[]
    groups: string[]
    customGroupsUpdated: number
}

export function DVCCorrelationAnalysis({ binning, metric1, allMetrics, groups, customGroupsUpdated }: DVCCorrelationAnalysisProps) {
    const [metric2, setMetric2] = useState<string>(allMetrics.find(m => m !== metric1) || allMetrics[0])
    const [correlationData, setCorrelationData] = useState<any[]>([])
    const [isLoading, setIsLoading] = useState(false)

    const colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#f43f5e', '#8b5cf6']

    const groupColorMap = useMemo(() => {
        const map: Record<string, string> = {}
        groups.forEach((g, i) => { map[g] = colors[i % colors.length] })
        map['All Cages'] = '#6366f1' // Default color if no groups
        map['Unknown'] = '#94a3b8'
        return map
    }, [groups])

    useEffect(() => {
        let isMounted = true
        if (!metric1 || !metric2) return

        setIsLoading(true)

        dvcWorker.getCorrelation(binning, metric1, metric2)
            .then(data => {
                if (isMounted) {
                    setCorrelationData(data || [])
                    setIsLoading(false)
                }
            })
            .catch(err => {
                console.error("Correlation Error:", err)
                if (isMounted) setIsLoading(false)
            })

        return () => { isMounted = false }
    }, [binning, metric1, metric2, customGroupsUpdated])

    const availableSecondaryMetrics = allMetrics.filter(m => m !== metric1)

    return (
        <Card className="mt-6 border-indigo-100 shadow-sm animate-in fade-in slide-in-from-bottom-4 duration-500">
            <CardHeader className="pb-4 border-b border-indigo-50">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <CardTitle className="text-xl text-indigo-900 flex items-center gap-2">
                            <Link className="w-5 h-5 text-indigo-600" /> Multi-Metric Correlation
                        </CardTitle>
                        <CardDescription>
                            Analyze the relationship between {metric1} and another metric.
                        </CardDescription>
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="text-sm font-semibold text-slate-600">Y-Axis Metric:</span>
                        <select
                            value={metric2}
                            onChange={(e) => setMetric2(e.target.value)}
                            className="bg-white border border-slate-300 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 text-slate-700 font-medium font-sans"
                        >
                            {availableSecondaryMetrics.map(m => (
                                <option key={m} value={m}>{m}</option>
                            ))}
                        </select>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="pt-6 relative min-h-[400px]">
                {isLoading ? (
                    <div className="absolute inset-0 flex flex-col items-center justify-center bg-white/80 backdrop-blur-sm z-10">
                        <Loader2 className="w-8 h-8 text-indigo-500 animate-spin mb-4" />
                        <span className="text-sm text-slate-500 font-medium">Calculating Pearson Correlations...</span>
                    </div>
                ) : correlationData.length === 0 ? (
                    <div className="h-[400px] flex items-center justify-center text-sm text-slate-400">
                        Insufficient paired data to compute correlation between {metric1} and {metric2}.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 xl:grid-cols-2 gap-8">
                        {correlationData.map((dataObj, idx) => (
                            <div key={`corr-${idx}`} className="bg-slate-50 border border-slate-100 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
                                <div className="flex justify-between items-start mb-4">
                                    <h3 className="font-bold text-indigo-900 flex items-center gap-2">
                                        <div className="w-3 h-3 rounded-full" style={{ backgroundColor: groupColorMap[dataObj.group] || groupColorMap['Unknown'] }}></div>
                                        {dataObj.group}
                                    </h3>
                                    <div className="text-right">
                                        <div className="text-sm font-bold text-slate-700">r = {dataObj.stats.r}</div>
                                        <div className="text-xs text-slate-500">R² = {dataObj.stats.r2} (n={dataObj.stats.n})</div>
                                    </div>
                                </div>

                                <div className="h-[250px] w-full">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <ScatterChart margin={{ top: 10, right: 10, bottom: 20, left: 10 }}>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                                            <XAxis
                                                type="number"
                                                dataKey="x"
                                                name={metric1}
                                                tick={{ fontSize: 11, fill: '#64748b' }}
                                                axisLine={false}
                                                tickLine={false}
                                                domain={['auto', 'auto']}
                                                label={{ value: metric1, position: 'bottom', fontSize: 12, fontWeight: 'bold', fill: '#64748b' }}
                                            />
                                            <YAxis
                                                type="number"
                                                dataKey="y"
                                                name={metric2}
                                                tick={{ fontSize: 11, fill: '#64748b' }}
                                                axisLine={false}
                                                tickLine={false}
                                                domain={['auto', 'auto']}
                                                label={{ value: metric2, angle: -90, position: 'left', fontSize: 12, fontWeight: 'bold', fill: '#64748b' }}
                                            />
                                            <Tooltip
                                                cursor={{ strokeDasharray: '3 3' }}
                                                contentStyle={{ borderRadius: '8px', border: '1px solid #e2e8f0', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: '11px' }}
                                            />
                                            <Scatter
                                                name="Data Points"
                                                data={dataObj.points}
                                                fill={groupColorMap[dataObj.group] || groupColorMap['Unknown']}
                                                opacity={0.6}
                                            >
                                                {dataObj.points.map((_entry: any, index: number) => (
                                                    <Cell key={`cell-${index}`} fill={groupColorMap[dataObj.group] || groupColorMap['Unknown']} />
                                                ))}
                                            </Scatter>

                                            <Scatter
                                                name="Best Fit"
                                                data={dataObj.regressionLine}
                                                line={{ stroke: '#0f172a', strokeWidth: 2 }}
                                                shape={() => null} // Hide dots for the regression line
                                                fill="transparent"
                                                legendType="line"
                                            />
                                        </ScatterChart>
                                    </ResponsiveContainer>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    )
}
