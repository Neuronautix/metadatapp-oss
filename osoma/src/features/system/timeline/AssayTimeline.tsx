import { useMemo, useState } from 'react'
import type { TimelineEvent, TimelineEventKind } from '@/domain/events/event.types.ts'
import { HorizontalTimeline, TimelineNode } from '@/components/ui/HorizontalTimeline.tsx'
import { CheckCircle2, Clock, Link as LinkIcon, PenLine, PlusCircle, Share, Unlink } from 'lucide-react'

const zoomOptions = [7, 30, 90] as const

const kindLabels: Record<TimelineEventKind, string> = {
    created: 'Created',
    updated: 'Updated',
    linked: 'Linked',
    unlinked: 'Unlinked',
    status: 'Status',
    scheduled: 'Scheduled',
    exported: 'Exported',
}

const getEventMapping = (kind: TimelineEventKind): { color: TimelineNode['color'], icon: React.ReactNode } => {
    switch (kind) {
        case 'created':
            return { color: 'green', icon: <PlusCircle /> }
        case 'updated':
            return { color: 'blue', icon: <PenLine /> }
        case 'linked':
            return { color: 'indigo', icon: <LinkIcon /> }
        case 'unlinked':
            return { color: 'slate', icon: <Unlink /> }
        case 'status':
            return { color: 'amber', icon: <CheckCircle2 /> }
        case 'scheduled':
            return { color: 'purple', icon: <Clock /> }
        case 'exported':
            return { color: 'slate', icon: <Share /> }
        default:
            return { color: 'slate', icon: undefined }
    }
}

export function AssayTimeline({ events }: { events: TimelineEvent[] }) {
    const [filter, setFilter] = useState<TimelineEventKind | 'all'>('all')
    const [zoomDays, setZoomDays] = useState<(typeof zoomOptions)[number]>(30)

    const kinds = useMemo(
        () => Array.from(new Set(events.map((event) => event.kind))),
        [events]
    )

    const now = Date.now()
    const filtered = useMemo(() => {
        const threshold = now - zoomDays * 24 * 60 * 60 * 1000

        return events
            .filter((event) => (filter === 'all' ? true : event.kind === filter))
            .filter((event) => new Date(event.at).getTime() >= threshold)
    }, [events, filter, zoomDays])

    const timelineNodes = useMemo<TimelineNode[]>(() => {
        return filtered.map(event => {
            const { color, icon } = getEventMapping(event.kind)
            return {
                id: event.id,
                title: event.title,
                subtitle: event.detail,
                timestamp: new Date(event.at),
                color,
                icon
            }
        })
    }, [filtered])

    if (!events.length) {
        return <p className="text-sm text-slate-500">No assay events available for this resource yet.</p>
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        className={`rounded-full border px-3 py-1 text-xs transition-colors ${filter === 'all' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50'}`}
                        onClick={() => setFilter('all')}
                    >
                        All
                    </button>
                    {kinds.map((kind) => (
                        <button
                            key={kind}
                            className={`rounded-full border px-3 py-1 text-xs transition-colors ${filter === kind ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50'}`}
                            onClick={() => setFilter(kind)}
                        >
                            {kindLabels[kind]}
                        </button>
                    ))}
                </div>

                <div className="flex items-center gap-2 text-xs">
                    {zoomOptions.map((value) => (
                        <button
                            key={value}
                            className={`rounded-full border px-3 py-1 transition-colors ${zoomDays === value ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'}`}
                            onClick={() => setZoomDays(value)}
                        >
                            {value}d
                        </button>
                    ))}
                </div>
            </div>

            <div className="rounded-2xl border border-line bg-white/50 backdrop-blur-sm px-2 py-4 shadow-subtle relative min-h-[160px] overflow-x-hidden">
                {timelineNodes.length > 0 ? (
                    <HorizontalTimeline nodes={timelineNodes} className="w-full" />
                ) : (
                    <div className="absolute inset-0 flex items-center justify-center">
                        <p className="text-sm text-slate-500">No events found in this timeframe.</p>
                    </div>
                )}
            </div>
        </div>
    )
}
