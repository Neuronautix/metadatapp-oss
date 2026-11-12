import { Link, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Pencil } from 'lucide-react'
import { getDataset } from './dataset.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { DetailTabs } from '@/components/DetailTabs.tsx'
import { RestrictedActions } from '@/components/RestrictedActions.tsx'
import { ResourceAuditPanel } from '@/features/system/audit/ResourceAuditPanel.tsx'
import { Timeline } from '@/features/system/audit/Timeline.tsx'
import { useTimelineEvents } from '@/features/system/audit/useTimelineEvents.ts'
import { useRole } from '@/app/role-context.tsx'
import { FairScorePanel } from '@/features/core/studies/components/FairScorePanel.tsx'
import { canEdit, isAdmin } from '@/lib/rbac.ts'
import { exportJson } from '@/lib/export.ts'
import { useToast } from '@/components/ui/toast.tsx'
import { computeFairCriteria, fairVariantFromScore } from '@/lib/fair.ts'
import { formatDate, formatNumber } from '@/lib/format.ts'

export function DatasetViewPage() {
  const { datasetId } = useParams()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['dataset', datasetId],
    queryFn: () => getDataset(datasetId ?? ''),
    enabled: Boolean(datasetId),
  })

  const timeline = useTimelineEvents('dataset', data?.id ?? '')

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <EmptyState
        title="Dataset unavailable"
        description={(error as Error)?.message ?? 'Dataset record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['dataset', datasetId] })}
      />
    )
  }

  const statusVariant = data.status === 'published' ? 'success' : data.status === 'restricted' ? 'warning' : 'outline'
  const fairVariant = fairVariantFromScore(data.fairScore.total)
  const allowEdit = canEdit(role)
  const allowAdmin = isAdmin(role)

  const overview = (
    <div className="space-y-6">
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Dataset profile</CardTitle>
            <CardDescription>Format, investigation linkage, and release status.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-muted">
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-muted">Investigation</p>
                <p className="font-semibold text-ink">{data.investigationName}</p>
                <p>{data.investigationId}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-muted">Format</p>
                <p className="font-semibold text-ink">{data.format}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-muted">Size</p>
                <p className="font-semibold text-ink">{formatNumber(data.sizeGb)} GB</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-muted">DOI</p>
                <p className="font-semibold text-ink">{data.doi ?? 'Not assigned'}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Lifecycle</CardTitle>
            <CardDescription>Release timestamps.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-muted">
            <p>Created {formatDate(data.createdAt)}</p>
            <p>Updated {formatDate(data.updatedAt)}</p>
          </CardContent>
        </Card>
      </div>

      <FairScorePanel score={data.fairScore} criteria={computeFairCriteria(data)} />

      <Card>
        <CardHeader>
          <CardTitle>FAIR timeline</CardTitle>
          <CardDescription>Export, access, and provenance events.</CardDescription>
        </CardHeader>
        <CardContent>
          {timeline.isLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 3 }).map((_, index) => (
                <Skeleton key={index} className="h-16 w-full" />
              ))}
            </div>
          ) : timeline.isError ? (
            <EmptyState
              title="Timeline unavailable"
              description={timeline.error?.message ?? 'Unable to load timeline.'}
              actionLabel="Retry"
              onAction={timeline.refetch}
            />
          ) : (
            <Timeline events={timeline.events} />
          )}
        </CardContent>
      </Card>

      <RestrictedActions
        title="Restricted action"
        description="Dataset embargo changes require administrative approval."
        actionLabel="Request embargo change"
        disabled={!allowAdmin}
        onAction={() =>
          toast({
            title: 'Embargo queued',
            description: 'Administrative review required before release status change.',
          })
        }
      />
    </div>
  )

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to="/datasets">
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-muted">Dataset</p>
            <h2 className="font-display text-2xl">{data.title}</h2>
            <p className="text-sm text-muted">{data.id}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={statusVariant}>{data.status}</Badge>
          <Badge variant={fairVariant}>{data.fairScore.total}/100</Badge>
          <Button variant="outline" size="sm" onClick={() => exportJson(data, `dataset-${data.id}`)}>
            Export JSON
          </Button>
          {allowEdit ? (
            <Button variant="outline" size="sm" asChild>
              <Link to={`/datasets/${data.id}/edit`}>
                <Pencil className="h-4 w-4" />
                Edit
              </Link>
            </Button>
          ) : null}
        </div>
      </div>

      <DetailTabs overview={overview} audit={<ResourceAuditPanel resourceType="dataset" resourceId={data.id} />} />
    </div>
  )
}
