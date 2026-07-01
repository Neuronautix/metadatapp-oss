import { Link, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Pencil } from 'lucide-react'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { DetailTabs } from '@/components/DetailTabs.tsx'
import { RestrictedActions } from '@/components/RestrictedActions.tsx'
import { ResourceAuditPanel } from '@/features/system/audit/ResourceAuditPanel.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import { useAuth } from '@/app/auth-context.tsx'
import { exportJson } from '@/lib/export.ts'
import { useToast } from '@/components/ui/toast.tsx'
import { accessAreaOptions, normalizeAccessAreas } from '@/lib/user-access.ts'
import { getMetaItemByRef } from '@/metadatapp/api.ts'
import type { MetaUser } from '@/metadatapp/types.ts'
import { getResourceEntry } from '@/metadatapp/registry.ts'
import type { SchemaRef } from '@/metadatapp/openapi-types.ts'
import { asArray, getIdFromIri } from '@/metadatapp/iri.ts'

const asString = (value: unknown): string => (typeof value === 'string' ? value : '')

export function UserViewPage() {
  const { userId } = useParams()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { session } = useAuth()
  const { toast } = useToast()
  const entry = getResourceEntry('users')
  const itemPath = entry?.itemPath && userId ? entry.itemPath.replace('{id}', encodeURIComponent(userId)) : null
  const schemaRef = entry?.itemSchemaRef

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['meta', 'users', userId],
    queryFn: () => getMetaItemByRef<SchemaRef>(itemPath ?? '', schemaRef as SchemaRef),
    enabled: Boolean(itemPath) && Boolean(schemaRef),
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
        title="User unavailable"
        description={(error as Error)?.message ?? 'User record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['meta', 'users', userId] })}
      />
    )
  }

  const allowEdit = canEdit(role)
  const isSuperAdmin = Boolean(session?.user?.isSuperAdmin)
  const user = data as unknown as MetaUser
  const resolvedId = asString(user.id).trim() ? asString(user.id) : getIdFromIri((user as { '@id'?: string })['@id'])
  const firstName = asString(user.firstName)
  const lastName = asString(user.lastName)
  const email = asString(user.email)
  const explicitName = asString(user.name)
  const name = explicitName.trim() ? explicitName : [firstName, lastName].filter(Boolean).join(' ').trim() || email || resolvedId
  const roles = [
    ...asArray(user.roles),
    ...(Array.isArray(user.role) ? user.role : user.role ? [user.role] : []),
  ].filter(Boolean)
  const accountId = getIdFromIri(user.account)
  const connectedApps = asArray(user.connectedApps).map(getIdFromIri).filter(Boolean)
  const orcid = asString(user.orcid).trim()
  const orcidUrl = orcid ? `https://orcid.org/${orcid}` : ''
  const accessLevel = user.accessLevel ?? 'viewer'
  const accessAreas = normalizeAccessAreas(user.accessAreas, accessLevel)

  const overview = (
    <div className="space-y-6">
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Identity</CardTitle>
            <CardDescription>Core identity fields from the API.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-slate-600">
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">First name</p>
                <p className="font-semibold text-slate-700">{firstName || '—'}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Last name</p>
                <p className="font-semibold text-slate-700">{lastName || '—'}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Email</p>
                <p className="font-semibold text-slate-700">{email || '—'}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">ORCID</p>
                {orcid ? (
                  <a className="font-semibold text-slate-700 underline underline-offset-4" href={orcidUrl} target="_blank" rel="noreferrer">
                    {orcid}
                  </a>
                ) : (
                  <p className="font-semibold text-slate-700">—</p>
                )}
              </div>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Access scope</p>
              <div className="mt-2 flex flex-wrap gap-2">
                {accessAreas.map((area) => (
                  <Badge key={area} variant="outline">
                    {accessAreaOptions.find((option) => option.value === area)?.label ?? area}
                  </Badge>
                ))}
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Access</CardTitle>
            <CardDescription>Tenant scope and authorization roles.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-slate-600">
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Account</p>
                <p className="font-semibold text-slate-700">{accountId || '—'}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Connected apps</p>
                <p className="font-semibold text-slate-700">{connectedApps.length ? connectedApps.join(', ') : '—'}</p>
              </div>
            </div>

            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Roles</p>
              <div className="mt-2 flex flex-wrap gap-2">
                {roles.length ? roles.map((value) => <Badge key={value} variant="outline">{value}</Badge>) : <span>—</span>}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <RestrictedActions
        title="Restricted action"
        description="Suspending access requires super-admin approval."
        actionLabel="Request suspension"
        disabled={!isSuperAdmin}
        onAction={() =>
          toast({
            title: 'Suspension queued',
            description: 'Super-admin review required before suspension.',
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
            <Link to="/users">
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">User</p>
            <h2 className="font-display text-2xl">{name}</h2>
            <p className="text-sm text-slate-500">{resolvedId}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => exportJson(user, `user-${resolvedId || userId || 'unknown'}`)}>
            Export JSON
          </Button>
          {allowEdit && resolvedId ? (
            <Button variant="outline" size="sm" asChild>
              <Link to={`/users/${resolvedId}/edit`}>
                <Pencil className="h-4 w-4" />
                Edit
              </Link>
            </Button>
          ) : null}
        </div>
      </div>

      <DetailTabs overview={overview} audit={<ResourceAuditPanel resourceType="user" resourceId={resolvedId || userId || ''} />} />
    </div>
  )
}
