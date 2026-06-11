import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { DownloadCloud, Filter, WandSparkles } from 'lucide-react'
import JSZip from 'jszip'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { vcgMatchModes } from '@/domain/dvc/dvc.enums.ts'
import type { Dataset, VcgSelection } from '@/domain/dvc/dvc.types.ts'
import { buildCircadianTraceForSeries, buildMeanCircadianBundle } from '@/domain/nam/vcg.combine.ts'
import { useFeatureFlag } from '@/feature-flags/useFeatureFlag.ts'
import { formatDateTime } from '@/lib/format.ts'
import { generateVcg, getNamActivity, getNamDatasets } from './dvc.api.ts'
import { CircadianMeanCompareChart } from '@/features/integrations/tecniplast/nam-vcg/CircadianMeanCompareChart.tsx'

const buildCsv = (selection: VcgSelection, target: Dataset) => {
  const rows = [
    ['dataset_id', 'pid', 'similarity_score', 'strain', 'sex', 'age_weeks', 'sanitary_status', 'environment', 'bedding'],
    [
      target.id,
      target.pid,
      'target',
      target.strain,
      target.sex,
      `${target.ageWeeks}`,
      target.sanitaryStatus,
      target.environment,
      target.beddingMaterial,
    ],
    ...selection.candidates.map((candidate) => [
      candidate.datasetId,
      candidate.pid,
      `${candidate.similarityScore}`,
      candidate.metadata.strain,
      candidate.metadata.sex,
      `${candidate.metadata.ageWeeks}`,
      candidate.metadata.sanitaryStatus,
      candidate.metadata.environment,
      candidate.metadata.beddingMaterial,
    ]),
  ]

  return rows.map((row) => row.join(',')).join('\n')
}

const downloadBlob = (blob: Blob, filename: string) => {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.click()
  URL.revokeObjectURL(url)
}

