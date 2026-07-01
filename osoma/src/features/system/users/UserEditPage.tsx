import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { getUser, updateUser } from './user.api.ts'
import type { User } from '@/domain/resources.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { useRole } from '@/app/role-context.tsx'
import { useAuth } from '@/app/auth-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import { accessAreaOptions, getDefaultAccessAreas, normalizeAccessAreas, summarizeAccessAreas, type AccessArea } from '@/lib/user-access.ts'

const accessOptions: User['accessLevel'][] = ['viewer', 'editor', 'admin']
const statusOptions: User['status'][] = ['active', 'invited', 'suspended']

export function UserEditPage() {
  const { userId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { session } = useAuth()
  const { toast } = useToast()
  const allowEdit = canEdit(role)
  const isSuperAdmin = Boolean(session?.user?.isSuperAdmin)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['user', userId],
    queryFn: () => getUser(userId ?? ''),
    enabled: Boolean(userId) && allowEdit,
  })

  const [formState, setFormState] = useState({
    name: '',
    email: '',
    role: '',
    lab: '',
    accessLevel: accessOptions[0],
    status: statusOptions[0],
    accessAreas: getDefaultAccessAreas(accessOptions[0]) as AccessArea[],
  })

  const lastInitializedId = useRef<string | null>(null)

  useEffect(() => {
    if (data && data.id !== lastInitializedId.current) {
      lastInitializedId.current = data.id
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setFormState({
        name: data.name,
        email: data.email ?? '',
        role: data.role,
        lab: data.lab,
        accessLevel: data.accessLevel,
        status: data.status,
        accessAreas: normalizeAccessAreas(data.accessAreas, data.accessLevel),
      })
    }
  }, [data])

  const mutation = useMutation({
    mutationFn: () =>
      updateUser(userId ?? '', {
        name: formState.name,
        email: formState.email,
        role: formState.role,
        lab: formState.lab,
        accessLevel: formState.accessLevel,
        status: formState.status,
        accessAreas: formState.accessAreas,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['user', userId] })
      toast({ title: 'User updated', description: 'Access profile refreshed.' })
    },
  })

  const handleSave = () => {
    if (!formState.name.trim() || !formState.email.trim() || !formState.role.trim()) {
      toast({ title: 'Missing fields', description: 'Name, email, and role are required.' })
      return
    }
    mutation.mutate()
  }

  const toggleAccessArea = (area: AccessArea) => {
    setFormState((previous) => {
      const nextAreas = previous.accessAreas.includes(area)
        ? previous.accessAreas.filter((value) => value !== area)
        : [...previous.accessAreas, area]

      return {
        ...previous,
        accessAreas: normalizeAccessAreas(nextAreas, previous.accessLevel),
      }
    })
  }

  if (!allowEdit || !isSuperAdmin) {
    return (
      <EmptyState
        title="Access limited"
        description="Only super admins can change user profiles and workspace permissions."
        actionLabel="Back to user"
        onAction={() => navigate(userId ? `/users/${userId}` : '/users')}
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
        title="User unavailable"
        description={(error as Error)?.message ?? 'User record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['user', userId] })}
      />
    )
  }

  const statusVariant = data.status === 'active' ? 'success' : data.status === 'invited' ? 'warning' : 'outline'

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/users/${data.id}`}>
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Edit user</p>
            <h2 className="font-display text-2xl">{data.name}</h2>
          </div>
        </div>
        <Badge variant={statusVariant}>{data.status}</Badge>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Access controls</CardTitle>
          <CardDescription>Update role, lab, and permissions.</CardDescription>
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
              <Label>Email</Label>
              <Input
                type="email"
                value={formState.email}
                onChange={(event) => setFormState((prev) => ({ ...prev, email: event.target.value }))}
              />
            </div>
            <div>
              <Label>Role</Label>
              <Input
                value={formState.role}
                onChange={(event) => setFormState((prev) => ({ ...prev, role: event.target.value }))}
              />
            </div>
            <div>
              <Label>Lab</Label>
              <Input
                value={formState.lab}
                onChange={(event) => setFormState((prev) => ({ ...prev, lab: event.target.value }))}
              />
            </div>
            <div>
              <Label>Access level</Label>
              <select
                className="w-full rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-700"
                value={formState.accessLevel}
                onChange={(event) => {
                  const nextAccessLevel = event.target.value as User['accessLevel']
                  setFormState((prev) => ({
                    ...prev,
                    accessLevel: nextAccessLevel,
                    accessAreas: getDefaultAccessAreas(nextAccessLevel),
                  }))
                }}
              >
                {accessOptions.map((option) => (
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
                onChange={(event) => setFormState((prev) => ({ ...prev, status: event.target.value as User['status'] }))}
              >
                {statusOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="rounded-2xl border border-line bg-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-slate-900">Workspace access</p>
                <p className="text-sm text-slate-500">Choose exactly which areas this user can enter and manage.</p>
              </div>
              <Badge variant="outline">{summarizeAccessAreas(formState.accessAreas, formState.accessLevel)}</Badge>
            </div>
            <div className="mt-4 grid gap-3 md:grid-cols-2">
              {accessAreaOptions.map((option) => {
                const checked = formState.accessAreas.includes(option.value)
                return (
                  <label key={option.value} className="flex cursor-pointer items-start gap-3 rounded-2xl border border-line/80 bg-white p-4 text-sm">
                    <input
                      type="checkbox"
                      className="mt-1 h-4 w-4 rounded border-line"
                      checked={checked}
                      onChange={() => toggleAccessArea(option.value)}
                    />
                    <div>
                      <p className="font-semibold text-slate-900">{option.label}</p>
                      <p className="text-slate-500">{option.description}</p>
                    </div>
                  </label>
                )
              })}
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button onClick={handleSave} disabled={mutation.isPending}>
              Save updates
            </Button>
            <Button variant="outline" asChild>
              <Link to={`/users/${data.id}`}>Cancel</Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
