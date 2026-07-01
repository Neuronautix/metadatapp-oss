import { Badge } from '@/components/ui/badge.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { formatDateTime } from '@/lib/format.ts'
import type { CageContextSnapshot, QualitySignals } from '@/domain/dvc/dvc.types.ts'

export function ContextSnapshotPanel({
  snapshot,
  quality,
}: {
  snapshot: CageContextSnapshot | null
  quality: QualitySignals | null
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Context snapshot</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3 text-sm text-slate-600">
        {snapshot ? (
          <>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Bedding</p>
              <p className="font-semibold text-slate-800">{snapshot.beddingMaterial}</p>
              <p>{snapshot.beddingBatch}</p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Sanitary status</p>
              <p className="font-semibold text-slate-800">{snapshot.sanitaryStatus}</p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Diet / Water</p>
              <p className="font-semibold text-slate-800">{snapshot.diet}</p>
              <p>{snapshot.water}</p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Cage / Room</p>
              <p className="font-semibold text-slate-800">{snapshot.cageType}</p>
              <p>
                {snapshot.room} - {snapshot.rack}
              </p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Device + Pipeline</p>
              <p className="font-semibold text-slate-800">{snapshot.device}</p>
              <p>Firmware {snapshot.firmware}</p>
              <p>Pipeline {snapshot.pipelineVersion}</p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Last changeout</p>
              <p className="font-semibold text-slate-800">{formatDateTime(snapshot.lastChangeout)}</p>
            </div>
            {quality ? (
              <div className="rounded-xl border border-line bg-surface-2 p-3 text-xs text-slate-500">
                <div className="flex items-center justify-between">
                  <span>Sensor quality</span>
                  <Badge variant={quality.sensorHealth === 'good' ? 'success' : 'warning'}>
                    {quality.sensorHealth}
                  </Badge>
                </div>
                <p className="mt-2">Missing data {quality.missingDataPct}%</p>
                <p>Last seen {formatDateTime(quality.lastSeen)}</p>
              </div>
            ) : null}
          </>
        ) : (
          <p>Loading context snapshot...</p>
        )}
      </CardContent>
    </Card>
  )
}
