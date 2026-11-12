import { Link, useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { getInvestigationActivity, getInvestigations } from './investigation.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { formatDate, formatDateTime } from '@/lib/format.ts'
import { Feature } from '@/feature-flags/Feature.tsx'
import type { InvestigationOperator } from './investigation.types.ts'
import { fairVariantFromScore } from '@/lib/fair.ts'

export function InvestigationsPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['investigations'],
    queryFn: getInvestigations,
  })

  const activityQuery = useQuery({
    queryKey: ['investigations-activity'],
    queryFn: getInvestigationActivity,
  })

  return (
    <div className="space-y-6">
      <PageHeader
        title="Investigations"
        subtitle="Investigation portfolio with operators, schedules, FAIR checks, and live animal assignments."
        actions={
          <Button asChild>
            <Link to="/investigations/new">New investigation</Link>
          </Button>
        }
      />

      <Card>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 className="font-display text-lg">Recent activity</h3>
            <p className="text-sm text-slate-500">Cross-investigation updates in the last 24h.</p>
          </div>
        </div>
        <div className="mt-4 space-y-3">
          {activityQuery.isLoading ? (
            <Skeleton className="h-24 w-full" />
          ) : activityQuery.data?.data.length ? (
            activityQuery.data.data.map((item) => (
              <div key={item.id} className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-line bg-white p-4 text-sm">
                <div>
                  <Link to={`/investigations/${item.investigationId}`} className="font-semibold text-slate-800">
                    {item.investigationName}
                  </Link>
                  <p className="text-xs text-slate-500">{item.title}</p>
                  <p className="text-xs text-slate-400">{item.detail}</p>
                </div>
                <div className="text-right text-xs text-slate-400">
                  <p>{formatDateTime(item.at)}</p>
                  <p>{item.actor}</p>
                </div>
              </div>
            ))
          ) : (
            <p className="text-sm text-slate-500">No activity logged yet.</p>
          )}
        </div>
      </Card>

      <Card>
        {isLoading ? (
          <div className="space-y-3 p-6">
            {Array.from({ length: 6 }).map((_, index) => (
              <Skeleton key={index} className="h-12 w-full" />
            ))}
          </div>
        ) : isError ? (
          <div className="p-6">
            <EmptyState
              title="Investigation feed unavailable"
              description={(error as Error).message}
              actionLabel="Retry"
              onAction={() => queryClient.invalidateQueries({ queryKey: ['investigations'] })}
            />
          </div>
        ) : data && data.data.length === 0 ? (
          <div className="p-6">
            <EmptyState
              title="No investigations yet"
              description="Create your first investigation to start assigning studies and animals."
              actionLabel="New investigation"
              onAction={() => navigate('/investigations/new')}
            />
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Investigation</TableHead>
                <TableHead>Species</TableHead>
                <TableHead>Schedule</TableHead>
                <TableHead>Operators</TableHead>
                <TableHead>Animals</TableHead>
                <TableHead>FAIR</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.data.map((investigation) => (
                <TableRow key={investigation.id}>
                  <TableCell>
                    <Link to={`/investigations/${investigation.id}`} className="font-medium text-slate-900">
                      {investigation.name}
                    </Link>
                    <p className="text-xs text-slate-500">{investigation.description}</p>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline">{investigation.species}</Badge>
                  </TableCell>
                  <TableCell>
                    <p className="text-sm text-slate-700">{investigation.startAt ? formatDate(investigation.startAt) : 'N/A'}</p>
                    <p className="text-xs text-slate-400">
                      {investigation.endAt ? `Ends ${formatDate(investigation.endAt)}` : 'Open-ended'}
                    </p>
                  </TableCell>
                  <TableCell>
                    <div className="flex flex-wrap gap-2">
                      {investigation.assignedOperators.map((operator: InvestigationOperator) => (
                        <Badge key={operator.id} variant="outline">
                          {operator.name}
                        </Badge>
                      ))}
                    </div>
                  </TableCell>
                  <TableCell className="text-sm text-slate-700">{investigation.assignedAnimalIds.length}</TableCell>
                  <TableCell>
                    <Badge variant={fairVariantFromScore(investigation.fairScore.total)} aria-label={`FAIR score: ${investigation.fairScore.total} out of 100`}>
                      {investigation.fairScore.total}/100
                    </Badge>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>

      <Feature flag="feature.future">
        <Card className="border-dashed border-line bg-surface-2">
          <div className="space-y-2">
            <p className="text-sm font-semibold text-slate-900">Future features in stealth</p>
            <p className="text-xs text-slate-500">
              These areas are dark but ready to light up once the guard rails are in place.
            </p>
            <ul className="ml-4 list-disc space-y-1 text-sm text-slate-600">
              <li>AI captain mode that narrates the next 24h of studies.</li>
              <li>Cross-investigation narrative builder for exec-level summaries.</li>
              <li>Virtual operations console for remote controller handoffs.</li>
            </ul>
          </div>
        </Card>
      </Feature>
    </div>
  )
}
