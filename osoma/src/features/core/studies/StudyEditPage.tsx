import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { getStudy, updateStudy } from './study.api.ts'
import { getInvestigations } from '@/features/core/investigations/investigation.api.ts'
import type { StudyStatus } from '@/domain/resources.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'

const statusOptions: StudyStatus[] = ['draft', 'running', 'paused', 'completed', 'failed']

export function StudyEditPage() {
  const { studyId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()
  const allowEdit = canEdit(role)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['study', studyId],
    queryFn: () => getStudy(studyId ?? ''),
    enabled: Boolean(studyId) && allowEdit,
  })

  const { data: investigationsData } = useQuery({
    queryKey: ['investigations'],
    queryFn: getInvestigations,
    staleTime: 60_000,
  })

  const [status, setStatus] = useState<StudyStatus>('draft')
  const [investigationId, setInvestigationId] = useState('')

  useEffect(() => {
    if (data) {
      setStatus(data.status)
      setInvestigationId(data.investigationId ?? '')
    }
  }, [data])

  const mutation = useMutation({
    mutationFn: () => updateStudy(studyId ?? '', { status, investigationId: investigationId || undefined }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['studies'] })
      queryClient.invalidateQueries({ queryKey: ['study', studyId] })
      toast({ title: 'Study updated', description: 'Investigation and status synced.' })
    },
  })

  if (!allowEdit) {
    return (
      <EmptyState
        title="Access limited"
        description="Your role only allows read-only access."
        actionLabel="Back to study"
        onAction={() => navigate(studyId ? `/studies/${studyId}` : '/studies')}
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
        title="Study unavailable"
        description={(error as Error)?.message ?? 'Study record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['study', studyId] })}
      />
    )
  }

  const statusVariant = data.status === 'running' ? 'success' : data.status === 'paused' ? 'warning' : 'outline'
  const qcVariant = data.qcStatus === 'pass' ? 'success' : data.qcStatus === 'review' ? 'warning' : 'outline'

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/studies/${data.id}`}>
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Edit study</p>
            <h2 className="font-display text-2xl">{data.name}</h2>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Badge variant={statusVariant}>{data.status}</Badge>
          <Badge variant={qcVariant}>QC {data.qcStatus}</Badge>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Study settings</CardTitle>
          <CardDescription>Link to an investigation and align run state to operations.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div>
            <Label>Investigation</Label>
            <select
              className="w-full rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-700"
              value={investigationId}
              onChange={(event) => setInvestigationId(event.target.value)}
            >
              <option value="">— Unassigned —</option>
              {investigationsData?.data.map((inv) => (
                <option key={inv.id} value={inv.id}>
                  {inv.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <Label>Status</Label>
            <select
              className="w-full rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-700"
              value={status}
              onChange={(event) => setStatus(event.target.value as StudyStatus)}
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
              Save changes
            </Button>
            <Button variant="outline" asChild>
              <Link to={`/studies/${data.id}`}>Cancel</Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
