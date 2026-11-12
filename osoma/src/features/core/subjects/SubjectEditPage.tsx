import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { getSubject, updateSubject } from './subject.api.ts'
import type { Subject } from '@/domain/resources.ts'
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

const speciesOptions = ['human', 'mouse', 'rat', 'arabidopsis', 'environment'] as const
const consentOptions: Subject['consent'][] = ['granted', 'restricted', 'withdrawn']
const statusOptions: Subject['status'][] = ['active', 'completed', 'archived']

export function SubjectEditPage() {
  const { subjectId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()
  const allowEdit = canEdit(role)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['subject', subjectId],
    queryFn: () => getSubject(subjectId ?? ''),
    enabled: Boolean(subjectId) && allowEdit,
  })

  const [formState, setFormState] = useState<{
    label: string
    species: (typeof speciesOptions)[number]
    cohort: string
    consent: Subject['consent']
    status: Subject['status']
  }>({
    label: '',
    species: speciesOptions[0],
    cohort: '',
    consent: consentOptions[0],
    status: statusOptions[0],
  })

  const lastInitializedId = useRef<string | null>(null)

  useEffect(() => {
    if (data && data.id !== lastInitializedId.current) {
      lastInitializedId.current = data.id
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setFormState({
        label: data.label,
        species: data.species as (typeof speciesOptions)[number],
        cohort: data.cohort,
        consent: data.consent,
        status: data.status,
      })
    }
  }, [data])

  const mutation = useMutation({
    mutationFn: () =>
      updateSubject(subjectId ?? '', {
        label: formState.label,
        species: formState.species,
        cohort: formState.cohort,
        consent: formState.consent,
        status: formState.status,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['subjects'] })
      queryClient.invalidateQueries({ queryKey: ['subject', subjectId] })
      toast({ title: 'Subject updated', description: 'Registry entry refreshed.' })
    },
  })

  const handleSave = () => {
    if (!formState.label.trim() || !formState.cohort.trim()) {
      toast({ title: 'Missing fields', description: 'Label and cohort are required.' })
      return
    }
    mutation.mutate()
  }

  if (!allowEdit) {
    return (
      <EmptyState
        title="Access limited"
        description="Your role only allows read-only access."
        actionLabel="Back to subject"
        onAction={() => navigate(subjectId ? `/subjects/${subjectId}` : '/subjects')}
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
        title="Subject unavailable"
        description={(error as Error)?.message ?? 'Subject record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['subject', subjectId] })}
      />
    )
  }

  const consentVariant = data.consent === 'granted' ? 'success' : data.consent === 'restricted' ? 'warning' : 'outline'

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/subjects/${data.id}`}>
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Edit subject</p>
            <h2 className="font-display text-2xl">{data.label}</h2>
          </div>
        </div>
        <Badge variant={consentVariant}>{data.consent}</Badge>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Registry controls</CardTitle>
          <CardDescription>Update consent, cohort, and status.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-5">
          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <Label>Label</Label>
              <Input
                value={formState.label}
                onChange={(event) => setFormState((prev) => ({ ...prev, label: event.target.value }))}
              />
            </div>
            <div>
              <Label>Cohort</Label>
              <Input
                value={formState.cohort}
                onChange={(event) => setFormState((prev) => ({ ...prev, cohort: event.target.value }))}
              />
            </div>
            <div>
              <Label>Species</Label>
              <select
                className="w-full rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-700"
                value={formState.species}
                onChange={(event) =>
                  setFormState((prev) => ({ ...prev, species: event.target.value as (typeof speciesOptions)[number] }))
                }
              >
                {speciesOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label>Consent</Label>
              <select
                className="w-full rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-700"
                value={formState.consent}
                onChange={(event) => setFormState((prev) => ({ ...prev, consent: event.target.value as Subject['consent'] }))}
              >
                {consentOptions.map((option) => (
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
                onChange={(event) => setFormState((prev) => ({ ...prev, status: event.target.value as Subject['status'] }))}
              >
                {statusOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button onClick={handleSave} disabled={mutation.isPending}>
              Save updates
            </Button>
            <Button variant="outline" asChild>
              <Link to={`/subjects/${data.id}`}>Cancel</Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
