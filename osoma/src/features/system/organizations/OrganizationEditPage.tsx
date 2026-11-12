import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { getOrganization, updateOrganization } from './organization.api.ts'
import type { Organization } from '@/domain/resources.ts'
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

const typeOptions: Organization['type'][] = ['university', 'biotech', 'hospital', 'consortium']

export function OrganizationEditPage() {
  const { organizationId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()
  const allowEdit = canEdit(role)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['organization', organizationId],
    queryFn: () => getOrganization(organizationId ?? ''),
    enabled: Boolean(organizationId) && allowEdit,
  })

  const [formState, setFormState] = useState({
    name: '',
    type: typeOptions[0],
    location: '',
    labs: 0,
    activeInvestigations: 0,
  })

  const lastInitializedId = useRef<string | null>(null)

  useEffect(() => {
    if (data && data.id !== lastInitializedId.current) {
      lastInitializedId.current = data.id
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setFormState({
        name: data.name,
        type: data.type,
        location: data.location,
        labs: data.labs,
        activeInvestigations: data.activeInvestigations,
      })
    }
  }, [data])

  const mutation = useMutation({
    mutationFn: () =>
      updateOrganization(organizationId ?? '', {
        name: formState.name,
        type: formState.type,
        location: formState.location,
        labs: formState.labs,
        activeInvestigations: formState.activeInvestigations,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['organizations'] })
      queryClient.invalidateQueries({ queryKey: ['organization', organizationId] })
      toast({ title: 'Organization updated', description: 'Partnership profile refreshed.' })
    },
  })

  const handleSave = () => {
    if (!formState.name.trim() || !formState.location.trim()) {
      toast({ title: 'Missing fields', description: 'Organization name and location are required.' })
      return
    }
    mutation.mutate()
  }

  if (!allowEdit) {
    return (
      <EmptyState
        title="Access limited"
        description="Your role only allows read-only access."
        actionLabel="Back to organization"
        onAction={() => navigate(organizationId ? `/organizations/${organizationId}` : '/organizations')}
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
        title="Organization unavailable"
        description={(error as Error)?.message ?? 'Organization record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['organization', organizationId] })}
      />
    )
  }

  const typeVariant = data.type === 'consortium' ? 'warning' : data.type === 'biotech' ? 'default' : 'outline'

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/organizations/${data.id}`}>
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Edit organization</p>
            <h2 className="font-display text-2xl">{data.name}</h2>
          </div>
        </div>
        <Badge variant={typeVariant}>{data.type}</Badge>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Organization controls</CardTitle>
          <CardDescription>Update type, footprint, and active labs.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-5">
          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <Label>Name</Label>
              <Input
                value={formState.name}
                onChange={(event) => setFormState((prev) => ({ ...prev, name: event.target.value }))}
              />
            </div>
            <div>
              <Label>Location</Label>
              <Input
                value={formState.location}
                onChange={(event) => setFormState((prev) => ({ ...prev, location: event.target.value }))}
              />
            </div>
            <div>
              <Label>Type</Label>
              <select
                className="w-full rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-700"
                value={formState.type}
                onChange={(event) => setFormState((prev) => ({ ...prev, type: event.target.value as Organization['type'] }))}
              >
                {typeOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label>Labs</Label>
              <Input
                type="number"
                min={0}
                value={formState.labs}
                onChange={(event) => setFormState((prev) => ({ ...prev, labs: Number(event.target.value) }))}
              />
            </div>
            <div>
              <Label>Active investigations</Label>
              <Input
                type="number"
                min={0}
                value={formState.activeInvestigations}
                onChange={(event) => setFormState((prev) => ({ ...prev, activeInvestigations: Number(event.target.value) }))}
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button onClick={handleSave} disabled={mutation.isPending}>
              Save updates
            </Button>
            <Button variant="outline" asChild>
              <Link to={`/organizations/${data.id}`}>Cancel</Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
