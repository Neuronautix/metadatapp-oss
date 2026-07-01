import { useMemo } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { getMetaItemByRef, updateMetaItemByRef } from '@/metadatapp/api.ts'
import { getRouteResourceId } from '@/metadatapp/display.ts'
import { getResourceEntry } from '@/metadatapp/registry.ts'
import { getFormFieldsForEntry, getResourceDisplayLabel } from '@/metadatapp/ui-config.ts'
import { MetaResourceForm } from './MetaResourceForm.tsx'
import type { SchemaRef } from '@/metadatapp/openapi-types.ts'

export function MappResourceEditPage() {
  const { resource, id } = useParams()
  const navigate = useNavigate()
  const { toast } = useToast()
  const entry = resource ? getResourceEntry(resource) : undefined

  const schemaRef = entry?.itemSchemaRef ?? entry?.listSchemaRef
  const updateRef = entry?.updateSchemaRef ?? schemaRef
  const encodedId = getRouteResourceId(id, id)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['metadatapp', resource, id, 'edit'],
    queryFn: () => getMetaItemByRef(`${entry?.collectionPath}/${encodedId}`, schemaRef as SchemaRef),
    enabled: Boolean(entry?.collectionPath && id && schemaRef),
  })

  const fields = useMemo(() => getFormFieldsForEntry(entry, updateRef), [entry, updateRef])
  const resourceLabel = getResourceDisplayLabel(entry, 'singular')
  const record = data as Record<string, any> | undefined
  const routeId = getRouteResourceId(record?.id ?? record?.['@id'] ?? id, id)

  const mutation = useMutation({
    mutationFn: (payload: Record<string, any>) =>
      updateMetaItemByRef(
        `${entry?.collectionPath}/${encodedId}`,
        updateRef as SchemaRef,
        payload,
        entry?.supports.updateMethod ?? 'PATCH'
      ),
    onSuccess: () => {
      toast({ title: 'Updated', description: 'Resource updated.' })
      navigate(`/metadata/${entry?.key}/${routeId}`)
    },
  })

  if (!entry || !id) {
    return <EmptyState title="Resource not found" description="Unknown MAPP API resource." />
  }

  if (!schemaRef || !updateRef) {
    return <EmptyState title="Schema missing" description="No schema reference for this resource." />
  }

  if (!entry.supports.update) {
    return <EmptyState title="Edit disabled" description="This resource does not support updates." />
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
        title="Resource unavailable"
        description={(error as Error).message}
        actionLabel="Back"
        onAction={() => navigate(`/metadata/${entry.key}`)}
      />
    )
  }

  const recordData = data as Record<string, any>
  const resourceId = recordData.id ?? recordData['@id'] ?? id

  return (
    <div className="space-y-6">
      <PageHeader title={`Edit ${resourceLabel}`} subtitle={String(resourceId)} />
      <Card>
        <CardHeader>
          <CardTitle>Fields</CardTitle>
        </CardHeader>
        <CardContent>
          <MetaResourceForm
            fields={fields}
            initialData={recordData}
            submitLabel={`Update ${resourceLabel}`}
            onSubmit={(payload) => mutation.mutate(payload)}
            isSubmitting={mutation.isPending}
            assistantConfig={{
              resourceType: entry.key,
              resourceId: String(resourceId),
              resourceLabel,
            }}
          />
        </CardContent>
      </Card>
    </div>
  )
}
