import { useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { createMetaItemByRef } from '@/metadatapp/api.ts'
import { getResourceEntry } from '@/metadatapp/registry.ts'
import { getFormFieldsForEntry } from '@/metadatapp/ui-config.ts'
import { MetaResourceForm } from '@/features/system/metadatapp/MetaResourceForm.tsx'
import type { SchemaRef } from '@/metadatapp/openapi-types.ts'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'

const getItemId = (item: Record<string, any>) => {
  const raw = item.id ?? item['@id']
  if (!raw) return null
  if (typeof raw === 'string') {
    const trimmed = raw.replace(/\/$/, '')
    const parts = trimmed.split('/')
    return parts[parts.length - 1]
  }
  return String(raw)
}

export function UserCreatePage() {
  const navigate = useNavigate()
  const { toast } = useToast()
  const { role } = useRole()
  const allowEdit = canEdit(role)

  const entry = getResourceEntry('users')
  const schemaRef = entry?.createSchemaRef ?? entry?.itemSchemaRef
  const fields = useMemo(() => getFormFieldsForEntry(entry, schemaRef), [entry, schemaRef])

  const mutation = useMutation({
    mutationFn: (payload: Record<string, any>) =>
      createMetaItemByRef(entry?.collectionPath ?? '', schemaRef as SchemaRef, payload),
    onSuccess: (data) => {
      const id = getItemId(data as Record<string, any>)
      toast({ title: 'User created', description: 'User record saved.' })
      if (id) {
        navigate(`/users/${id}`)
      } else {
        navigate('/users')
      }
    },
  })

  if (!allowEdit) {
    return (
      <EmptyState
        title="Access limited"
        description="Your role only allows read-only access."
        actionLabel="Back to users"
        onAction={() => navigate('/users')}
      />
    )
  }

  if (!entry) {
    return <EmptyState title="Resource not found" description="The backend does not expose a users collection." />
  }

  if (!schemaRef) {
    return <EmptyState title="Schema missing" description="No OpenAPI schema reference for users." />
  }

  if (!entry.supports.create) {
    return <EmptyState title="Create disabled" description="The backend does not allow creating users." />
  }

  return (
    <div className="space-y-6">
      <PageHeader title="New user" subtitle="Create a Metadatapp user profile." />
      <Card>
        <CardHeader>
          <CardTitle>Fields</CardTitle>
        </CardHeader>
        <CardContent>
          <MetaResourceForm
            fields={fields}
            submitLabel="Create user"
            onSubmit={(payload) => mutation.mutate(payload)}
            isSubmitting={mutation.isPending}
          />
        </CardContent>
      </Card>
    </div>
  )
}

