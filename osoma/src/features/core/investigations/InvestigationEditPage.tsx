import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { getInvestigation, updateInvestigation } from './investigation.api.ts'
import { operatorOptions } from './investigation.constants.ts'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import type { InvestigationOperator } from './investigation.types.ts'

export function InvestigationEditPage() {
  const { investigationId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()
  const allowEdit = canEdit(role)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['investigation', investigationId],
    queryFn: () => getInvestigation(investigationId ?? ''),
    enabled: Boolean(investigationId) && allowEdit,
  })

  const [formState, setFormState] = useState({
    name: '',
    description: '',
    startDate: '',
    endDate: '',
    assignedOperatorIds: [] as string[],
  })

  const lastInitializedId = useRef<string | null>(null)

  useEffect(() => {
    if (data && data.id !== lastInitializedId.current) {
      lastInitializedId.current = data.id
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setFormState({
        name: data.name,
        description: data.description ?? '',
        startDate: data.startAt ? data.startAt.slice(0, 10) : '',
        endDate: data.endAt ? data.endAt.slice(0, 10) : '',
        assignedOperatorIds: data.assignedOperators.map((operator: InvestigationOperator) => operator.id),
      })
    }
  }, [data])

  const mutation = useMutation({
    mutationFn: () =>
      updateInvestigation(investigationId ?? '', {
        name: formState.name.trim(),
        description: formState.description.trim(),
        species: 'Mouse',
        startDate: formState.startDate,
        endDate: formState.endDate || undefined,
        assignedOperatorIds: formState.assignedOperatorIds,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['investigations'] })
      queryClient.invalidateQueries({ queryKey: ['investigation', investigationId] })
      toast({ title: 'Investigation updated', description: 'Mouse study details saved.' })
    },
  })

  const toggleOperator = (operatorId: string) => {
    setFormState((prev) => {
      const selected = prev.assignedOperatorIds.includes(operatorId)
      return {
        ...prev,
        assignedOperatorIds: selected
          ? prev.assignedOperatorIds.filter((id) => id !== operatorId)
          : [...prev.assignedOperatorIds, operatorId],
      }
    })
  }

  const handleSave = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (!formState.name.trim()) {
      toast({ title: 'Investigation name required', description: 'Add a investigation name to continue.' })
      return
    }

    if (!formState.description.trim()) {
      toast({ title: 'Description required', description: 'Add a short investigation description.' })
      return
    }

    if (!formState.startDate) {
      toast({ title: 'Start date required', description: 'Pick a investigation start date.' })
      return
    }

    if (formState.assignedOperatorIds.length === 0) {
      toast({ title: 'Assign operators', description: 'Select at least one operator.' })
      return
    }

    mutation.mutate()
  }

  if (!allowEdit) {
    return (
      <EmptyState
        title="Access limited"
        description="Your role only allows read-only access."
        actionLabel="Back to investigation"
        onAction={() => navigate(investigationId ? `/investigations/${investigationId}` : '/investigations')}
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
        title="Investigation unavailable"
        description={(error as Error)?.message ?? 'Investigation record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['investigation', investigationId] })}
      />
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/investigations/${data.id}`}>
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Edit investigation</p>
            <h2 className="font-display text-2xl">{data.name}</h2>
          </div>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Investigation settings</CardTitle>
          <CardDescription>Update the mouse investigation scope and operators.</CardDescription>
        </CardHeader>
        <CardContent>
          <form className="space-y-6" onSubmit={handleSave}>
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <Label htmlFor="investigation-name">Investigation name</Label>
                <Input
                  id="investigation-name"
                  value={formState.name}
                  onChange={(event) => setFormState((prev) => ({ ...prev, name: event.target.value }))}
                />
              </div>
              <div>
                <Label htmlFor="investigation-species">Species</Label>
                <Input id="investigation-species" value="Mouse" disabled />
              </div>
              <div>
                <Label htmlFor="investigation-start">Start date</Label>
                <Input
                  id="investigation-start"
                  type="date"
                  value={formState.startDate}
                  onChange={(event) => setFormState((prev) => ({ ...prev, startDate: event.target.value }))}
                />
              </div>
              <div>
                <Label htmlFor="investigation-end">End date</Label>
                <Input
                  id="investigation-end"
                  type="date"
                  value={formState.endDate}
                  onChange={(event) => setFormState((prev) => ({ ...prev, endDate: event.target.value }))}
                />
              </div>
            </div>

            <div>
              <Label htmlFor="investigation-description">Description</Label>
              <Textarea
                id="investigation-description"
                value={formState.description}
                onChange={(event) => setFormState((prev) => ({ ...prev, description: event.target.value }))}
              />
            </div>

            <div className="space-y-3">
              <Label>Assigned operators</Label>
              <div className="grid gap-2 md:grid-cols-2">
                {operatorOptions.map((operator) => (
                  <label
                    key={operator.id}
                    className="flex items-center justify-between rounded-xl border border-line bg-white px-3 py-2 text-sm"
                  >
                    <span>
                      <span className="font-medium text-slate-700">{operator.name}</span>
                      <span className="block text-xs text-slate-400">{operator.role}</span>
                    </span>
                    <input
                      type="checkbox"
                      className="h-4 w-4 accent-slate-900"
                      checked={formState.assignedOperatorIds.includes(operator.id)}
                      onChange={() => toggleOperator(operator.id)}
                    />
                  </label>
                ))}
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <Button type="submit" disabled={mutation.isPending}>
                Save updates
              </Button>
              <Button variant="outline" asChild>
                <Link to={`/investigations/${data.id}`}>Cancel</Link>
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
