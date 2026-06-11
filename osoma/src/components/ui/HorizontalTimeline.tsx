import * as React from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { cn } from '@/lib/utils'

export interface TimelineNode {
    id: string
    title: string
    subtitle?: string
    timestamp: Date
    icon?: React.ReactNode
    color?: 'blue' | 'green' | 'amber' | 'red' | 'slate' | 'indigo' | 'purple'
}

interface HorizontalTimelineProps {
    nodes: TimelineNode[]
    className?: string
}

const colorMap = {
    blue: 'bg-blue-100 text-blue-700 border-blue-200',
    green: 'bg-green-100 text-green-700 border-green-200',
    amber: 'bg-amber-100 text-amber-700 border-amber-200',
    red: 'bg-red-100 text-red-700 border-red-200',
    slate: 'bg-slate-100 text-slate-700 border-slate-200',
    indigo: 'bg-indigo-100 text-indigo-700 border-indigo-200',
    purple: 'bg-purple-100 text-purple-700 border-purple-200',
}

const nodePointMap = {
    blue: 'bg-blue-500 ring-blue-100',
    green: 'bg-green-500 ring-green-100',
    amber: 'bg-amber-500 ring-amber-100',
    red: 'bg-red-500 ring-red-100',
    slate: 'bg-slate-400 ring-slate-100',
    indigo: 'bg-indigo-500 ring-indigo-100',
    purple: 'bg-purple-500 ring-purple-100',
}

export function HorizontalTimeline({ nodes, className }: HorizontalTimelineProps) {
    const scrollRef = React.useRef<HTMLDivElement>(null)

    const scrollLeft = () => {
        if (scrollRef.current) {
            scrollRef.current.scrollBy({ left: -300, behavior: 'smooth' })
        }
    }

    const scrollRight = () => {
        if (scrollRef.current) {
            scrollRef.current.scrollBy({ left: 300, behavior: 'smooth' })
        }
    }

    // Sort by chronological order
    const sortedNodes = React.useMemo(() => {
        return [...nodes].sort((a, b) => a.timestamp.getTime() - b.timestamp.getTime())
    }, [nodes])

    if (!sortedNodes.length) return null

    return (
        <div className={cn("relative group", className)}>
            <button
                onClick={scrollLeft}
                className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4 z-10 p-1.5 rounded-full bg-white shadow-md border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 opacity-0 group-hover:opacity-100 transition-opacity"
            >
                <ChevronLeft className="h-4 w-4" />
            </button>

            <div
                ref={scrollRef}
                className="flex overflow-x-auto items-start pt-4 pb-2 px-6 gap-8 scrollbar-hide snap-x scroll-smooth"
                style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
            >
                {sortedNodes.map((node, i) => {
                    const colorList = colorMap[node.color || 'slate']
                    const pointColor = nodePointMap[node.color || 'slate']
                    const isLast = i === sortedNodes.length - 1

                    return (
                        <div key={node.id} className="relative flex-none snap-center flex flex-col items-center group/node min-w-[8rem]">
                            {/* Connective line — from this node's centre to the next node's centre */}
                            {!isLast && (
                                <div className="absolute top-4 left-1/2 w-[calc(100%+2rem)] h-[2px] bg-slate-200 z-0 -translate-y-1/2" />
                            )}

                            {/* Node Point */}
                            <div className={cn("relative z-10 w-8 h-8 rounded-full flex items-center justify-center ring-4 transition-transform group-hover/node:scale-110 cursor-pointer shadow-sm", colorList)}>
                                {node.icon ? (
                                    <span className="flex items-center justify-center w-full h-full [&>svg]:w-4 [&>svg]:h-4">
                                        {node.icon}
                                    </span>
                                ) : (
                                    <div className={cn("w-2.5 h-2.5 rounded-full", pointColor)} />
                                )}
                            </div>

                            {/* Node Content — in normal flow so the container grows to fit */}
                            <div className="mt-3 w-full text-center opacity-70 group-hover/node:opacity-100 transition-opacity">
                                <p className="text-xs font-semibold text-slate-800 break-words leading-snug line-clamp-2">{node.title}</p>
                                {node.subtitle && <p className="text-[10px] text-slate-500 mt-0.5 line-clamp-1">{node.subtitle}</p>}
                                <p className="text-[10px] text-slate-400 mt-1 font-mono">
                                    {new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(node.timestamp)}
                                </p>
                            </div>
                        </div>
                    )
                })}
            </div>

            <button
                onClick={scrollRight}
                className="absolute right-0 top-1/2 -translate-y-1/2 translate-x-4 z-10 p-1.5 rounded-full bg-white shadow-md border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 opacity-0 group-hover:opacity-100 transition-opacity"
            >
                <ChevronRight className="h-4 w-4" />
            </button>
        </div>
    )
}
