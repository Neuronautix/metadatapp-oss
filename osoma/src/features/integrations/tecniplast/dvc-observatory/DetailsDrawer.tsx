import { X } from 'lucide-react'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import type { TimelineSelection } from './ActivityTimelineChart.tsx'

export function DetailsDrawer({
  selection,
  onClose,
}: {
  selection: TimelineSelection | null
  onClose: () => void
}) {
  if (!selection) return null

  return (
    <div className="fixed inset-0 z-40 flex justify-end bg-slate-900/40">
      <div className="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-line px-6 py-4">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">DVC Detail</p>
            <h2 className="text-lg font-semibold text-slate-800">{selection.type}</h2>
          </div>
          <Button variant="ghost" size="icon" onClick={onClose}>
            <X className="h-4 w-4" />
          </Button>
        </div>
        <div className="flex-1 space-y-4 overflow-y-auto px-6 py-4 text-sm text-slate-600">
          {selection.type === 'event' ? (
            <>
              <Badge variant="outline">{selection.event.type}</Badge>
              <p className="text-lg font-semibold text-slate-800">Cage event</p>
              <p>{new Date(selection.event.ts).toLocaleString()}</p>
              <p>{selection.event.payload.notes ?? 'Operational adjustment logged.'}</p>
              {selection.event.payload.beddingBatch ? (
                <p>Bedding batch: {selection.event.payload.beddingBatch}</p>
              ) : null}
              {selection.event.payload.fromRoom || selection.event.payload.toRoom ? (
                <p>
                  Move: {selection.event.payload.fromRoom ?? 'Unknown'} -{' '}
                  {selection.event.payload.toRoom ?? 'Unknown'}
                </p>
              ) : null}
            </>
          ) : null}

          {selection.type === 'alarm' ? (
            <>
              <Badge variant="critical">{selection.alarm.severity}</Badge>
              <p className="text-lg font-semibold text-slate-800">{selection.alarm.reasonCode}</p>
              <p>
                {new Date(selection.alarm.startTs).toLocaleString()} -{' '}
                {selection.alarm.endTs ? new Date(selection.alarm.endTs).toLocaleString() : 'Open'}
              </p>
              <p>{selection.alarm.explanation}</p>
              <div className="mt-3">
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Checklist</p>
                <ul className="mt-2 list-disc space-y-1 pl-4">
                  {selection.alarm.actions.map((action) => (
                    <li key={action}>{action}</li>
                  ))}
                </ul>
              </div>
            </>
          ) : null}

          {selection.type === 'ai' ? (
            <>
              <Badge variant="outline">{selection.ai.label}</Badge>
              <p className="text-lg font-semibold text-slate-800">Behavioral output</p>
              <p>
                {new Date(selection.ai.windowStart).toLocaleString()} -{' '}
                {new Date(selection.ai.windowEnd).toLocaleString()}
              </p>
              <p>Sleep score {selection.ai.sleepScore}%</p>
              <p>Circadian stability {selection.ai.circadianStability}%</p>
              <p>Fragmentation {selection.ai.fragmentation}%</p>
              <p>Anomaly score {selection.ai.anomalyScore}%</p>
              <p className="mt-2">{selection.ai.explanation}</p>
            </>
          ) : null}
        </div>
      </div>
    </div>
  )
}
