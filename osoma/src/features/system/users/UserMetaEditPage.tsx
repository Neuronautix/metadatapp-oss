import { useMemo } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { getMetaItemByRef, updateMetaItemByRef } from '@/metadatapp/api.ts'
import { getResourceEntry } from '@/metadatapp/registry.ts'
import { getFormFieldsForEntry } from '@/metadatapp/ui-config.ts'
import { MetaResourceForm } from '@/features/system/metadatapp/MetaResourceForm.tsx'
import type { SchemaRef } from '@/metadatapp/openapi-types.ts'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'

const fillItemPath = (template: string, id: string) => template.replace('{id}', encodeURIComponent(id))

export function UserMetaEditPage() {
  const { userId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const { role } = useRole()
  const allowEdit = canEdit(role)

  const entry = getResourceEntry('users')
  const itemPath = entry?.itemPath && userId ? fillItemPath(entry.itemPath, userId) : null
  const itemSchemaRef = entry?.itemSchemaRef
  const updateSchemaRef = entry?.updateSchemaRef ?? entry?.itemSchemaRef
  const updateMethod = entry?.supports.updateMethod ?? 'PATCH'

  const fields = useMemo(() => getFormFieldsForEntry(entry, updateSchemaRef), [entry, updateSchemaRef])

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['meta', 'users', userId],
    queryFn: () => getMetaItemByRef(itemPath ?? '', itemSchemaRef as SchemaRef),
    enabled: Boolean(itemPath) && Boolean(itemSchemaRef),
  })

  const mutation = useMutation({
    mutationFn: (payload: Record<string, any>) =>
      updateMetaItemByRef(itemPath ?? '', updateSchemaRef as SchemaRef, payload, updateMethod),
    onSuccess: () => {
      toast({ title: 'User updated', description: 'User record saved.' })
      queryClient.invalidateQueries({ queryKey: ['meta', 'users', userId] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
      if (userId) navigate(`/users/${userId}`)
    },
  })

  if (!allowEdit) {
    return (
      <EmptyState
        title="Access limited"
        description="Your role only allows read-only access."
        actionLabel="Back to user"
        onAction={() => navigate(userId ? `/users/${userId}` : '/users')}
      />
    )
  }

  if (!entry || !entry.itemPath) {
    return <EmptyState title="Resource not found" description="The backend does not expose a user item route." />
  }

  if (!userId) {
    return <EmptyState title="User missing" description="No user identifier provided." />
  }

  if (!itemSchemaRef || !updateSchemaRef) {
    return <EmptyState title="Schema missing" description="No OpenAPI schema reference for users." />
  }

  if (!entry.supports.update) {
    return <EmptyState title="Update disabled" description="The backend does not allow updating users." />
  }

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

  return (
    <div className="space-y-6">
      <PageHeader title="Edit user" subtitle={itemPath ?? undefined} />
      <Card>
        <CardHeader>
          <CardTitle>Fields</CardTitle>
        </CardHeader>
        <CardContent>
          <MetaResourceForm
            fields={fields}
            initialData={data as Record<string, any>}
            submitLabel="Save user"
            onSubmit={(payload) => mutation.mutate(payload)}
            isSubmitting={mutation.isPending}
          />
        </CardContent>
      </Card>
    </div>
  )
}

