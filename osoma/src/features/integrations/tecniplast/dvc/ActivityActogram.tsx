import { useMemo, useState } from 'react'
import type { ParsedMetricData } from './DVCDataVisualizer.tsx'

interface ActivityActogramProps {
    data: ParsedMetricData[]
    metric: string
    cages: string[]
}

export function ActivityActogram({ data, metric, cages }: ActivityActogramProps) {
    const [selectedCage, setSelectedCage] = useState<string>(cages[0] || '')

    const { days, grid, maxVal } = useMemo(() => {
        if (!selectedCage) return { days: [], grid: {}, maxVal: 1 }

        const cageData = data.filter(d => d.cage === selectedCage)
        const daysTracker: Record<string, number[]> = {}
        let currentMax = 0

        // Bin size: 15 minutes = 96 bins per day
        const BINS = 96

        cageData.forEach(row => {
            const val = parseFloat(row[metric])
            if (isNaN(val)) return

            const ts = new Date(row.timestamp)
            const dayStr = ts.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
            const hour = ts.getHours()
            const min = ts.getMinutes()

            // Calculate bin index (0 to 95)
            const binIndex = Math.floor((hour * 60 + min) / 15)

            if (!daysTracker[dayStr]) {
                daysTracker[dayStr] = Array(BINS).fill(0)
            }

            daysTracker[dayStr][binIndex] += val
        })

        // Find max bin value for normalization
        Object.values(daysTracker).forEach(bins => {
            bins.forEach(v => {
                if (v > currentMax) currentMax = v
            })
        })

        return {
            days: Object.keys(daysTracker).sort((a, b) => new Date(a).getTime() - new Date(b).getTime()),
            grid: daysTracker,
            maxVal: currentMax > 0 ? currentMax : 1
        }
    }, [data, selectedCage, metric])


    return (
        <div className="w-full flex flex-col items-center">
            <div className="w-full max-w-sm mb-6 flex items-center justify-between">
                <span className="text-sm font-semibold text-slate-700">Displaying Actogram for Cage:</span>
                <select
                    className="p-2 border rounded-lg text-sm bg-white shadow-sm"
                    value={selectedCage}
                    onChange={(e) => setSelectedCage(e.target.value)}
                >
                    {cages.map(c => <option key={c} value={c}>{c}</option>)}
                </select>
            </div>

            <div className="w-full bg-slate-900 rounded-xl p-6 shadow-inner border border-slate-800 text-slate-200">
                <h3 className="text-center font-bold tracking-widest text-slate-400 text-xs mb-4 uppercase">
                    Circadian Rhythm / Raster Plot
                </h3>

                <div className="relative">
                    {/* Header: hours */}
                    <div className="flex ml-24 pr-4 border-b border-slate-700 mb-2 pb-2">
                        {Array.from({ length: 96 }).map((_, i) => {
                            if (i % 24 === 0) { // every 6 hours
                                return (
                                    <div key={i} className="flex-1 text-xs text-slate-500 -ml-2 text-left">
                                        {i / 4}h
                                    </div>
                                )
                            }
                            return <div key={i} className="flex-1"></div>;
                        })}
                        <div className="text-xs text-slate-500 absolute right-0 top-0">24h</div>
                    </div>

                    {/* Rows: days */}
                    {days.map(day => (
                        <div key={day} className="flex h-10 items-end group hover:bg-slate-800/50 transition-colors rounded">
                            <div className="w-24 flex-shrink-0 text-xs text-amber-500/80 font-medium py-1 translate-y-2">
                                {day}
                            </div>
                            <div className="flex-1 flex gap-[1px] h-full items-end border-b border-slate-800 pb-1 pr-4 relative">
                                {grid[day].map((val, i) => {
                                    const intensity = val / maxVal
                                    return (
                                        <div
                                            key={i}
                                            className="flex-1 rounded-[1px] bg-emerald-400 min-h-[2px]"
                                            style={{
                                                height: `${Math.max(2, intensity * 100)}%`,
                                                opacity: Math.max(0.1, intensity + 0.2)
                                            }}
                                            title={`${Math.floor(i / 4)}:${(i % 4) * 15} - Activity: ${val.toFixed(1)}`}
                                        />
                                    )
                                })}
                            </div>
                        </div>
                    ))}

                    {days.length === 0 && (
                        <div className="h-40 flex items-center justify-center text-slate-600">
                            No telemetry data available to plot actogram.
                        </div>
                    )}
                </div>
            </div>
        </div>
    )
}
