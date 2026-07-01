import { Link, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Pencil } from 'lucide-react'
import { getAssay } from './assay.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { DetailTabs } from '@/components/DetailTabs.tsx'
import { RestrictedActions } from '@/components/RestrictedActions.tsx'
import { ResourceAuditPanel } from '@/features/system/audit/ResourceAuditPanel.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit, isAdmin } from '@/lib/rbac.ts'
import { exportJson } from '@/lib/export.ts'
import { useToast } from '@/components/ui/toast.tsx'
import { formatDate } from '@/lib/format.ts'

export function AssayViewPage() {
  const { assayId } = useParams()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['assay', assayId],
    queryFn: () => getAssay(assayId ?? ''),
    enabled: Boolean(assayId),
  })

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
        title="Assay unavailable"
        description={(error as Error)?.message ?? 'Assay record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['assay', assayId] })}
      />
    )
  }

  const reviewVariant = data.reviewStatus === 'current' ? 'success' : data.reviewStatus === 'review-due' ? 'warning' : 'outline'
  const allowEdit = canEdit(role)
  const allowAdmin = isAdmin(role)

  const overview = (
    <div className="space-y-6">
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Assay profile</CardTitle>
            <CardDescription>Method, ownership, and version tracking.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-muted">
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-muted">Version</p>
                <p className="font-semibold text-ink">{data.version}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-muted">Method</p>
                <p className="font-semibold text-ink">{data.method}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-muted">Owner</p>
                <p className="font-semibold text-ink">{data.owner}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-muted">Review status</p>
                <p className="font-semibold text-ink">{data.reviewStatus}</p>
              </div>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-muted">Last reviewed</p>
              <p className="font-semibold text-ink">{formatDate(data.lastReviewedAt)}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Lifecycle</CardTitle>
            <CardDescription>Governance timestamps.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-muted">
            <p>Created {formatDate(data.createdAt)}</p>
            <p>Updated {formatDate(data.updatedAt)}</p>
          </CardContent>
        </Card>
      </div>

      <RestrictedActions
        title="Restricted action"
        description="Assay retirement requires administrative approval."
        actionLabel="Request retirement"
        disabled={!allowAdmin}
        onAction={() =>
          toast({
            title: 'Retirement queued',
            description: 'Administrative review required before retirement.',
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
            <Link to="/assays">
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-muted">Assay</p>
            <h2 className="font-display text-2xl">{data.name}</h2>
            <p className="text-sm text-muted">{data.id}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={reviewVariant}>{data.reviewStatus}</Badge>
          <Button variant="outline" size="sm" onClick={() => exportJson(data, `assay-${data.id}`)}>
            Export JSON
          </Button>
          {allowEdit ? (
            <Button variant="outline" size="sm" asChild>
              <Link to={`/assays/${data.id}/edit`}>
                <Pencil className="h-4 w-4" />
                Edit
              </Link>
            </Button>
          ) : null}
        </div>
      </div>

      <DetailTabs overview={overview} audit={<ResourceAuditPanel resourceType="assay" resourceId={data.id} />} />
    </div>
  )
}
