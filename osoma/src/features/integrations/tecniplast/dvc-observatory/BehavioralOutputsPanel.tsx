import { Badge } from '@/components/ui/badge.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import type { AIBehaviorOutput } from '@/domain/dvc/dvc.types.ts'

export function BehavioralOutputsPanel({ output }: { output: AIBehaviorOutput | null }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Behavioral outputs</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3 text-sm text-slate-600">
        {output ? (
          <>
            <div className="flex items-center justify-between">
              <span>Label</span>
              <Badge variant="outline">{output.label}</Badge>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Sleep score</p>
                <p className="font-semibold text-slate-800">{output.sleepScore}%</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Circadian stability</p>
                <p className="font-semibold text-slate-800">{output.circadianStability}%</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Fragmentation</p>
                <p className="font-semibold text-slate-800">{output.fragmentation}%</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Anomaly score</p>
                <p className="font-semibold text-slate-800">{output.anomalyScore}%</p>
              </div>
            </div>
            <p className="text-xs text-slate-500">
              {new Date(output.windowStart).toLocaleString()} - {new Date(output.windowEnd).toLocaleString()}
            </p>
            <p>{output.explanation}</p>
          </>
        ) : (
          <p>Behavioral outputs disabled.</p>
        )}
      </CardContent>
    </Card>
  )
}
