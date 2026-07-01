import { Link } from 'react-router-dom'
import type { Insight } from './observatory.types.ts'
import { insightToneClasses } from './observatory.tokens.ts'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'

export function InsightCards({ insights, isLoading }: { insights: Insight[]; isLoading?: boolean }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Causal highlights</CardTitle>
        <CardDescription>Signals from the living provenance engine.</CardDescription>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <Skeleton className="h-48 w-full" />
        ) : (
          <div className="grid gap-3 md:grid-cols-2">
            {insights.map((insight) => (
              <div key={insight.id} className={`rounded-2xl border p-4 ${insightToneClasses[insight.tone]}`}>
                <div className="flex items-center justify-between">
                  <p className="text-sm font-semibold text-slate-800">{insight.title}</p>
                  {insight.badge ? (
                    <span className="rounded-full bg-white/80 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                      {insight.badge}
                    </span>
                  ) : null}
                </div>
                <p className="mt-2 text-sm text-slate-600">{insight.detail}</p>
                <div className="mt-3">
                  <Link
                    to={insight.actionHref}
                    className="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-900"
                  >
                    {insight.actionLabel}
                    <span className="text-slate-400">→</span>
                  </Link>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
