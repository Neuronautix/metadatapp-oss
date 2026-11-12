import { useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Search } from 'lucide-react'
import { assignAnimalsToInvestigation, getInvestigation } from './investigation.api.ts'
import { getTicklabAnimals } from '@/features/core/animals/animal.api.ts'
import { getCageColorClass, statusBadgeMap } from '@/features/core/animals/animal.ui.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { formatDateTime } from '@/lib/format.ts'

export function InvestigationAssignAnimalsPage() {
  const { investigationId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const [search, setSearch] = useState('')
  const [selectedIds, setSelectedIds] = useState<string[]>([])

  const investigationQuery = useQuery({
    queryKey: ['investigation', investigationId],
    queryFn: () => getInvestigation(investigationId ?? ''),
    enabled: Boolean(investigationId),
  })

  const animalsQuery = useQuery({
    queryKey: ['ticklab-animals'],
    queryFn: getTicklabAnimals,
  })

  const mutation = useMutation({
    mutationFn: () => assignAnimalsToInvestigation(investigationId ?? '', selectedIds),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['investigations'] })
      queryClient.invalidateQueries({ queryKey: ['investigation', investigationId] })
      queryClient.invalidateQueries({ queryKey: ['investigation-animals', investigationId] })
      toast({
        title: 'Animals assigned',
        description: `Added ${selectedIds.length} animals to the study.`,
      })
      navigate(`/investigations/${investigationId}`)
    },
  })

  const availableAnimals = animalsQuery.data?.data ?? []

  const filteredAnimals = useMemo(() => {
    const list = availableAnimals
    if (!search.trim()) {
      return list
    }
    const term = search.toLowerCase()
    return list.filter((animal) =>
      [animal.tagId, animal.nickname, animal.strain, animal.cohort]
        .join(' ')
        .toLowerCase()
        .includes(term)
    )
  }, [availableAnimals, search])

  const toggleAnimal = (animalId: string) => {
    setSelectedIds((prev) =>
      prev.includes(animalId) ? prev.filter((id) => id !== animalId) : [...prev, animalId]
    )
  }

  if (investigationQuery.isError) {
    return (
      <EmptyState
        title="Investigation unavailable"
        description={investigationQuery.error?.message ?? 'Unable to load investigation.'}
        actionLabel="Back to investigations"
        onAction={() => navigate('/investigations')}
      />
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to={investigationId ? `/investigations/${investigationId}` : '/investigations'}>
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Tick@Lab roster</p>
            <h2 className="font-display text-2xl">Assign Animals</h2>
            {investigationQuery.data ? (
              <p className="text-sm text-slate-500">Investigation: {investigationQuery.data.name}</p>
            ) : null}
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="outline">{selectedIds.length} selected</Badge>
          <Button disabled={selectedIds.length === 0 || mutation.isPending} onClick={() => mutation.mutate()}>
            Attach to investigation
          </Button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <Card>
          <CardHeader>
            <CardTitle>Available animals</CardTitle>
            <CardDescription>Tick@Lab inventory ready for assignment.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" />
              <Input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search by tag, nickname, strain, cohort"
                className="pl-10"
              />
            </div>

            {animalsQuery.isLoading ? (
              <div className="space-y-3">
                {Array.from({ length: 5 }).map((_, index) => (
                  <Skeleton key={index} className="h-16 w-full" />
                ))}
              </div>
            ) : filteredAnimals.length === 0 ? (
              <EmptyState
                title="No animals available"
                description="Tick@Lab has no unassigned animals right now."
              />
            ) : (
              <div className="space-y-3">
                {filteredAnimals.map((animal) => {
                  const isSelected = selectedIds.includes(animal.id)
                  const status = statusBadgeMap[animal.badgeStatus ?? 'ok']
                  return (
                    <label
                      key={animal.id}
                      className={`flex items-center justify-between rounded-2xl border p-4 text-sm transition ${
                        isSelected ? 'border-slate-900 bg-slate-900/5' : 'border-line bg-white'
                      }`}
                    >
                      <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <Link
                            to={`/investigations/${investigationId}/animals/${animal.id}`}
                            className="font-semibold text-slate-800"
                            onClick={(event) => event.stopPropagation()}
                          >
                            {animal.nickname}
                          </Link>
                          <Badge variant="outline">{animal.tagId}</Badge>
                          <Badge variant="outline">{animal.sex}</Badge>
                          <Badge variant={status.variant}>{status.label}</Badge>
                        </div>
                        <p className="text-xs text-slate-500">
                          <span className="inline-flex items-center gap-2">
                            <span className={`h-2 w-2 rounded-full ${getCageColorClass(animal.cageColor)}`} />
                            {animal.cage}
                          </span>
                          {' • '}
                          {animal.strain} • {animal.ageWeeks} weeks • {animal.cohort}
                        </p>
                        <p className="text-xs text-slate-400">
                          Last weight {animal.lastWeight.toFixed(1)} g • {formatDateTime(animal.lastWeighedAt)}
                        </p>
                      </div>
                      <input
                        type="checkbox"
                        className="h-4 w-4 accent-slate-900"
                        checked={isSelected}
                        onChange={() => toggleAnimal(animal.id)}
                      />
                    </label>
                  )
                })}
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Assignment summary</CardTitle>
            <CardDescription>Review before attaching to investigation.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-slate-600">
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Selected animals</p>
              <p className="font-semibold text-slate-700">{selectedIds.length} pending</p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Cohorts</p>
              <p className="font-semibold text-slate-700">
                {Array.from(
                  new Set(
                    availableAnimals
                      .filter((animal) => selectedIds.includes(animal.id))
                      .map((animal) => animal.cohort)
                  )
                ).join(', ') || 'None yet'}
              </p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Next step</p>
              <p>Attach animals, then review weights and treatments on the investigation dashboard.</p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
