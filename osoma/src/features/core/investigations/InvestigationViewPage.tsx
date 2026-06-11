import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Download, Keyboard, PawPrint, Pencil, Star } from 'lucide-react'
import {
  exportInvestigationAnimalsCsv,
  exportInvestigationRoCrate,
  getKeyboardShortcuts,
  getInvestigation,
  getInvestigationDashboard,
  getInvestigationExportStatus,
  updateInvestigationTags,
} from './investigation.api.ts'
import { InvestigationGroupWeightChart } from './InvestigationGroupWeightChart.tsx'
import { getInvestigationAnimals, updateAnimalFavorite } from '@/features/core/animals/animal.api.ts'
import { MiniSparkline } from '@/features/core/animals/MiniSparkline.tsx'
import { getCageColorClass, statusBadgeMap } from '@/features/core/animals/animal.ui.ts'
import { Badge } from '@/components/ui/badge.tsx'
import type { InvestigationOperator } from './investigation.types.ts'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { StatCard } from '@/components/StatCard.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { formatDate, formatDateTime, formatNumber } from '@/lib/format.ts'
import { Feature } from '@/feature-flags/Feature.tsx'
import { useTimelineEvents } from '@/features/system/audit/useTimelineEvents.ts'
import { AssayTimeline } from '@/features/system/timeline/AssayTimeline.tsx'
import { FairScorePanel } from '@/features/core/studies/components/FairScorePanel.tsx'
import { downloadBlob } from '@/lib/download.ts'
import { computeFairCriteria, fairVariantFromScore } from '@/lib/fair.ts'