export function NamVcgWorkbenchPage() {
  const { toast } = useToast()
  const exportEnabled = useFeatureFlag('dvc.export.enabled')
  const meanTraceEnabled = useFeatureFlag('namVcg.meanTrace.enabled')
  const [targetId, setTargetId] = useState('')
  const [mode, setMode] = useState<(typeof vcgMatchModes)[number]>('Weighted Similarity')
  const [search, setSearch] = useState('')
  const [strain, setStrain] = useState('')
  const [sanitary, setSanitary] = useState('')
  const [bedding, setBedding] = useState('')
  const [traceSmoothing, setTraceSmoothing] = useState<'none' | 'light'>('none')

  const datasetsQuery = useQuery({
    queryKey: ['nam-datasets'],
    queryFn: () => getNamDatasets(),
  })

  const datasets = datasetsQuery.data?.data ?? []

  const filteredDatasets = useMemo(() => {
    return datasets.filter((dataset) => {
      const keyword = search.trim().toLowerCase()
      const matchesSearch =
        !keyword ||
        dataset.name.toLowerCase().includes(keyword) ||
        dataset.id.toLowerCase().includes(keyword) ||
        dataset.pid.toLowerCase().includes(keyword)
      const matchesStrain = !strain || dataset.strain === strain
      const matchesSanitary = !sanitary || dataset.sanitaryStatus === sanitary
      const matchesBedding = !bedding || dataset.beddingMaterial === bedding
      return matchesSearch && matchesStrain && matchesSanitary && matchesBedding
    })
  }, [datasets, search, strain, sanitary, bedding])

  const targetDataset = datasets.find((dataset) => dataset.id === targetId) ?? filteredDatasets[0]

  const generateMutation = useMutation({
    mutationFn: async () => {
      if (!targetDataset) {
        throw new Error('Select a target dataset to generate a VCG.')
      }
      return generateVcg({ targetDatasetId: targetDataset.id, mode })
    },
    onSuccess: () => {
      toast({ title: 'VCG generated', description: 'Matching scores and confounders updated.' })
    },
    onError: (error) => {
      toast({ title: 'VCG generation failed', description: (error as Error).message })
    },
  })

  const selection = generateMutation.data

  const activityIds = useMemo(() => {
    if (!selection || !targetDataset) return []
    return Array.from(new Set([targetDataset.id, ...selection.candidates.map((candidate) => candidate.datasetId)]))
  }, [selection, targetDataset])

  const activityQuery = useQuery({
    queryKey: ['nam-activity', activityIds.join('|'), traceSmoothing],
    queryFn: () => {
      const to = new Date()
      const from = new Date(to.getTime() - 7 * 24 * 60 * 60 * 1000)
      return getNamActivity({
        datasetIds: activityIds,
        from: from.toISOString(),
        to: to.toISOString(),
        interval: 15,
      })
    },
    enabled: meanTraceEnabled && activityIds.length > 0,
  })

  const activitySeries = activityQuery.data?.data ?? []
  const targetSeries = activitySeries.find((series) => series.datasetId === targetDataset?.id) ?? null
  const vcgSeries = activitySeries.filter((series) => series.datasetId !== targetDataset?.id)

  const targetTrace = useMemo(
    () => (targetSeries ? buildCircadianTraceForSeries(targetSeries, traceSmoothing) : null),
    [targetSeries, traceSmoothing]
  )
  const vcgBundle = useMemo(
    () => buildMeanCircadianBundle(vcgSeries, traceSmoothing),
    [vcgSeries, traceSmoothing]
  )

  const handleExport = async () => {
    if (!selection || !targetDataset) return
    const zip = new JSZip()
    const candidates = selection.candidates.map((candidate) => ({
      datasetId: candidate.datasetId,
      pid: candidate.pid,
      similarityScore: candidate.similarityScore,
      metadata: candidate.metadata,
      confounders: candidate.confounders,
    }))

    const contextSnapshot = {
      targetDatasetId: targetDataset.id,
      pid: targetDataset.pid,
      beddingBatch: targetDataset.beddingBatch,
      beddingMaterial: targetDataset.beddingMaterial,
      sanitaryStatus: targetDataset.sanitaryStatus,
      environment: targetDataset.environment,
      device: targetDataset.device,
      pipelineVersion: targetDataset.pipelineVersion,
    }

    const provenance = {
      generatedAt: selection.generatedAt,
      mode: selection.mode,
      target: targetDataset.pid,
      candidates: candidates.map((candidate) => candidate.pid),
      system: 'OneMeta DVC Activity Explorer',
    }

    zip.file('target_dataset.json', JSON.stringify(targetDataset, null, 2))
    zip.file('vcg_datasets.json', JSON.stringify(candidates, null, 2))
    zip.file('vcg_selection.json', JSON.stringify(selection, null, 2))
    zip.file('context_snapshot.json', JSON.stringify(contextSnapshot, null, 2))
    zip.file('provenance.json', JSON.stringify(provenance, null, 2))
    zip.file('activity_summary.csv', buildCsv(selection, targetDataset))
    zip.file(
      'README.txt',
      'VCG FAIR bundle containing target + virtual control datasets, metadata context, and provenance.'
    )

    const blob = await zip.generateAsync({ type: 'blob' })
    downloadBlob(blob, `vcg-${targetDataset.id}.zip`)
  }

  if (datasetsQuery.isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (datasetsQuery.isError) {
    return (
      <EmptyState
        title="NAM datasets unavailable"
        description={(datasetsQuery.error as Error)?.message ?? 'Dataset feed missing.'}
        actionLabel="Retry"
        onAction={() => datasetsQuery.refetch()}
      />
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="NAM-VCG Workbench"
        subtitle="Generate virtual control groups with metadata-first matching and confounder rationale."
        actions={
          <Button onClick={() => generateMutation.mutate()}>
            <WandSparkles className="h-4 w-4" />
            Generate VCG
          </Button>
        }
      />

      <Card>
        <CardHeader>
          <CardTitle>Target + filters</CardTitle>
          <CardDescription>Metadata drives cohort discovery and similarity scoring.</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4 md:grid-cols-2">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Target dataset</p>
            <select
              className="mt-2 h-10 w-full rounded-xl border border-line bg-white px-3 text-sm"
              value={targetDataset?.id ?? ''}
              onChange={(event) => setTargetId(event.target.value)}
            >
              {filteredDatasets.map((dataset) => (
                <option key={dataset.id} value={dataset.id}>
                  {dataset.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Match mode</p>
            <select
              className="mt-2 h-10 w-full rounded-xl border border-line bg-white px-3 text-sm"
              value={mode}
              onChange={(event) => setMode(event.target.value as (typeof vcgMatchModes)[number])}
            >
              {vcgMatchModes.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </div>
          <div className="md:col-span-2">
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Metadata filters</p>
            <div className="mt-2 grid gap-3 md:grid-cols-4">
              <div className="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2">
                <Filter className="h-4 w-4 text-slate-400" />
                <Input
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Search dataset, PID"
                  className="h-7 border-0 bg-transparent p-0 text-sm focus-visible:ring-0"
                />
              </div>
              <select
                className="h-10 rounded-xl border border-line bg-white px-3 text-sm"
                value={strain}
                onChange={(event) => setStrain(event.target.value)}
              >
                <option value="">All strains</option>
                {Array.from(new Set(datasets.map((dataset) => dataset.strain))).map((value) => (
                  <option key={value} value={value}>
                    {value}
                  </option>
                ))}
              </select>
              <select
                className="h-10 rounded-xl border border-line bg-white px-3 text-sm"
                value={sanitary}
                onChange={(event) => setSanitary(event.target.value)}
              >
                <option value="">All sanitary</option>
                {Array.from(new Set(datasets.map((dataset) => dataset.sanitaryStatus))).map((value) => (
                  <option key={value} value={value}>
                    {value}
                  </option>
                ))}
              </select>
              <select
                className="h-10 rounded-xl border border-line bg-white px-3 text-sm"
                value={bedding}
                onChange={(event) => setBedding(event.target.value)}
              >
                <option value="">All bedding</option>
                {Array.from(new Set(datasets.map((dataset) => dataset.beddingMaterial))).map((value) => (
                  <option key={value} value={value}>
                    {value}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </CardContent>
      </Card>

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>VCG candidates</CardTitle>
              <CardDescription>Ranked by similarity score and metadata alignment.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {selection ? (
                selection.candidates.map((candidate) => (
                  <div key={candidate.datasetId} className="rounded-2xl border border-line bg-surface-2 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <div>
                        <p className="text-sm font-semibold text-slate-800">{candidate.name}</p>
                        <p className="text-xs text-slate-500">{candidate.pid}</p>
                      </div>
                      <Badge variant="outline">{candidate.similarityScore}% match</Badge>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                      {candidate.matchNotes.map((note) => (
                        <Badge key={note} variant="outline">
                          {note}
                        </Badge>
                      ))}
                    </div>
                    {candidate.confounders.length ? (
                      <div className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-slate-600">
                        <p className="font-semibold text-slate-700">Confounders</p>
                        <ul className="mt-2 list-disc space-y-1 pl-4">
                          {candidate.confounders.map((item) => (
                            <li key={item}>{item}</li>
                          ))}
                        </ul>
                      </div>
                    ) : (
                      <p className="mt-3 text-xs text-slate-500">No confounders detected.</p>
                    )}
                  </div>
                ))
              ) : (
                <EmptyState
                  title="Generate a VCG"
                  description="Select a target dataset and generate candidates."
                />
              )}
            </CardContent>
          </Card>

          {meanTraceEnabled ? (
            selection && targetDataset ? (
              activityQuery.isLoading ? (
                <div className="space-y-3">
                  <Skeleton className="h-40 w-full" />
                  <Skeleton className="h-24 w-full" />
                </div>
              ) : activityQuery.isError || !targetTrace ? (
                <EmptyState
                  title="Circadian compare unavailable"
                  description={(activityQuery.error as Error)?.message ?? 'Trace data missing.'}
                  actionLabel="Retry"
                  onAction={() => activityQuery.refetch()}
                />
              ) : (
                <div className="space-y-3">
                  <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Smoothing</span>
                    <Button
                      size="sm"
                      variant={traceSmoothing === 'none' ? 'default' : 'outline'}
                      onClick={() => setTraceSmoothing('none')}
                    >
                      None
                    </Button>
                    <Button
                      size="sm"
                      variant={traceSmoothing === 'light' ? 'default' : 'outline'}
                      onClick={() => setTraceSmoothing('light')}
                    >
                      Light
                    </Button>
                  </div>
                  <CircadianMeanCompareChart
                    target={targetTrace}
                    mean={vcgBundle.mean}
                    stdBand={vcgBundle.std}
                  />
                </div>
              )
            ) : (
              <EmptyState
                title="Circadian compare ready"
                description="Generate a VCG to compare circadian means."
              />
            )
          ) : null}

          {meanTraceEnabled ? (
            <Card>
              <CardHeader>
                <CardTitle>VCG composition</CardTitle>
                <CardDescription>Datasets contributing to the mean trace.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-2 text-sm text-slate-600">
                {selection ? (
                  selection.candidates.map((candidate) => (
                    <div key={candidate.datasetId} className="flex flex-wrap items-center justify-between gap-2">
                      <div>
                        <Link to={`/datasets/${candidate.datasetId}`} className="font-semibold text-slate-800">
                          {candidate.name}
                        </Link>
                        <p className="text-xs text-slate-500">{candidate.datasetId}</p>
                      </div>
                      <Badge variant="outline">{candidate.similarityScore}%</Badge>
                    </div>
                  ))
                ) : (
                  <p>No candidates selected yet.</p>
                )}
              </CardContent>
            </Card>
          ) : null}
        </div>

        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Target metadata</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm text-slate-600">
              {targetDataset ? (
                <>
                  <div>
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">PID</p>
                    <p className="font-semibold text-slate-800">{targetDataset.pid}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Strain / Sex / Age</p>
                    <p className="font-semibold text-slate-800">
                      {targetDataset.strain} · {targetDataset.sex} · {targetDataset.ageWeeks}w
                    </p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Environment</p>
                    <p className="font-semibold text-slate-800">{targetDataset.environment}</p>
                    <p>{targetDataset.sanitaryStatus}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Bedding</p>
                    <p className="font-semibold text-slate-800">{targetDataset.beddingMaterial}</p>
                    <p>{targetDataset.beddingBatch}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Device + Pipeline</p>
                    <p className="font-semibold text-slate-800">{targetDataset.device}</p>
                    <p>{targetDataset.pipelineVersion}</p>
                  </div>
                </>
              ) : (
                <p>No target dataset selected.</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Similarity rationale</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm text-slate-600">
              {selection ? (
                <>
                  <p>{selection.explanation}</p>
                  <div className="mt-3 space-y-2 text-xs text-slate-500">
                    {Object.entries(selection.weighting).map(([key, value]) => (
                      <div key={key} className="flex items-center justify-between">
                        <span className="capitalize">{key.replace(/([A-Z])/g, ' $1')}</span>
                        <span>{Math.round(value * 100)}%</span>
                      </div>
                    ))}
                  </div>
                  <p className="text-xs text-slate-400">Generated {formatDateTime(selection.generatedAt)}</p>
                </>
              ) : (
                <p>Run VCG to see weighting and confounders.</p>
              )}
            </CardContent>
          </Card>

          {exportEnabled ? (
            <Card>
              <CardHeader>
                <CardTitle>FAIR export</CardTitle>
                <CardDescription>Bundle datasets, metadata, and provenance.</CardDescription>
              </CardHeader>
              <CardContent>
                <Button
                  variant="outline"
                  disabled={!selection}
                  onClick={handleExport}
                  className="w-full"
                >
                  <DownloadCloud className="h-4 w-4" />
                  Export VCG bundle
                </Button>
              </CardContent>
            </Card>
          ) : null}
        </div>
      </div>
    </div>
  )
}
