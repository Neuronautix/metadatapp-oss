import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { getDataset, updateDataset } from './dataset.api.ts'
import type { Dataset } from '@/domain/resources.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'

const statusOptions: Dataset['status'][] = ['draft', 'published', 'restricted']
const formatOptions = ['FASTQ', 'CSV', 'HDF5', 'JSON-LD'] as const

export function DatasetEditPage() {
  const { datasetId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()
  const allowEdit = canEdit(role)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['dataset', datasetId],
    queryFn: () => getDataset(datasetId ?? ''),
    enabled: Boolean(datasetId) && allowEdit,
  })

  const [formState, setFormState] = useState<{
    title: string
    format: (typeof formatOptions)[number]
    status: Dataset['status']
    doi: string
  }>({
    title: '',
    format: formatOptions[0],
    status: statusOptions[0],
    doi: '',
  })

  const lastInitializedId = useRef<string | null>(null)

  useEffect(() => {
    if (data && data.id !== lastInitializedId.current) {
      lastInitializedId.current = data.id
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setFormState({
        title: data.title,
        format: data.format as (typeof formatOptions)[number],
        status: data.status,
        doi: data.doi ?? '',
      })
    }
  }, [data])

  const mutation = useMutation({
    mutationFn: () =>
      updateDataset(datasetId ?? '', {
        title: formState.title,
        format: formState.format,
        status: formState.status,
        doi: formState.doi || undefined,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['datasets'] })
      queryClient.invalidateQueries({ queryKey: ['dataset', datasetId] })
      toast({ title: 'Dataset updated', description: 'Release metadata saved.' })
    },
  })

  const handleSave = () => {
    if (!formState.title.trim()) {
      toast({ title: 'Missing fields', description: 'Dataset title is required.' })
      return
    }
    mutation.mutate()
  }

  if (!allowEdit) {
    return (
      <EmptyState
        title="Access limited"
        description="Your role only allows read-only access."
        actionLabel="Back to dataset"
        onAction={() => navigate(datasetId ? `/datasets/${datasetId}` : '/datasets')}
      />
    )
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
        title="Dataset unavailable"
        description={(error as Error)?.message ?? 'Dataset record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['dataset', datasetId] })}
      />
    )
  }

  const statusVariant = data.status === 'published' ? 'success' : data.status === 'restricted' ? 'warning' : 'outline'

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/datasets/${data.id}`}>
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Edit dataset</p>
            <h2 className="font-display text-2xl">{data.title}</h2>
          </div>
        </div>
        <Badge variant={statusVariant}>{data.status}</Badge>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Release controls</CardTitle>
          <CardDescription>Update dataset status and identifiers.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-5">
          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <Label>Title</Label>
              <Input
                value={formState.title}
                onChange={(event) => setFormState((prev) => ({ ...prev, title: event.target.value }))}
              />
            </div>
            <div>
              <Label>Format</Label>
              <select
                className="w-full rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-700"
                value={formState.format}
                onChange={(event) =>
                  setFormState((prev) => ({ ...prev, format: event.target.value as (typeof formatOptions)[number] }))
                }
              >
                {formatOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label>Status</Label>
              <select
                className="w-full rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-700"
                value={formState.status}
                onChange={(event) => setFormState((prev) => ({ ...prev, status: event.target.value as Dataset['status'] }))}
              >
                {statusOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label>DOI</Label>
              <Input
                value={formState.doi}
                onChange={(event) => setFormState((prev) => ({ ...prev, doi: event.target.value }))}
                placeholder="10.5281/zenodo.0000000"
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button onClick={handleSave} disabled={mutation.isPending}>
              Save updates
            </Button>
            <Button variant="outline" asChild>
              <Link to={`/datasets/${data.id}`}>Cancel</Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
