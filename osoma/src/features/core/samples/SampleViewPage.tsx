import { Link, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Pencil } from 'lucide-react'
import { getSample } from './sample.api.ts'
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
import { formatDate, formatDateTime } from '@/lib/format.ts'

export function SampleViewPage() {
  const { sampleId } = useParams()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['sample', sampleId],
    queryFn: () => getSample(sampleId ?? ''),
    enabled: Boolean(sampleId),
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
        title="Sample unavailable"
        description={(error as Error)?.message ?? 'Sample record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['sample', sampleId] })}
      />
    )
  }

  const statusVariant = data.status === 'stored' ? 'success' : data.status === 'processing' ? 'warning' : 'outline'
  const qcVariant = data.qcStatus === 'pass' ? 'success' : data.qcStatus === 'review' ? 'warning' : 'outline'
  const allowEdit = canEdit(role)
  const allowAdmin = isAdmin(role)

  const overview = (
    <div className="space-y-6">
      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <Card>
          <CardHeader>
            <CardTitle>Specimen profile</CardTitle>
            <CardDescription>Subject context, collection metadata, and lineage.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-slate-600">
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Subject</p>
                <p className="font-semibold text-slate-700">{data.subjectLabel}</p>
                <p>{data.subjectId}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Investigation</p>
                <p className="font-semibold text-slate-700">{data.investigationName}</p>
                <p>{data.investigationId}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Type</p>
                <p className="font-semibold text-slate-700">{data.type}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Collected</p>
                <p className="font-semibold text-slate-700">{formatDateTime(data.collectedAt)}</p>
              </div>
            </div>

            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Storage</p>
              <p className="font-semibold text-slate-700">{data.storageLocation}</p>
            </div>

            {data.derivedFrom ? (
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Derived from</p>
                <p className="font-semibold text-slate-700">{data.derivedFrom}</p>
              </div>
            ) : null}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Lifecycle</CardTitle>
            <CardDescription>Operational timestamps.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            <p>Created {formatDate(data.createdAt)}</p>
            <p>Last update {formatDateTime(data.updatedAt)}</p>
            <p>QC status {data.qcStatus}</p>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Identifiers</CardTitle>
            <CardDescription>Barcodes and registry IDs.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            {data.identifiers.map((identifier) => (
              <div key={identifier.value} className="flex items-center justify-between">
                <span className="text-xs uppercase tracking-[0.2em] text-slate-400">{identifier.type}</span>
                <span className="font-medium text-slate-700">{identifier.value}</span>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Chain-of-custody</CardTitle>
            <CardDescription>Traceable handoffs.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            {data.provenance.map((event) => (
              <div key={event.id} className="space-y-1">
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">{formatDate(event.at)}</p>
                <p className="font-semibold text-slate-700">{event.action}</p>
                <p>{event.detail}</p>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      <RestrictedActions
        title="Restricted action"
        description="Sample disposal requires administrative approval."
        actionLabel="Request disposal"
        disabled={!allowAdmin}
        onAction={() =>
          toast({
            title: 'Disposal queued',
            description: 'Administrative review required before disposal.',
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
            <Link to="/samples">
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Sample</p>
            <h2 className="font-display text-2xl">{data.label}</h2>
            <p className="text-sm text-slate-500">{data.id}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={statusVariant}>{data.status}</Badge>
          <Badge variant={qcVariant}>QC {data.qcStatus}</Badge>
          <Button variant="outline" size="sm" onClick={() => exportJson(data, `sample-${data.id}`)}>
            Export JSON
          </Button>
          {allowEdit ? (
            <Button variant="outline" size="sm" asChild>
              <Link to={`/samples/${data.id}/edit`}>
                <Pencil className="h-4 w-4" />
                Edit
              </Link>
            </Button>
          ) : null}
        </div>
      </div>

      <DetailTabs overview={overview} audit={<ResourceAuditPanel resourceType="sample" resourceId={data.id} />} />
    </div>
  )
}
