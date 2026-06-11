import { useMemo, useState } from 'react'
import type { ParsedMetricData } from './DVCDataVisualizer.tsx'

interface SpatialHeatmapProps {
    data: ParsedMetricData[]
    cages: string[]
}

export function SpatialHeatmap({ data, cages }: SpatialHeatmapProps) {
    const [selectedCage, setSelectedCage] = useState<string>(cages[0] || '')
    const [timeRange, setTimeRange] = useState<'all' | 'day' | 'night'>('all')

    const heatmapData = useMemo(() => {
        if (!selectedCage) return []

        const cageData = data.filter(d => {
            if (d.cage !== selectedCage) return false

            if (timeRange === 'all') return true

            const hour = new Date(d.timestamp).getHours()
            const isDay = hour >= 6 && hour < 18 // Assuming 6AM-6PM is day

            if (timeRange === 'day') return isDay
            if (timeRange === 'night') return !isDay
            return true
        })

        // Check if we have v_1..v_12 or e1..e12 in the data
        const hasV = cageData.length > 0 && 'v_1' in cageData[0]
        const hasE = cageData.length > 0 && 'e1' in cageData[0]

        if (!hasV && !hasE) return null

        const prefix = hasV ? 'v_' : 'e'

        // Aggregate across the selected time period
        const electrodeSums = Array(12).fill(0)
        let validRows = 0

        cageData.forEach(row => {
            let rowHasData = false
            for (let i = 1; i <= 12; i++) {
                const val = parseFloat(row[`${prefix}${i}`])
                if (!isNaN(val)) {
                    electrodeSums[i - 1] += val
                    rowHasData = true
                }
            }
            if (rowHasData) validRows++
        })

        if (validRows === 0) return null

        // Calculate averages instead of sum
        const electrodeAverages = electrodeSums.map(sum => sum / validRows)
        return electrodeAverages

    }, [data, selectedCage, timeRange])

    if (!heatmapData) {
        return (
            <div className="w-full h-64 flex flex-col items-center justify-center text-slate-500 bg-slate-50 rounded-xl border border-dashed border-slate-300">
                <p>Spatial activity data (electrodes 1-12) not found in this dataset.</p>
                <p className="text-xs mt-2">Requires columns like `v_1` to `v_12` or `e1` to `e12`.</p>
            </div>
        )
    }

    const maxVal = Math.max(...heatmapData, 0.0001) // Prevent division by zero

    return (
        <div className="w-full flex flex-col items-center">
            <div className="w-full max-w-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
                <div className="flex items-center gap-3">
                    <span className="text-sm font-semibold text-slate-700">Displaying Heatmap for Cage:</span>
                    <select
                        className="p-2 border rounded-lg text-sm bg-white shadow-sm font-medium"
                        value={selectedCage}
                        onChange={(e) => setSelectedCage(e.target.value)}
                    >
                        {cages.map(c => <option key={c} value={c}>{c}</option>)}
                    </select>
                </div>

                <div className="flex bg-slate-100 p-1 rounded-lg">
                    {(['all', 'day', 'night'] as const).map(tr => (
                        <button
                            key={tr}
                            onClick={() => setTimeRange(tr)}
                            className={`px-4 py-1.5 rounded-md text-xs font-bold capitalize transition-all ${timeRange === tr
                                    ? 'bg-white text-indigo-700 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800'
                                }`}
                        >
                            {tr}
                        </button>
                    ))}
                </div>
            </div>

            <div className="bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
                <div className="text-center mb-6">
                    <h3 className="font-bold text-slate-800">Cage Floor Activity Map</h3>
                    <p className="text-xs text-slate-500">3x4 Electrode Grid Layout (Front to Back)</p>
                </div>

                {/* 3x4 Grid */}
                <div className="grid grid-cols-4 gap-2 w-[400px] h-[300px] mx-auto p-4 bg-slate-100 rounded-xl border-4 border-slate-300 relative shadow-inner">
                    {/* Front label */}
                    <div className="absolute -bottom-6 left-0 right-0 text-center text-xs font-bold text-slate-400 uppercase tracking-widest">
                        Front (Door)
                    </div>
                    {/* Back label */}
                    <div className="absolute -top-6 left-0 right-0 text-center text-xs font-bold text-slate-400 uppercase tracking-widest">
                        Back (Water/Food)
                    </div>

                    {heatmapData.map((val, idx) => {
                        const intensity = val / maxVal
                        // Map intensity to a thermal color (blue -> green -> yellow -> red)
                        // Simplified directly using tailwind via inline style for precise opacity

                        return (
                            <div
                                key={idx}
                                className="relative flex items-center justify-center rounded-lg border border-slate-200 overflow-hidden group"
                                style={{
                                    backgroundColor: `rgba(239, 68, 68, ${intensity})` // Red heatmap
                                }}
                            >
                                <div className="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent pointer-events-none" />
                                <span className={`text-xs font-bold z-10 transition-colors ${intensity > 0.5 ? 'text-white' : 'text-slate-500'}`}>
                                    {val.toFixed(1)}
                                </span>

                                {/* Tooltip */}
                                <div className="absolute opacity-0 group-hover:opacity-100 bg-slate-800 text-white text-[10px] py-1 px-2 rounded -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap z-20 pointer-events-none transition-opacity">
                                    Electrode {idx + 1}: {val.toFixed(2)}
                                </div>
                            </div>
                        )
                    })}
                </div>

                <div className="mt-10 flex items-center justify-center gap-2 text-xs text-slate-500">
                    <span>Low Activity</span>
                    <div className="w-32 h-3 bg-gradient-to-r from-[rgba(239,68,68,0)] to-[rgba(239,68,68,1)] rounded-full border border-slate-200" />
                    <span>High Activity</span>
                </div>
            </div>
        </div>
    )
}
