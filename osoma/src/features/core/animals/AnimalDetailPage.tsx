import { type FormEvent, useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, CircleAlert, Play, StopCircle } from 'lucide-react'
import {
  createAnimalSample,
  createAnimalTreatment,
  createAnimalWeight,
  getAnimal,
  getAnimalAnalysis,
  getAnimalBehavior,
  getAnimalSamples,
  getAnimalTimeline,
  getAnimalTreatments,
  getAnimalWeightRange,
  getAnimalWeights,
  updateAnimalNote,
} from './animal.api.ts'
import type { AnimalWeightEntry } from './animal.types.ts'
import { AnimalTimeline } from './AnimalTimeline.tsx'
import { BehaviorChart } from './BehaviorChart.tsx'
import { MiniSparkline } from './MiniSparkline.tsx'
import { WeightChart } from './WeightChart.tsx'
import { getInvestigation } from '@/features/core/investigations/investigation.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { SegmentedTabs } from '@/components/SegmentedTabs.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { formatDate, formatDateTime, formatNumber } from '@/lib/format.ts'

const buildDateTimeInputValue = (value?: string) => {
  const date = value ? new Date(value) : new Date()
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

export function AnimalDetailPage() {
  const { investigationId, animalId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { toast } = useToast()

  const animalQuery = useQuery({
    queryKey: ['animal', animalId],
    queryFn: () => getAnimal(animalId ?? ''),
    enabled: Boolean(animalId),
  })

  const investigationQuery = useQuery({
    queryKey: ['investigation', investigationId],
    queryFn: () => getInvestigation(investigationId ?? ''),
    enabled: Boolean(investigationId),
  })

  const weightsQuery = useQuery({
    queryKey: ['animal-weights', animalId],
    queryFn: () => getAnimalWeights(animalId ?? ''),
    enabled: Boolean(animalId),
  })

  const rangeQuery = useQuery({
    queryKey: ['animal-weight-range', animalId],
    queryFn: () => getAnimalWeightRange(animalId ?? ''),
    enabled: Boolean(animalId),
  })

  const treatmentsQuery = useQuery({
    queryKey: ['animal-treatments', animalId],
    queryFn: () => getAnimalTreatments(animalId ?? ''),
    enabled: Boolean(animalId),
  })

  const samplesQuery = useQuery({
    queryKey: ['animal-samples', animalId],
    queryFn: () => getAnimalSamples(animalId ?? ''),
    enabled: Boolean(animalId),
  })

  const analysisQuery = useQuery({
    queryKey: ['animal-analysis', animalId],
    queryFn: () => getAnimalAnalysis(animalId ?? ''),
    enabled: Boolean(animalId),
  })

  const behaviorQuery = useQuery({
    queryKey: ['animal-behavior', animalId],
    queryFn: () => getAnimalBehavior(animalId ?? ''),
    enabled: Boolean(animalId),
  })

  const timelineQuery = useQuery({
    queryKey: ['animal-timeline', animalId],
    queryFn: () => getAnimalTimeline(animalId ?? ''),
    enabled: Boolean(animalId),
  })

  const [weights, setWeights] = useState<AnimalWeightEntry[]>([])
  const [liveFeed, setLiveFeed] = useState(false)
  const [noteDraft, setNoteDraft] = useState('')

  useEffect(() => {
    if (weightsQuery.data?.data) {
      setWeights(weightsQuery.data.data)
    }
  }, [weightsQuery.data])

  useEffect(() => {
    if (animalQuery.data) {
      setNoteDraft(animalQuery.data.quickNote ?? '')
    }
  }, [animalQuery.data])

  const weightMutation = useMutation({
    mutationFn: (payload: Pick<AnimalWeightEntry, 'grams' | 'at' | 'source'>) =>
      createAnimalWeight(animalId ?? '', payload),
    onSuccess: (entry) => {
      setWeights((prev) => [...prev, entry])
      queryClient.invalidateQueries({ queryKey: ['animal-timeline', animalId] })
    },
  })

  const noteMutation = useMutation({
    mutationFn: (note: string) => updateAnimalNote(animalId ?? '', note),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['animal', animalId] })
      toast({ title: 'Note saved', description: 'Quick note updated.' })
    },
  })

  const handleSimulatedWeight = useCallback(() => {
    if (!animalId || weightMutation.isPending) {
      return
    }
    const range = rangeQuery.data
    const last = weights[weights.length - 1]
    const base = last?.grams ?? range?.target ?? 20
    const jitter = (Math.random() - 0.45) * 0.8
    const next = Number((base + jitter).toFixed(1))

    weightMutation.mutate({
      grams: next,
      at: new Date().toISOString(),
      source: 'scale',
    })
  }, [animalId, rangeQuery.data, weights, weightMutation])

  useEffect(() => {
    if (!liveFeed) {
      return
    }
    const interval = setInterval(() => {
      handleSimulatedWeight()
    }, 3500)
    return () => clearInterval(interval)
  }, [handleSimulatedWeight, liveFeed])

  const [treatmentForm, setTreatmentForm] = useState({
    drug: '',
    pathogen: '',
    lot: '',
    dose: '',
    doseUnit: 'mg/kg',
    operator: '',
    administeredAt: buildDateTimeInputValue(),
    notes: '',
  })

  const treatmentMutation = useMutation({
    mutationFn: () =>
      createAnimalTreatment(animalId ?? '', {
        drug: treatmentForm.drug.trim(),
        pathogen: treatmentForm.pathogen.trim(),
        lot: treatmentForm.lot.trim(),
        dose: Number(treatmentForm.dose),
        doseUnit: treatmentForm.doseUnit.trim(),
        operator: treatmentForm.operator.trim() || 'On-call operator',
        administeredAt: new Date(treatmentForm.administeredAt).toISOString(),
        notes: treatmentForm.notes.trim() || undefined,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['animal-treatments', animalId] })
      queryClient.invalidateQueries({ queryKey: ['animal-timeline', animalId] })
      toast({ title: 'Treatment logged', description: 'Event added to the timeline.' })
      setTreatmentForm((prev) => ({
        ...prev,
        drug: '',
        pathogen: '',
        lot: '',
        dose: '',
        notes: '',
      }))
    },
  })

  const [sampleForm, setSampleForm] = useState({
    collectedAt: buildDateTimeInputValue(),
    operator: '',
    volume: '',
    volumeUnit: 'uL',
    site: 'Submandibular',
  })

  const sampleMutation = useMutation({
    mutationFn: () =>
      createAnimalSample(animalId ?? '', {
        investigationId: animalQuery.data?.investigationId ?? investigationId ?? 'PRJ-0000',
        collectedAt: new Date(sampleForm.collectedAt).toISOString(),
        operator: sampleForm.operator.trim() || 'Sampling desk',
        volume: Number(sampleForm.volume),
        volumeUnit: sampleForm.volumeUnit.trim(),
        site: sampleForm.site.trim(),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['animal-samples', animalId] })
      queryClient.invalidateQueries({ queryKey: ['animal-timeline', animalId] })
      toast({ title: 'Sample created', description: 'Blood sample logged and linked to investigation.' })
      setSampleForm((prev) => ({
        ...prev,
        volume: '',
        operator: '',
      }))
    },
  })

  const statusVariant =
    animalQuery.data?.status === 'at-risk'
      ? 'warning'
      : animalQuery.data?.status === 'quarantine'
        ? 'outline'
        : 'success'

  const lastWeight = weights[weights.length - 1]
  const range = rangeQuery.data
  const isOutOfRange =
    Boolean(range && lastWeight && (lastWeight.grams < range.min || lastWeight.grams > range.max))

  const weightDelta = useMemo(() => {
    if (weights.length < 2) {
      return null
    }
    const previous = weights[weights.length - 2]
    return lastWeight.grams - previous.grams
  }, [lastWeight, weights])

  const isNoteDirty = noteDraft.trim() !== (animalQuery.data?.quickNote ?? '').trim()

  if (animalQuery.isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (animalQuery.isError || !animalQuery.data) {
    return (
      <EmptyState
        title="Animal unavailable"
        description={animalQuery.error?.message ?? 'Animal record missing.'}
        actionLabel="Back to investigation"
        onAction={() => navigate(investigationId ? `/investigations/${investigationId}` : '/investigations')}
      />
    )
  }

  const overview = (
    <div className="space-y-6">
      <div className="grid gap-4 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>Identity</CardTitle>
            <CardDescription>Tick@Lab registration.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-2 text-sm text-slate-600">
            <div className="flex items-center justify-between">
              <span>Tag ID</span>
              <span className="font-medium text-slate-800">{animalQuery.data.tagId}</span>
            </div>
            <div className="flex items-center justify-between">
              <span>Strain</span>
              <span className="font-medium text-slate-800">{animalQuery.data.strain}</span>
            </div>
            <div className="flex items-center justify-between">
              <span>Sex</span>
              <span className="font-medium text-slate-800">{animalQuery.data.sex}</span>
            </div>
            <div className="flex items-center justify-between">
              <span>Age</span>
              <span className="font-medium text-slate-800">{animalQuery.data.ageWeeks} weeks</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Housing</CardTitle>
            <CardDescription>Current location.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-2 text-sm text-slate-600">
            <div className="flex items-center justify-between">
              <span>Room</span>
              <span className="font-medium text-slate-800">{animalQuery.data.room}</span>
            </div>
            <div className="flex items-center justify-between">
              <span>Cage</span>
              <span className="font-medium text-slate-800">{animalQuery.data.cage}</span>
            </div>
            <div className="flex items-center justify-between">
              <span>Cohort</span>
              <span className="font-medium text-slate-800">{animalQuery.data.cohort}</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Latest status</CardTitle>
            <CardDescription>Weight and flags.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            <div className="flex items-center justify-between">
              <span>Last weight</span>
              <span className="font-medium text-slate-800">{formatNumber(animalQuery.data.lastWeight)} g</span>
            </div>
            <div className="flex items-center justify-between">
              <span>Weighed</span>
              <span className="font-medium text-slate-800">{formatDateTime(animalQuery.data.lastWeighedAt)}</span>
            </div>
            <div className="flex items-center justify-between">
              <span>Baseline</span>
              <span className="font-medium text-slate-800">{formatNumber(animalQuery.data.baselineWeight)} g</span>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Investigation context</CardTitle>
          <CardDescription>Assignment and ownership.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap items-center justify-between gap-4 text-sm text-slate-600">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Investigation</p>
            <p className="font-semibold text-slate-700">
              {investigationQuery.data?.name ?? animalQuery.data.investigationId ?? 'Unassigned'}
            </p>
            <p className="text-xs text-slate-500">{animalQuery.data.investigationId}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Assigned</p>
            <p className="font-semibold text-slate-700">
              {animalQuery.data.assignedAt ? formatDate(animalQuery.data.assignedAt) : '—'}
            </p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Status</p>
            <Badge variant={statusVariant}>{animalQuery.data.status.replace('-', ' ')}</Badge>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Quick note</CardTitle>
          <CardDescription>Daily observations for this animal.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3 text-sm text-slate-600">
          <Textarea
            value={noteDraft}
            onChange={(event) => setNoteDraft(event.target.value)}
            placeholder="Add a quick note for the next shift..."
            className="min-h-[120px]"
          />
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-xs text-slate-400">Saved notes stay with the animal profile.</p>
            <Button
              size="sm"
              onClick={() => noteMutation.mutate(noteDraft)}
              disabled={!isNoteDirty || noteMutation.isPending}
            >
              Save note
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )

  const weightsTab = (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Weight trend</CardTitle>
          <CardDescription>Auto-updates when live feed is enabled.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {weightsQuery.isLoading ? (
            <Skeleton className="h-52 w-full" />
          ) : (
            <WeightChart weights={weights} range={range} />
          )}

          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
              {range ? (
                <span className="rounded-full border border-line px-2 py-1">
                  Target {range.min}-{range.max} g
                </span>
              ) : null}
              {weightDelta !== null ? (
                <span className="rounded-full border border-line px-2 py-1">
                  Delta {weightDelta > 0 ? '+' : ''}{formatNumber(weightDelta)} g
                </span>
              ) : null}
              {isOutOfRange ? <Badge variant="warning">Out of range</Badge> : null}
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={handleSimulatedWeight}
                disabled={weightMutation.isPending}
              >
                Push weight
              </Button>
              <Button
                type="button"
                size="sm"
                variant={liveFeed ? 'outline' : 'default'}
                onClick={() => setLiveFeed((prev) => !prev)}
              >
                {liveFeed ? (
                  <>
                    <StopCircle className="h-4 w-4" />
                    Stop live feed
                  </>
                ) : (
                  <>
                    <Play className="h-4 w-4" />
                    Start live feed
                  </>
                )}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )

  const treatmentsTab = (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Treatment event</CardTitle>
          <CardDescription>Log drug and pathogen dosing.</CardDescription>
        </CardHeader>
        <CardContent>
          <form
            className="grid gap-4 md:grid-cols-2"
            onSubmit={(event: FormEvent<HTMLFormElement>) => {
              event.preventDefault()
              if (!treatmentForm.drug.trim()) {
                toast({ title: 'Drug required', description: 'Enter a drug or compound name.' })
                return
              }
              if (!treatmentForm.dose) {
                toast({ title: 'Dose required', description: 'Enter a dose value.' })
                return
              }
              treatmentMutation.mutate()
            }}
          >
            <div>
              <Label>Drug / compound</Label>
              <Input
                value={treatmentForm.drug}
                onChange={(event) => setTreatmentForm((prev) => ({ ...prev, drug: event.target.value }))}
                placeholder="Dexamethasone"
              />
            </div>
            <div>
              <Label>Pathogen / trigger</Label>
              <Input
                value={treatmentForm.pathogen}
                onChange={(event) => setTreatmentForm((prev) => ({ ...prev, pathogen: event.target.value }))}
                placeholder="Listeria challenge"
              />
            </div>
            <div>
              <Label>Lot</Label>
              <Input
                value={treatmentForm.lot}
                onChange={(event) => setTreatmentForm((prev) => ({ ...prev, lot: event.target.value }))}
                placeholder="LOT-7734"
              />
            </div>
            <div>
              <Label>Dose</Label>
              <div className="flex gap-2">
                <Input
                  type="number"
                  value={treatmentForm.dose}
                  onChange={(event) => setTreatmentForm((prev) => ({ ...prev, dose: event.target.value }))}
                  placeholder="2.5"
                />
                <Input
                  value={treatmentForm.doseUnit}
                  onChange={(event) => setTreatmentForm((prev) => ({ ...prev, doseUnit: event.target.value }))}
                  className="w-28"
                />
              </div>
            </div>
            <div>
              <Label>Operator</Label>
              <Input
                value={treatmentForm.operator}
                onChange={(event) => setTreatmentForm((prev) => ({ ...prev, operator: event.target.value }))}
                placeholder="Operator name"
              />
            </div>
            <div>
              <Label>Time</Label>
              <Input
                type="datetime-local"
                value={treatmentForm.administeredAt}
                onChange={(event) => setTreatmentForm((prev) => ({ ...prev, administeredAt: event.target.value }))}
              />
            </div>
            <div className="md:col-span-2">
              <Label>Notes</Label>
              <Textarea
                value={treatmentForm.notes}
                onChange={(event) => setTreatmentForm((prev) => ({ ...prev, notes: event.target.value }))}
                placeholder="Observations during dosing."
              />
            </div>
            <div className="md:col-span-2">
              <Button type="submit" disabled={treatmentMutation.isPending}>
                Log treatment
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Recent treatments</CardTitle>
          <CardDescription>Latest dosing events.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {treatmentsQuery.isLoading ? (
            <Skeleton className="h-24 w-full" />
          ) : treatmentsQuery.data?.data.length ? (
            treatmentsQuery.data.data.map((event) => (
              <div key={event.id} className="rounded-2xl border border-line bg-white p-4 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-semibold text-slate-800">
                    {event.drug} • {event.dose} {event.doseUnit}
                  </p>
                  <Badge variant="outline">{event.lot}</Badge>
                </div>
                <p className="text-xs text-slate-500">
                  {event.pathogen} • {formatDateTime(event.administeredAt)} • {event.operator}
                </p>
                {event.notes ? <p className="mt-2 text-xs text-slate-500">{event.notes}</p> : null}
              </div>
            ))
          ) : (
            <p className="text-sm text-slate-500">No treatments logged yet.</p>
          )}
        </CardContent>
      </Card>
    </div>
  )

  const samplesTab = (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Blood sampling</CardTitle>
          <CardDescription>Generate a linked sample record.</CardDescription>
        </CardHeader>
        <CardContent>
          <form
            className="grid gap-4 md:grid-cols-2"
            onSubmit={(event: FormEvent<HTMLFormElement>) => {
              event.preventDefault()
              if (!sampleForm.volume) {
                toast({ title: 'Volume required', description: 'Enter a sample volume.' })
                return
              }
              sampleMutation.mutate()
            }}
          >
            <div>
              <Label>Collected at</Label>
              <Input
                type="datetime-local"
                value={sampleForm.collectedAt}
                onChange={(event) => setSampleForm((prev) => ({ ...prev, collectedAt: event.target.value }))}
              />
            </div>
            <div>
              <Label>Operator</Label>
              <Input
                value={sampleForm.operator}
                onChange={(event) => setSampleForm((prev) => ({ ...prev, operator: event.target.value }))}
                placeholder="Operator name"
              />
            </div>
            <div>
              <Label>Volume</Label>
              <div className="flex gap-2">
                <Input
                  type="number"
                  value={sampleForm.volume}
                  onChange={(event) => setSampleForm((prev) => ({ ...prev, volume: event.target.value }))}
                  placeholder="120"
                />
                <Input
                  value={sampleForm.volumeUnit}
                  onChange={(event) => setSampleForm((prev) => ({ ...prev, volumeUnit: event.target.value }))}
                  className="w-24"
                />
              </div>
            </div>
            <div>
              <Label>Site</Label>
              <Input
                value={sampleForm.site}
                onChange={(event) => setSampleForm((prev) => ({ ...prev, site: event.target.value }))}
              />
            </div>
            <div className="md:col-span-2">
              <Button type="submit" disabled={sampleMutation.isPending}>
                Create sample
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Recent samples</CardTitle>
          <CardDescription>Latest blood draws linked to investigation.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {samplesQuery.isLoading ? (
            <Skeleton className="h-24 w-full" />
          ) : samplesQuery.data?.data.length ? (
            samplesQuery.data.data.map((sample) => (
              <div key={sample.id} className="rounded-2xl border border-line bg-white p-4 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-semibold text-slate-800">{sample.sampleId}</p>
                  <Badge variant="outline">{sample.investigationId}</Badge>
                </div>
                <p className="text-xs text-slate-500">
                  {sample.volume}{sample.volumeUnit} • {sample.site} • {formatDateTime(sample.collectedAt)}
                </p>
                <p className="text-xs text-slate-400">{sample.operator}</p>
              </div>
            ))
          ) : (
            <p className="text-sm text-slate-500">No samples logged yet.</p>
          )}
        </CardContent>
      </Card>
    </div>
  )

  const analysisTab = (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Analysis results</CardTitle>
          <CardDescription>Flow, PCR, antibody, and trend signals.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {analysisQuery.isLoading ? (
            <Skeleton className="h-48 w-full" />
          ) : analysisQuery.data ? (
            <>
              <div className="grid gap-4 md:grid-cols-3">
                {analysisQuery.data.trends.map((trend) => (
                  <div key={trend.id} className="rounded-2xl border border-line bg-white p-4">
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">{trend.label}</p>
                    <div className="mt-2 flex items-center justify-between">
                      <p className="text-lg font-semibold text-slate-800">{trend.values.at(-1)}</p>
                      <MiniSparkline values={trend.values} stroke="#0f172a" />
                    </div>
                  </div>
                ))}
              </div>

              <div className="grid gap-4 lg:grid-cols-3">
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Flow cytometry</p>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Marker</TableHead>
                        <TableHead>Population</TableHead>
                        <TableHead>Value</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {analysisQuery.data.flow.map((row) => (
                        <TableRow key={row.id}>
                          <TableCell>{row.marker}</TableCell>
                          <TableCell>{row.population}</TableCell>
                          <TableCell>{formatNumber(row.value)}{row.unit}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">PCR</p>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Target</TableHead>
                        <TableHead>CT</TableHead>
                        <TableHead>Delta</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {analysisQuery.data.pcr.map((row) => (
                        <TableRow key={row.id}>
                          <TableCell>{row.target}</TableCell>
                          <TableCell>{formatNumber(row.ct)}</TableCell>
                          <TableCell>{formatNumber(row.deltaCt)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Antibody</p>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Antigen</TableHead>
                        <TableHead>Titer</TableHead>
                        <TableHead>Unit</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {analysisQuery.data.antibody.map((row) => (
                        <TableRow key={row.id}>
                          <TableCell>{row.antigen}</TableCell>
                          <TableCell>{formatNumber(row.titer)}</TableCell>
                          <TableCell>{row.unit}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </div>
              <p className="text-xs text-slate-400">Updated {formatDateTime(analysisQuery.data.updatedAt)}</p>
            </>
          ) : null}
        </CardContent>
      </Card>
    </div>
  )

  const behaviorTab = (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Behavior series</CardTitle>
          <CardDescription>Circadian activity and anomalies.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {behaviorQuery.isLoading ? (
            <Skeleton className="h-40 w-full" />
          ) : behaviorQuery.data ? (
            <>
              <BehaviorChart series={behaviorQuery.data} />
              <div className="grid gap-4 md:grid-cols-3">
                <div className="rounded-2xl border border-line bg-white p-4 text-sm">
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Baseline activity</p>
                  <p className="mt-2 text-lg font-semibold text-slate-800">{behaviorQuery.data.baseline}</p>
                </div>
                <div className="rounded-2xl border border-line bg-white p-4 text-sm">
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Circadian peak</p>
                  <p className="mt-2 text-lg font-semibold text-slate-800">{behaviorQuery.data.circadianPeak}</p>
                </div>
                <div className="rounded-2xl border border-line bg-white p-4 text-sm">
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Circadian low</p>
                  <p className="mt-2 text-lg font-semibold text-slate-800">{behaviorQuery.data.circadianLow}</p>
                </div>
              </div>
              {behaviorQuery.data.points.some((point) => point.anomaly) ? (
                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                  <div className="flex items-center gap-2">
                    <CircleAlert className="h-4 w-4" />
                    <p className="font-semibold">Anomalies detected in the last 24h.</p>
                  </div>
                  <p className="mt-1 text-xs text-amber-700">Review spikes and correlate with treatment timeline.</p>
                </div>
              ) : null}
            </>
          ) : null}
        </CardContent>
      </Card>
    </div>
  )

  const timelineTab = (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Animal 360 timeline</CardTitle>
          <CardDescription>All events merged chronologically.</CardDescription>
        </CardHeader>
        <CardContent>
          {timelineQuery.isLoading ? (
            <Skeleton className="h-40 w-full" />
          ) : timelineQuery.isError ? (
            <EmptyState
              title="Timeline unavailable"
              description={timelineQuery.error?.message ?? 'Unable to load timeline.'}
              actionLabel="Retry"
              onAction={() => timelineQuery.refetch()}
            />
          ) : (
            <AnimalTimeline events={timelineQuery.data?.data ?? []} />
          )}
        </CardContent>
      </Card>
    </div>
  )

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
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Animal detail</p>
            <h2 className="font-display text-2xl">{animalQuery.data.nickname}</h2>
            <p className="text-sm text-slate-500">{animalQuery.data.tagId}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="outline">{animalQuery.data.strain}</Badge>
          <Badge variant={statusVariant}>{animalQuery.data.status.replace('-', ' ')}</Badge>
          <Badge variant="outline">{animalQuery.data.sex}</Badge>
        </div>
      </div>

      <SegmentedTabs
        tabs={[
          { id: 'overview', label: 'Overview', content: overview },
          { id: 'weights', label: 'Weight', content: weightsTab },
          { id: 'treatments', label: 'Treatment', content: treatmentsTab },
          { id: 'samples', label: 'Sampling', content: samplesTab },
          { id: 'analysis', label: 'Analysis', content: analysisTab },
          { id: 'behavior', label: 'Behavior', content: behaviorTab },
          { id: 'timeline', label: 'Timeline', content: timelineTab },
        ]}
      />
    </div>
  )
}
