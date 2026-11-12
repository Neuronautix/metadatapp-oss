import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import type { CageDetail } from '../cage.types.ts'
import { formatDateTime } from '@/lib/format.ts'

export function CageOverview({ cage }: { cage: CageDetail }) {
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle>Metadata snapshot</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3 text-sm text-slate-600">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Bedding</p>
            <p className="font-semibold text-slate-800">{cage.bedding}</p>
            <p>{cage.beddingBatch}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Sanitary</p>
            <p className="font-semibold text-slate-800">{cage.sanitary}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Diet / Water</p>
            <p className="font-semibold text-slate-800">{cage.diet}</p>
            <p>{cage.water}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Device + Pipeline</p>
            <p className="font-semibold text-slate-800">{cage.device}</p>
            <p>Firmware {cage.firmware}</p>
            <p>Pipeline {cage.pipelineVersion}</p>
          </div>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>Location + quality</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3 text-sm text-slate-600">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Location</p>
            <p className="font-semibold text-slate-800">{cage.facility}</p>
            <p>
              {cage.room} - {cage.rack}
            </p>
            <p>{cage.cageType}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Alerts</p>
            <p className="font-semibold text-slate-800">{cage.alerts}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Last seen</p>
            <p className="font-semibold text-slate-800">{formatDateTime(cage.lastSeen)}</p>
          </div>
          <div className="rounded-xl border border-line bg-surface-2 p-3 text-xs text-slate-500">
            <div className="flex items-center justify-between">
              <span>Sensor quality</span>
              <Badge variant={cage.quality.sensorHealth === 'good' ? 'success' : 'warning'}>
                {cage.quality.sensorHealth}
              </Badge>
            </div>
            <p className="mt-2">Missing data {cage.quality.missingDataPct}%</p>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
