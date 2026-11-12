import { AlertTriangle, CheckCircle2, MinusCircle, Shuffle } from 'lucide-react'
import type { ReplayResult, ScenarioDelta } from '@/domain/scenario/scenario.types.ts'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { formatDateTime } from '@/lib/format.ts'
import { cn } from '@/lib/utils.ts'

export function TimelineDiffView({ result, isLoading }: { result?: ReplayResult | null; isLoading?: boolean }) {
  if (isLoading) {
    return <Skeleton className="h-40 w-full" />
  }

  if (!result) {
    return null
  }

  const realityActive = result.reality.alarms.filter((alarm) => alarm.status === 'active').length
  const scenarioActive = result.scenario.alarms.filter((alarm) => alarm.status === 'active').length
  const realityOutOfRange = result.reality.metrics.filter((metric) => metric.status === 'out-of-range').length
  const scenarioOutOfRange = result.scenario.metrics.filter((metric) => metric.status === 'out-of-range').length

  return (
    <div className="space-y-4">
      <div className="grid gap-3 md:grid-cols-3">
        <SnapshotStat
          label="Active alarms"
          reality={realityActive}
          scenario={scenarioActive}
          emphasis
        />
        <SnapshotStat label="Out-of-range signals" reality={realityOutOfRange} scenario={scenarioOutOfRange} />
        <SnapshotStat
          label="Timestamp"
          reality={formatDateTime(result.reality.at)}
          scenario={formatDateTime(result.scenario.at)}
        />
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Reality vs Scenario</CardTitle>
          <CardDescription>Divergences and impacts after replay.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {result.deltas.length === 0 ? (
            <p className="text-sm text-slate-500">No divergences detected for this timestamp.</p>
          ) : (
            result.deltas.map((delta) => <DeltaRow key={delta.id} delta={delta} />)
          )}
        </CardContent>
      </Card>
    </div>
  )
}

function SnapshotStat({
  label,
  reality,
  scenario,
  emphasis = false,
}: {
  label: string
  reality: string | number
  scenario: string | number
  emphasis?: boolean
}) {
  const positive = Number(scenario) < Number(reality)
  const Icon = emphasis ? Shuffle : CheckCircle2
  return (
    <Card>
      <CardHeader className="space-y-1 pb-2">
        <CardTitle className="text-sm font-semibold text-slate-600">{label}</CardTitle>
        <CardDescription className="text-xs text-slate-500">Reality → Scenario</CardDescription>
      </CardHeader>
      <CardContent className="flex items-center justify-between">
        <div className="flex items-baseline gap-2 text-xl font-semibold text-slate-900">
          {reality}
          <span className="text-xs text-slate-500">→</span>
          <span
            className={cn(
              'text-lg',
              emphasis && positive ? 'text-emerald-600' : emphasis ? 'text-amber-600' : 'text-slate-800'
            )}
          >
            {scenario}
          </span>
        </div>
        <Badge variant={positive ? 'success' : 'outline'} className="gap-1">
          <Icon className="h-4 w-4" />
          {positive ? 'Improved' : 'Changed'}
        </Badge>
      </CardContent>
    </Card>
  )
}

function DeltaRow({ delta }: { delta: ScenarioDelta }) {
  const iconMap: Record<ScenarioDelta['impact'], JSX.Element> = {
    positive: <CheckCircle2 className="h-4 w-4 text-emerald-600" />,
    negative: <AlertTriangle className="h-4 w-4 text-amber-600" />,
    neutral: <MinusCircle className="h-4 w-4 text-slate-400" />,
  }

  return (
    <div className="flex items-start gap-3 rounded-xl border border-line bg-surface-2 p-3">
      <div className="mt-0.5">{iconMap[delta.impact]}</div>
      <div className="flex-1 space-y-1">
        <div className="flex items-center gap-2">
          <Badge variant="outline" className="capitalize">
            {delta.type}
          </Badge>
          <p className="text-sm font-semibold text-slate-800">{delta.title}</p>
        </div>
        <p className="text-sm text-slate-600">{delta.detail}</p>
      </div>
    </div>
  )
}
