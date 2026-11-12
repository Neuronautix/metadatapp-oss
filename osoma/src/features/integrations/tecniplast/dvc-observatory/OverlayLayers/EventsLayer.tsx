import { PawPrint, Sparkles, Trash2, Utensils, ArrowRightLeft } from 'lucide-react'
import type { CageEvent } from '@/domain/dvc/dvc.types.ts'
import type { TimelineSelection } from '../ActivityTimelineChart.tsx'

const toTime = (value: string) => new Date(value).getTime()

const eventIcons: Record<CageEvent['type'], typeof ArrowRightLeft> = {
  move: ArrowRightLeft,
  bedding_change: Trash2,
  enrichment_added: Sparkles,
  cleaning: Trash2,
  feed_change: Utensils,
}

const eventColors: Record<CageEvent['type'], string> = {
  move: '#38bdf8',
  bedding_change: '#f97316',
  enrichment_added: '#a855f7',
  cleaning: '#94a3b8',
  feed_change: '#22c55e',
}

export function EventsLayer({
  events,
  start,
  end,
  onSelect,
}: {
  events: CageEvent[]
  start: number
  end: number
  onSelect: (value: TimelineSelection) => void
}) {
  return (
    <g>
      {events.map((event, index) => {
        const x = ((toTime(event.ts) - start) / Math.max(1, end - start)) * 860
        const Icon = eventIcons[event.type] ?? PawPrint
        return (
          <g key={event.id} transform={`translate(${x}, 18)`} onClick={() => onSelect({ type: 'event', event })}>
            <circle r={7} fill={eventColors[event.type]} opacity={0.9} />
            <Icon x={-4} y={-4} size={8} color="#0f172a" />
            <text x={0} y={-10 - (index % 2) * 12} textAnchor="middle" fontSize="9" fill="#0f172a">
              {event.type.replace('_', ' ')}
            </text>
          </g>
        )
      })}
    </g>
  )
}
