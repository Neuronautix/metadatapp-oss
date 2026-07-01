import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { getAssay, updateAssay } from './assay.api.ts'
import type { Assay } from '@/domain/resources.ts'
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

const reviewOptions: Assay['reviewStatus'][] = ['current', 'review-due', 'retired']

export function AssayEditPage() {
  const { assayId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()
  const allowEdit = canEdit(role)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['assay', assayId],
    queryFn: () => getAssay(assayId ?? ''),
    enabled: Boolean(assayId) && allowEdit,
  })

  const [formState, setFormState] = useState({
    name: '',
    version: '',
    method: '',
    owner: '',
    reviewStatus: reviewOptions[0],
    lastReviewedAt: '',
  })

  const lastInitializedId = useRef<string | null>(null)

  useEffect(() => {
    if (data && data.id !== lastInitializedId.current) {
      lastInitializedId.current = data.id
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setFormState({
        name: data.name,
        version: data.version,
        method: data.method,
        owner: data.owner,
        reviewStatus: data.reviewStatus,
        lastReviewedAt: data.lastReviewedAt.slice(0, 10),
      })
    }
  }, [data])

  const mutation = useMutation({
    mutationFn: () =>
      updateAssay(assayId ?? '', {
        name: formState.name,
        version: formState.version,
        method: formState.method,
        owner: formState.owner,
        reviewStatus: formState.reviewStatus,
        lastReviewedAt: formState.lastReviewedAt,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['assays'] })
      queryClient.invalidateQueries({ queryKey: ['assay', assayId] })
      toast({ title: 'Assay updated', description: 'Review metadata saved.' })
    },
  })

  const handleSave = () => {
    if (!formState.name.trim() || !formState.method.trim()) {
      toast({ title: 'Missing fields', description: 'Assay name and method are required.' })
      return
    }
    mutation.mutate()
  }

  if (!allowEdit) {
    return (
      <EmptyState
        title="Access limited"
        description="Your role only allows read-only access."
        actionLabel="Back to assay"
        onAction={() => navigate(assayId ? `/assays/${assayId}` : '/assays')}
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
        title="Assay unavailable"
        description={(error as Error)?.message ?? 'Assay record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['assay', assayId] })}
      />
    )
  }

  const reviewVariant = data.reviewStatus === 'current' ? 'success' : data.reviewStatus === 'review-due' ? 'warning' : 'outline'

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/assays/${data.id}`}>
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Edit assay</p>
            <h2 className="font-display text-2xl">{data.name}</h2>
          </div>
        </div>
        <Badge variant={reviewVariant}>{data.reviewStatus}</Badge>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Assay controls</CardTitle>
          <CardDescription>Update review cadence and method metadata.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-5">
          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <Label>Assay name</Label>
              <Input
                value={formState.name}
                onChange={(event) => setFormState((prev) => ({ ...prev, name: event.target.value }))}
              />
            </div>
            <div>
              <Label>Version</Label>
              <Input
                value={formState.version}
                onChange={(event) => setFormState((prev) => ({ ...prev, version: event.target.value }))}
              />
            </div>
            <div>
              <Label>Method</Label>
              <Input
                value={formState.method}
                onChange={(event) => setFormState((prev) => ({ ...prev, method: event.target.value }))}
              />
            </div>
            <div>
              <Label>Owner</Label>
              <Input
                value={formState.owner}
                onChange={(event) => setFormState((prev) => ({ ...prev, owner: event.target.value }))}
              />
            </div>
            <div>
              <Label>Review status</Label>
              <select
                className="w-full rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-700"
                value={formState.reviewStatus}
                onChange={(event) =>
                  setFormState((prev) => ({ ...prev, reviewStatus: event.target.value as Assay['reviewStatus'] }))
                }
              >
                {reviewOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label>Last reviewed</Label>
              <Input
                type="date"
                value={formState.lastReviewedAt}
                onChange={(event) => setFormState((prev) => ({ ...prev, lastReviewedAt: event.target.value }))}
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button onClick={handleSave} disabled={mutation.isPending}>
              Save updates
            </Button>
            <Button variant="outline" asChild>
              <Link to={`/assays/${data.id}`}>Cancel</Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
