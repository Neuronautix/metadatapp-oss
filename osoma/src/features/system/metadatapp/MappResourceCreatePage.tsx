import { useMemo } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { createMetaItemByRef } from '@/metadatapp/api.ts'
import { getRouteResourceId } from '@/metadatapp/display.ts'
import { getResourceEntry } from '@/metadatapp/registry.ts'
import { getFormFieldsForEntry, getResourceDisplayLabel } from '@/metadatapp/ui-config.ts'
import { MetaResourceForm } from './MetaResourceForm.tsx'
import type { SchemaRef } from '@/metadatapp/openapi-types.ts'

export function MappResourceCreatePage() {
  const { resource } = useParams()
  const navigate = useNavigate()
  const { toast } = useToast()
  const entry = resource ? getResourceEntry(resource) : undefined

  const schemaRef = entry?.createSchemaRef ?? entry?.itemSchemaRef
  const fields = useMemo(() => getFormFieldsForEntry(entry, schemaRef), [entry, schemaRef])
  const resourceLabel = getResourceDisplayLabel(entry, 'singular')

  const mutation = useMutation({
    mutationFn: (payload: Record<string, any>) =>
      createMetaItemByRef(entry?.collectionPath ?? '', schemaRef as SchemaRef, payload),
    onSuccess: (data) => {
      const record = data as Record<string, any>
      const id = getRouteResourceId(record.id ?? record['@id'])
      toast({ title: 'Created', description: 'Resource created.' })
      if (id) {
        navigate(`/metadata/${entry?.key}/${id}`)
      } else {
        navigate(`/metadata/${entry?.key}`)
      }
    },
  })

  if (!entry) {
    return <EmptyState title="Resource not found" description="Unknown MAPP API resource." />
  }

  if (!schemaRef) {
    return <EmptyState title="Schema missing" description="No schema reference for this resource." />
  }

  if (!entry.supports.create) {
    return <EmptyState title="Create disabled" description="This resource does not support create." />
  }

  return (
    <div className="space-y-6">
      <PageHeader title={`New ${resourceLabel}`} subtitle={entry.collectionPath} />
      <Card>
        <CardHeader>
          <CardTitle>Fields</CardTitle>
        </CardHeader>
        <CardContent>
          <MetaResourceForm
            fields={fields}
            submitLabel={`Create ${resourceLabel}`}
            onSubmit={(payload) => mutation.mutate(payload)}
            isSubmitting={mutation.isPending}
            assistantConfig={{
              resourceType: entry.key,
              resourceLabel,
            }}
          />
        </CardContent>
      </Card>
    </div>
  )
}
