import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { getSample, updateSample } from './sample.api.ts'
import type { SampleStatus } from '@/domain/resources.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'

const statusOptions: SampleStatus[] = ['collected', 'processing', 'stored', 'consumed']

export function SampleEditPage() {
  const { sampleId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()
  const allowEdit = canEdit(role)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['sample', sampleId],
    queryFn: () => getSample(sampleId ?? ''),
    enabled: Boolean(sampleId) && allowEdit,
  })

  const [status, setStatus] = useState<SampleStatus>('collected')

  const lastInitializedId = useRef<string | null>(null)

  useEffect(() => {
    if (data && data.id !== lastInitializedId.current) {
      lastInitializedId.current = data.id
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setStatus(data.status)
    }
  }, [data])

  const mutation = useMutation({
    mutationFn: () => updateSample(sampleId ?? '', { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['samples'] })
      queryClient.invalidateQueries({ queryKey: ['sample', sampleId] })
      toast({ title: 'Sample updated', description: 'Chain-of-custody updated in registry.' })
    },
  })

  if (!allowEdit) {
    return (
      <EmptyState
        title="Access limited"
        description="Your role only allows read-only access."
        actionLabel="Back to sample"
        onAction={() => navigate(sampleId ? `/samples/${sampleId}` : '/samples')}
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
        title="Sample unavailable"
        description={(error as Error)?.message ?? 'Sample record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['sample', sampleId] })}
      />
    )
  }

  const statusVariant = data.status === 'stored' ? 'success' : data.status === 'processing' ? 'warning' : 'outline'
  const qcVariant = data.qcStatus === 'pass' ? 'success' : data.qcStatus === 'review' ? 'warning' : 'outline'

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/samples/${data.id}`}>
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Edit sample</p>
            <h2 className="font-display text-2xl">{data.label}</h2>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Badge variant={statusVariant}>{data.status}</Badge>
          <Badge variant={qcVariant}>QC {data.qcStatus}</Badge>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Status controls</CardTitle>
          <CardDescription>Update specimen lifecycle.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div>
            <Label>Status</Label>
            <select
              className="w-full rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-700"
              value={status}
              onChange={(event) => setStatus(event.target.value as SampleStatus)}
            >
              {statusOptions.map((value) => (
                <option key={value} value={value}>
                  {value}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <Button onClick={() => mutation.mutate()} disabled={mutation.isPending}>
              Sync status
            </Button>
            <Button variant="outline" asChild>
              <Link to={`/samples/${data.id}`}>Cancel</Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
