import { Link, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Pencil } from 'lucide-react'
import { getOrganization } from './organization.api.ts'
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

export function OrganizationViewPage() {
  const { organizationId } = useParams()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['organization', organizationId],
    queryFn: () => getOrganization(organizationId ?? ''),
    enabled: Boolean(organizationId),
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
        title="Organization unavailable"
        description={(error as Error)?.message ?? 'Organization record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['organization', organizationId] })}
      />
    )
  }

  const typeVariant = data.type === 'consortium' ? 'warning' : data.type === 'biotech' ? 'default' : 'outline'
  const allowEdit = canEdit(role)
  const allowAdmin = isAdmin(role)

  const overview = (
    <div className="space-y-6">
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Organization profile</CardTitle>
            <CardDescription>Location, type, and scale.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-slate-600">
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Type</p>
                <p className="font-semibold text-slate-700">{data.type}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Location</p>
                <p className="font-semibold text-slate-700">{data.location}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Labs</p>
                <p className="font-semibold text-slate-700">{data.labs}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Active investigations</p>
                <p className="font-semibold text-slate-700">{data.activeInvestigations}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Lifecycle</CardTitle>
            <CardDescription>Partnership timestamps.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            <p>Created {formatDate(data.createdAt)}</p>
            <p>Updated {formatDate(data.updatedAt)}</p>
          </CardContent>
        </Card>
      </div>

      <RestrictedActions
        title="Restricted action"
        description="Partnership suspension requires administrative approval."
        actionLabel="Request suspension"
        disabled={!allowAdmin}
        onAction={() =>
          toast({
            title: 'Suspension queued',
            description: 'Administrative review required before suspension.',
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
            <Link to="/organizations">
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Organization</p>
            <h2 className="font-display text-2xl">{data.name}</h2>
            <p className="text-sm text-slate-500">{data.id}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={typeVariant}>{data.type}</Badge>
          <Button variant="outline" size="sm" onClick={() => exportJson(data, `organization-${data.id}`)}>
            Export JSON
          </Button>
          {allowEdit ? (
            <Button variant="outline" size="sm" asChild>
              <Link to={`/organizations/${data.id}/edit`}>
                <Pencil className="h-4 w-4" />
                Edit
              </Link>
            </Button>
          ) : null}
        </div>
      </div>

      <DetailTabs overview={overview} audit={<ResourceAuditPanel resourceType="organization" resourceId={data.id} />} />
    </div>
  )
}