export function InvestigationViewPage() {
  const { investigationId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const [tagInput, setTagInput] = useState('')
  const [focusedAnimalId, setFocusedAnimalId] = useState<string | null>(null)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['investigation', investigationId],
    queryFn: () => getInvestigation(investigationId ?? ''),
    enabled: Boolean(investigationId),
  })

  const animalsQuery = useQuery({
    queryKey: ['investigation-animals', investigationId],
    queryFn: () => getInvestigationAnimals(investigationId ?? ''),
    enabled: Boolean(investigationId),
  })

  const dashboardQuery = useQuery({
    queryKey: ['investigation-dashboard', investigationId],
    queryFn: () => getInvestigationDashboard(investigationId ?? ''),
    enabled: Boolean(investigationId),
  })

  const shortcutsQuery = useQuery({
    queryKey: ['keyboard-shortcuts'],
    queryFn: getKeyboardShortcuts,
  })

  const exportStatusQuery = useQuery({
    queryKey: ['investigation-export', investigationId],
    queryFn: () => getInvestigationExportStatus(investigationId ?? ''),
    enabled: Boolean(investigationId),
  })

  const hasWorkspaceDataError =
    animalsQuery.isError ||
    dashboardQuery.isError ||
    shortcutsQuery.isError ||
    exportStatusQuery.isError

  const tagMutation = useMutation({
    mutationFn: (tags: string[]) => updateInvestigationTags(investigationId ?? '', tags),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['investigation', investigationId] })
      queryClient.invalidateQueries({ queryKey: ['investigations'] })
      toast({ title: 'Tag added', description: 'Investigation tags updated.' })
    },
  })

  const favoriteMutation = useMutation({
    mutationFn: ({ animalId, isFavorite }: { animalId: string; isFavorite: boolean }) =>
      updateAnimalFavorite(animalId, isFavorite),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['investigation-animals', investigationId] })
    },
  })

  const exportMutation = useMutation({
    mutationFn: () => exportInvestigationAnimalsCsv(investigationId ?? ''),
    onSuccess: (payload) => {
      const blob = new Blob([payload.csv], { type: 'text/csv' })
      downloadBlob(blob, `${investigationId ?? 'investigation'}-animals.csv`)
      toast({
        title: 'CSV exported',
        description: `${payload.rowCount} animals exported at ${formatDateTime(payload.exportedAt)}.`,
      })
      queryClient.invalidateQueries({ queryKey: ['investigation-export', investigationId] })
    },
  })

  const roCrateMutation = useMutation({
    mutationFn: () => exportInvestigationRoCrate(investigationId ?? ''),
    onSuccess: (blob) => {
      downloadBlob(blob, `${investigationId ?? 'investigation'}-ro-crate.zip`)
      toast({
        title: 'RO-Crate export ready',
        description: 'Download complete.',
      })
    },
  })

  const animals = animalsQuery.data?.data ?? []
  const timeline = useTimelineEvents('investigation', investigationId ?? '')

  useEffect(() => {
    if (!animals.length) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setFocusedAnimalId(null)
      return
    }
    if (!focusedAnimalId || !animals.some((animal) => animal.id === focusedAnimalId)) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setFocusedAnimalId(animals[0].id)
    }
  }, [animals, focusedAnimalId])

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

  const tags = data.tags ?? []
  const handleAddTag = () => {
    const nextTag = tagInput.trim()
    if (!nextTag || tagMutation.isPending) {
      return
    }
    const nextTags = Array.from(new Set([...tags, nextTag]))
    tagMutation.mutate(nextTags)
    setTagInput('')
  }

  const handleTagKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter') {
      event.preventDefault()
      handleAddTag()
    }
  }

  const handleRosterKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (!animals.length || !focusedAnimalId) {
      return
    }
    const currentIndex = animals.findIndex((animal) => animal.id === focusedAnimalId)
    if (currentIndex === -1) {
      return
    }
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      const nextIndex = Math.min(currentIndex + 1, animals.length - 1)
      setFocusedAnimalId(animals[nextIndex].id)
      return
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault()
      const nextIndex = Math.max(currentIndex - 1, 0)
      setFocusedAnimalId(animals[nextIndex].id)
      return
    }
    if (event.key === 'Enter') {
      event.preventDefault()
      navigate(`/investigations/${data.id}/animals/${focusedAnimalId}`)
      return
    }
    if (event.key.toLowerCase() === 'f') {
      event.preventDefault()
      const currentAnimal = animals[currentIndex]
      favoriteMutation.mutate({ animalId: currentAnimal.id, isFavorite: !currentAnimal.isFavorite })
    }
  }

  const lastWeighIn = animals.map((animal) => animal.lastWeighedAt).sort().reverse()[0]
  const fairVariant = fairVariantFromScore(data.fairScore.total)

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to="/investigations">
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Investigation dashboard</p>
            <h2 className="font-display text-2xl">{data.name}</h2>
            <p className="text-sm text-slate-500">{data.description}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="outline">{data.species}</Badge>
          <Badge variant={fairVariant} aria-label={`FAIR score: ${data.fairScore.total} out of 100`}>
            {data.fairScore.total}/100
          </Badge>
          <Button variant="outline" onClick={() => roCrateMutation.mutate()} disabled={roCrateMutation.isPending}>
            <Download className="h-4 w-4" />
            Export RO-Crate
          </Button>
          <Button variant="outline" asChild>
            <Link to={`/investigations/${data.id}/edit`}>
              <Pencil className="h-4 w-4" />
              Edit
            </Link>
          </Button>
          <Button variant="outline" asChild>
            <Link to={`/investigations/${data.id}/animals`}>
              <PawPrint className="h-4 w-4" />
              Assign animals
            </Link>
          </Button>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-4">
        <StatCard title="Assigned animals" value={String(data.assignedAnimalIds.length)} helper="Active and quarantine" />
        <StatCard
          title="At-risk"
          value={String(dashboardQuery.data?.animalsAtRisk.length ?? 0)}
          helper="Weight or protocol flags"
        />
        <StatCard title="Operators" value={String(data.assignedOperators.length)} helper="On rotation" />
        <StatCard
          title="Last weigh-in"
          value={lastWeighIn ? formatDateTime(lastWeighIn) : 'Pending'}
          helper="Latest cage sweep"
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-4">
        <Card>
          <CardHeader>
            <CardTitle>Schedule</CardTitle>
            <CardDescription>Active study window.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-2 text-sm text-slate-600">
            <div className="flex items-center justify-between">
              <span>Start date</span>
              <span className="font-medium text-slate-800">{data.startAt ? formatDate(data.startAt) : 'N/A'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span>End date</span>
              <span className="font-medium text-slate-800">
                {data.endAt ? formatDate(data.endAt) : 'Open-ended'}
              </span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Operators</CardTitle>
            <CardDescription>Assigned investigation coverage.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            {data.assignedOperators.map((operator: InvestigationOperator) => (
              <div key={operator.id} className="flex items-center justify-between">
                <span className="font-medium text-slate-700">{operator.name}</span>
                <span className="text-xs text-slate-400">{operator.role}</span>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Animals</CardTitle>
            <CardDescription>Current investigation assignments.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-2 text-sm text-slate-600">
            <div className="flex items-center justify-between">
              <span>Assigned animals</span>
              <span className="font-display text-2xl text-slate-900">{data.assignedAnimalIds.length}</span>
            </div>
            <p className="text-xs text-slate-400">Click an animal to review weights and treatments.</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Tags</CardTitle>
            <CardDescription>Labels for quick filters.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            <div className="flex flex-wrap gap-2">
              {tags.length ? (
                tags.map((tag: string) => (
                  <Badge key={tag} variant="outline">
                    {tag}
                  </Badge>
                ))
              ) : (
                <span className="text-xs text-slate-400">No tags yet.</span>
              )}
            </div>
            <div className="flex flex-wrap gap-2">
              <Input
                value={tagInput}
                onChange={(event) => setTagInput(event.target.value)}
                onKeyDown={handleTagKeyDown}
                placeholder="Add tag"
                className="h-9"
              />
              <Button size="sm" onClick={handleAddTag} disabled={!tagInput.trim() || tagMutation.isPending}>
                Add
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      <FairScorePanel score={data.fairScore} criteria={computeFairCriteria(data)} />

      {hasWorkspaceDataError ? (
        <EmptyState
          title="Partial workspace data unavailable"
          description="Some dashboard panels could not load from the backend. You can retry without leaving this page."
          actionLabel="Retry"
          onAction={() => {
            animalsQuery.refetch()
            dashboardQuery.refetch()
            shortcutsQuery.refetch()
            exportStatusQuery.refetch()
          }}
        />
      ) : null}

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <Card>
          <CardHeader>
            <CardTitle>Animals at risk</CardTitle>
            <CardDescription>Weight or protocol flags.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {dashboardQuery.isLoading ? (
              <Skeleton className="h-28 w-full" />
            ) : dashboardQuery.isError ? (
              <p className="text-sm text-slate-500">Risk panel unavailable right now.</p>
            ) : dashboardQuery.data?.animalsAtRisk.length ? (
              dashboardQuery.data.animalsAtRisk.map((animal) => (
                <div key={animal.id} className="flex items-center justify-between rounded-2xl border border-line bg-white p-4 text-sm">
                  <div>
                    <Link to={`/investigations/${data.id}/animals/${animal.id}`} className="font-semibold text-slate-800">
                      {animal.nickname}
                    </Link>
                    <p className="text-xs text-slate-500">{animal.tagId} • {animal.reason}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-semibold text-slate-800">{formatNumber(animal.lastWeight)} g</p>
                    <p className="text-xs text-slate-400">{formatDateTime(animal.lastWeighedAt)}</p>
                  </div>
                </div>
              ))
            ) : (
              <p className="text-sm text-slate-500">No animals at risk right now.</p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Recent actions</CardTitle>
            <CardDescription>Latest activity on the study.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            {dashboardQuery.isLoading ? (
              <Skeleton className="h-28 w-full" />
            ) : dashboardQuery.isError ? (
              <p>Recent actions unavailable right now.</p>
            ) : dashboardQuery.data?.recentActions.length ? (
              dashboardQuery.data.recentActions.map((action) => (
                <div key={action.id} className="rounded-2xl border border-line bg-white p-4">
                  <p className="font-semibold text-slate-800">{action.title}</p>
                  <p className="text-xs text-slate-500">{action.detail}</p>
                  <p className="text-xs text-slate-400">{formatDateTime(action.at)} • {action.actor}</p>
                </div>
              ))
            ) : (
              <p>No actions logged yet.</p>
            )}
          </CardContent>
        </Card>
      </div>

      <Feature flag="timeline.enabled">
        <Card>
          <CardHeader>
            <CardTitle>Assay timeline</CardTitle>
            <CardDescription>Horizontal event tracker for investigation-level study activity.</CardDescription>
          </CardHeader>
          <CardContent>
            {timeline.isError ? (
              <EmptyState
                title="Timeline unavailable"
                description={timeline.error?.message ?? 'Unable to load timeline.'}
                actionLabel="Retry"
                onAction={timeline.refetch}
              />
            ) : timeline.isLoading ? (
              <Skeleton className="h-40 w-full" />
            ) : (
              <AssayTimeline events={timeline.events} />
            )}
          </CardContent>
        </Card>
      </Feature>

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <Card>
          <CardHeader>
            <CardTitle>Group weight evolution</CardTitle>
            <CardDescription>Investigation-wide weight band.</CardDescription>
          </CardHeader>
          <CardContent>
            {dashboardQuery.isLoading ? (
              <Skeleton className="h-40 w-full" />
            ) : dashboardQuery.isError ? (
              <p className="text-sm text-slate-500">Group-weight trend unavailable right now.</p>
            ) : (
              <InvestigationGroupWeightChart points={dashboardQuery.data?.groupWeights ?? []} />
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Marker trends</CardTitle>
            <CardDescription>Selected assay signals.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            {dashboardQuery.isLoading ? (
              <Skeleton className="h-28 w-full" />
            ) : dashboardQuery.isError ? (
              <p>Marker trends unavailable right now.</p>
            ) : dashboardQuery.data?.markerTrends.length ? (
              dashboardQuery.data.markerTrends.map((marker) => (
                <div key={marker.id} className="flex items-center justify-between rounded-2xl border border-line bg-white p-3">
                  <div>
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">{marker.label}</p>
                    <p className="text-lg font-semibold text-slate-800">{formatNumber(marker.value)}</p>
                  </div>
                  <div className="text-right">
                    <MiniSparkline values={marker.history} stroke="#0f172a" />
                    <p className="text-xs text-slate-400">
                      {marker.delta > 0 ? '+' : ''}{formatNumber(marker.delta)} from last check
                    </p>
                  </div>
                </div>
              ))
            ) : (
              <p>No marker trends yet.</p>
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <CardTitle>Animal roster</CardTitle>
              <CardDescription>Assigned animals with quick links.</CardDescription>
            </div>
            <div className="flex flex-col items-start gap-2 sm:items-end">
              <Button
                size="sm"
                variant="outline"
                onClick={() => exportMutation.mutate()}
                disabled={exportMutation.isPending}
              >
                <Download className="h-4 w-4" />
                Export CSV
              </Button>
              <p className="text-xs text-slate-400">
                {exportStatusQuery.isError
                  ? 'Export status unavailable right now.'
                  : exportStatusQuery.data?.lastExportAt
                  ? `Last export ${formatDateTime(exportStatusQuery.data.lastExportAt)} (${exportStatusQuery.data.rowCount} rows)`
                  : 'No exports yet.'}
              </p>
            </div>
          </div>
        </CardHeader>
        <CardContent className="space-y-3">
          {animalsQuery.isLoading ? (
            <Skeleton className="h-24 w-full" />
          ) : animalsQuery.isError ? (
            <p className="text-sm text-slate-500">Animal roster unavailable right now.</p>
          ) : animals.length ? (
            <>
              {shortcutsQuery.isError ? (
                <p className="text-xs text-slate-500">Keyboard shortcuts unavailable right now.</p>
              ) : shortcutsQuery.data?.data?.length ? (
                <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-white px-4 py-3 text-xs text-slate-500">
                  <Keyboard className="h-4 w-4 text-slate-400" />
                  <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Keyboard</span>
                  {shortcutsQuery.data.data.map((shortcut) => (
                    <span key={shortcut.id} className="rounded-full border border-line px-2 py-1 text-slate-600">
                      {shortcut.keys}: {shortcut.label}
                    </span>
                  ))}
                </div>
              ) : null}
              <div
                className="space-y-3 outline-none focus-visible:ring-2 focus-visible:ring-slate-900/20"
                tabIndex={0}
                onKeyDown={handleRosterKeyDown}
                aria-label="Animal roster keyboard navigation"
              >
                {animals.map((animal) => {
                  const status = statusBadgeMap[animal.badgeStatus ?? 'ok']
                  const isFocused = focusedAnimalId === animal.id
                  return (
                    <div
                      key={animal.id}
                      className={`flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-4 text-sm transition ${isFocused ? 'border-slate-900 bg-slate-900/5' : 'border-line bg-white'
                        }`}
                      onClick={() => setFocusedAnimalId(animal.id)}
                      role="option"
                      aria-selected={isFocused}
                    >
                      <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <button
                            type="button"
                            className="rounded-full border border-line p-1 text-slate-400 transition hover:text-amber-500"
                            onClick={(event) => {
                              event.stopPropagation()
                              favoriteMutation.mutate({
                                animalId: animal.id,
                                isFavorite: !animal.isFavorite,
                              })
                            }}
                            aria-label={animal.isFavorite ? 'Unfavorite animal' : 'Favorite animal'}
                          >
                            <Star
                              className={`h-4 w-4 ${animal.isFavorite ? 'fill-amber-400 text-amber-500' : 'text-slate-400'
                                }`}
                            />
                          </button>
                          <Link
                            to={`/investigations/${data.id}/animals/${animal.id}`}
                            className="font-semibold text-slate-800"
                          >
                            {animal.nickname}
                          </Link>
                          <Badge variant="outline">{animal.tagId}</Badge>
                          <Badge variant={status.variant}>{status.label}</Badge>
                        </div>
                        <p className="text-xs text-slate-500">
                          <span className="inline-flex items-center gap-2">
                            <span className={`h-2 w-2 rounded-full ${getCageColorClass(animal.cageColor)}`} />
                            {animal.cage}
                          </span>
                          {' • '}
                          {animal.cohort}
                        </p>
                      </div>
                      <div className="flex items-center gap-4">
                        <MiniSparkline values={animal.weightHistory ?? []} stroke="#0f172a" />
                        <div className="text-right">
                          <p className="font-semibold text-slate-800">{formatNumber(animal.lastWeight)} g</p>
                          <p className="text-xs text-slate-400">{formatDateTime(animal.lastWeighedAt)}</p>
                        </div>
                      </div>
                    </div>
                  )
                })}
              </div>
            </>
          ) : (
            <p className="text-sm text-slate-500">No animals assigned yet.</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
