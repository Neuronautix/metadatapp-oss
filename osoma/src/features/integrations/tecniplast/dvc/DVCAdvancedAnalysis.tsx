import { useState, useEffect, useMemo } from 'react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card.tsx'
import { ScatterChart, Scatter, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell, Legend } from 'recharts'
import { BrainCircuit, Loader2 } from 'lucide-react'
import { dvcWorker } from './dvcWorkerClient.ts'

interface DVCAdvancedAnalysisProps {
    binning: 'raw' | '15m' | '1h' | '1d'
    metric: string
    groups: string[]
    customGroupsUpdated: number // Use as a trigger to refetch when groups change
}

export function DVCAdvancedAnalysis({ binning, metric, groups, customGroupsUpdated }: DVCAdvancedAnalysisProps) {
    const [pcaData, setPcaData] = useState<any>(null)
    const [cosinorData, setCosinorData] = useState<any[]>([])
    const [isLoading, setIsLoading] = useState(true)

    const colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#f43f5e', '#8b5cf6']

    const groupColorMap = useMemo(() => {
        const map: Record<string, string> = {}
        groups.forEach((g, i) => { map[g] = colors[i % colors.length] })
        map['Unknown'] = '#94a3b8'
        return map
    }, [groups])

    useEffect(() => {
        let isMounted = true
        setIsLoading(true)

        Promise.all([
            dvcWorker.getPCA(binning, metric).catch(err => {
                console.error("PCA Error:", err)
                return null
            }),
            dvcWorker.getCosinor(binning, metric).catch(err => {
                console.error("Cosinor Error:", err)
                return []
            })
        ]).then(([pcaResult, cosinorResult]) => {
            if (isMounted) {
                setPcaData(pcaResult)
                setCosinorData(cosinorResult)
                setIsLoading(false)
            }
        })

        return () => { isMounted = false }
    }, [binning, metric, customGroupsUpdated])

    if (isLoading) {
        return (
            <div className="flex flex-col items-center justify-center p-12 text-slate-400 bg-white rounded-xl border border-slate-200 min-h-[400px]">
                <Loader2 className="w-8 h-8 animate-spin text-indigo-500 mb-4" />
                <p className="text-sm font-medium">Running Advanced Statistical Models...</p>
                <p className="text-xs text-slate-400 mt-2">Computing Principal Components and Cosinor fits</p>
            </div>
        )
    }

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
            {/* PCA Plot */}
            <Card className="border-indigo-100 shadow-sm">
                <CardHeader className="pb-2">
                    <CardTitle className="text-md text-indigo-900 flex items-center gap-2">
                        <BrainCircuit className="w-4 h-4 text-indigo-600" /> Principal Component Analysis
                    </CardTitle>
                    <CardDescription className="text-xs">
                        Dimensionality reduction showing global variance between individuals.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {!pcaData || !pcaData.points ? (
                        <div className="h-[250px] flex items-center justify-center text-xs text-slate-400">
                            Insufficient data for PCA (Requires &gt;= 3 cages)
                        </div>
                    ) : (
                        <div className="h-[300px] w-full mt-4">
                            <ResponsiveContainer width="100%" height="100%">
                                <ScatterChart margin={{ top: 20, right: 20, bottom: 20, left: 20 }}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                                    <XAxis
                                        type="number"
                                        dataKey="pc1"
                                        name="PC 1"
                                        tick={{ fontSize: 12 }}
                                        axisLine={false}
                                        tickLine={false}
                                        label={{ value: `PC 1 (${(pcaData.variance.pc1 * 100).toFixed(1)}%)`, position: 'bottom', fontSize: 12, fontWeight: 'bold', fill: '#64748b' }}
                                    />
                                    <YAxis
                                        type="number"
                                        dataKey="pc2"
                                        name="PC 2"
                                        tick={{ fontSize: 12 }}
                                        axisLine={false}
                                        tickLine={false}
                                        label={{ value: `PC 2 (${(pcaData.variance.pc2 * 100).toFixed(1)}%)`, angle: -90, position: 'left', fontSize: 12, fontWeight: 'bold', fill: '#64748b' }}
                                    />
                                    <Tooltip
                                        cursor={{ strokeDasharray: '3 3' }}
                                        contentStyle={{ borderRadius: '8px', border: '1px solid #e2e8f0', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: '12px' }}
                                    />
                                    <Legend iconType="circle" wrapperStyle={{ fontSize: '12px', paddingTop: '10px' }} />

                                    {groups.map(grp => (
                                        <Scatter
                                            key={`pca-scatter-${grp}`}
                                            name={grp}
                                            data={pcaData.points.filter((p: any) => p.group === grp)}
                                            fill={groupColorMap[grp] || groupColorMap['Unknown']}
                                        >
                                            {pcaData.points.filter((p: any) => p.group === grp).map((_entry: any, index: number) => (
                                                <Cell key={`cell-${index}`} fill={groupColorMap[grp] || groupColorMap['Unknown']} />
                                            ))}
                                        </Scatter>
                                    ))}
                                </ScatterChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Cosinor Results */}
            <Card className="border-indigo-100 shadow-sm">
                <CardHeader className="pb-2">
                    <CardTitle className="text-md text-indigo-900 flex items-center gap-2">
                        <BrainCircuit className="w-4 h-4 text-indigo-600" /> Cosinor Rhythm Analysis
                    </CardTitle>
                    <CardDescription className="text-xs">
                        Multiple linear regression fitting a 24h cosine wave (Mesor, Amplitude, Acrophase).
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {!cosinorData || cosinorData.length === 0 ? (
                        <div className="h-[250px] flex items-center justify-center text-xs text-slate-400">
                            Insufficient data for Cosinor Analysis
                        </div>
                    ) : (
                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full text-sm text-left">
                                <thead className="text-xs text-slate-500 uppercase bg-slate-50 rounded-t-lg">
                                    <tr>
                                        <th className="px-4 py-2 rounded-tl-lg text-slate-700 font-bold">Cage</th>
                                        <th className="px-4 py-2 text-slate-700 font-bold">Group</th>
                                        <th className="px-4 py-2 text-indigo-700 font-bold">MESOR</th>
                                        <th className="px-4 py-2 text-indigo-700 font-bold">Amplitude</th>
                                        <th className="px-4 py-2 rounded-tr-lg text-indigo-700 font-bold">Acrophase (h)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {cosinorData.map((row, idx) => (
                                        <tr key={`${row.cage}-${idx}`} className="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                            <td className="px-4 py-2 font-medium text-slate-700">{row.cage}</td>
                                            <td className="px-4 py-2">
                                                <span
                                                    className="px-2 py-0.5 rounded text-[10px] font-bold text-white tracking-widest"
                                                    style={{ backgroundColor: groupColorMap[row.group] || groupColorMap['Unknown'] }}
                                                >
                                                    {row.group.slice(0, 3).toUpperCase()}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2 text-slate-600">{row.mesor}</td>
                                            <td className="px-4 py-2 text-slate-600">{row.amplitude}</td>
                                            <td className="px-4 py-2 font-semibold text-slate-800">{row.acrophase} h</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    )
}
