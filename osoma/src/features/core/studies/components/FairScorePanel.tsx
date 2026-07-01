import { CheckCircle2, XCircle } from 'lucide-react'
import type { FAIRCriteria, FAIRScore } from '@/domain/resources.ts'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'

type PillarKey = keyof FAIRCriteria

const PILLARS: { key: PillarKey; label: string; scoreKey: keyof FAIRScore }[] = [
  { key: 'findable', label: 'Findable', scoreKey: 'findable' },
  { key: 'accessible', label: 'Accessible', scoreKey: 'accessible' },
  { key: 'interoperable', label: 'Interoperable', scoreKey: 'interoperable' },
  { key: 'reusable', label: 'Reusable', scoreKey: 'reusable' },
]

const PILLAR_COLORS: Record<PillarKey, string> = {
  findable: 'bg-blue-500',
  accessible: 'bg-violet-500',
  interoperable: 'bg-amber-500',
  reusable: 'bg-emerald-500',
}

type Props = {
  score: FAIRScore
  criteria: FAIRCriteria
}

export function FairScorePanel({ score, criteria }: Props) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between gap-3">
        <div>
          <CardTitle>FAIR Score</CardTitle>
          <CardDescription>
            Findability, Accessibility, Interoperability, and Reusability assessment.
          </CardDescription>
        </div>
        <div className="flex flex-col items-end gap-1">
          <span className="text-3xl font-bold text-slate-800">{score.total}</span>
          <span className="text-xs text-slate-400">/ 100</span>
        </div>
      </CardHeader>
      <CardContent className="space-y-6">
        {PILLARS.map(({ key, label, scoreKey }) => (
          <div key={key} className="space-y-2">
            <div className="flex items-center justify-between text-sm">
              <span className="font-semibold text-slate-700">{label}</span>
              <span className="text-xs text-slate-500">{score[scoreKey]}/100</span>
            </div>
            <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
              <div
                className={`h-full rounded-full ${PILLAR_COLORS[key]}`}
                style={{ width: `${score[scoreKey]}%` }}
              />
            </div>
            <ul className="space-y-2 pt-1">
              {criteria[key].map((criterion) => (
                <li key={criterion.id} className="flex items-start gap-2 text-sm">
                  {criterion.pass ? (
                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                  ) : (
                    <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-rose-400" />
                  )}
                  <span>
                    <span className="font-medium text-slate-700">{criterion.id}</span>
                    <span className="ml-1 text-slate-600">{criterion.label}</span>
                    <span className="mt-0.5 block text-xs text-slate-400">{criterion.description}</span>
                  </span>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </CardContent>
    </Card>
  )
}
