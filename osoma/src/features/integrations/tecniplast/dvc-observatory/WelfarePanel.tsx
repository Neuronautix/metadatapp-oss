import { useState } from 'react'
import { AlertTriangle } from 'lucide-react'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import type { AnomalyReport } from '@/domain/dvc/dvc.anomalies.ts'

const checklist = [
  'Verify hydration and feed availability',
  'Check cage changeout schedule',
  'Review environmental thresholds',
  'Escalate to vet if persistent',
]

export function WelfarePanel({ report }: { report: AnomalyReport | null }) {
  const [acknowledged, setAcknowledged] = useState(false)

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle>Welfare panel</CardTitle>
        <AlertTriangle className="h-4 w-4 text-amber-500" />
      </CardHeader>
      <CardContent className="space-y-3 text-sm text-slate-600">
        {report ? (
          <>
            <div className="flex items-center justify-between">
              <span>Risk score</span>
              <Badge
                variant={report.risk === 'High' ? 'critical' : report.risk === 'Medium' ? 'warning' : 'success'}
              >
                {report.risk}
              </Badge>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Reasons</p>
              <ul className="mt-2 list-disc space-y-1 pl-4">
                {report.reasons.length ? report.reasons.map((reason) => <li key={reason}>{reason}</li>) : <li>No active welfare anomalies.</li>}
              </ul>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Checklist</p>
              <ul className="mt-2 space-y-2">
                {checklist.map((item) => (
                  <li key={item} className="flex items-center gap-2">
                    <input type="checkbox" checked={acknowledged} readOnly />
                    <span>{item}</span>
                  </li>
                ))}
              </ul>
            </div>
            <Button variant={acknowledged ? 'outline' : 'default'} onClick={() => setAcknowledged((prev) => !prev)}>
              {acknowledged ? 'Acknowledged' : 'Acknowledge welfare review'}
            </Button>
          </>
        ) : (
          <p>Waiting for welfare signals...</p>
        )}
      </CardContent>
    </Card>
  )
}
