import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Badge } from '@/components/ui/badge.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { formatDateTime } from '@/lib/format.ts'
import { getNamDatasets } from './dvc.api.ts'

export function FairNavigatorPage() {
  const datasetsQuery = useQuery({
    queryKey: ['nam-datasets'],
    queryFn: () => getNamDatasets(),
  })

  const datasets = datasetsQuery.data?.data ?? []

  const pidCoverage = useMemo(() => {
    if (!datasets.length) return 0
    return Math.round((datasets.filter((dataset) => dataset.pid).length / datasets.length) * 100)
  }, [datasets])

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
        title="FAIR navigator unavailable"
        description={(datasetsQuery.error as Error)?.message ?? 'Dataset feed missing.'}
        actionLabel="Retry"
        onAction={() => datasetsQuery.refetch()}
      />
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="FAIR Navigator"
        subtitle="Trace persistent identifiers, metadata context, and export readiness."
      />

      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>PID coverage</CardTitle>
            <CardDescription>Datasets with persistent identifiers.</CardDescription>
          </CardHeader>
          <CardContent className="text-3xl font-semibold text-slate-800">{pidCoverage}%</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Metadata axis</CardTitle>
            <CardDescription>Primary axis for DVC interpretation.</CardDescription>
          </CardHeader>
          <CardContent className="text-sm text-slate-600">
            Strain, sanitary status, environment, bedding, and pipeline are surfaced first.
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Export posture</CardTitle>
            <CardDescription>FAIR bundles ready for NAM-VCG.</CardDescription>
          </CardHeader>
          <CardContent className="text-sm text-slate-600">
            Provenance and context snapshots are maintained for every dataset.
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Dataset ledger</CardTitle>
          <CardDescription>Metadata-rich registry for DVC activity series.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {datasets.map((dataset) => (
            <div key={dataset.id} className="rounded-2xl border border-line bg-surface-2 p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-slate-800">{dataset.name}</p>
                  <p className="text-xs text-slate-500">{dataset.pid}</p>
                  <p className="mt-2 text-sm text-slate-600">{dataset.description}</p>
                </div>
                <Badge variant="outline">{dataset.id}</Badge>
              </div>
              <div className="mt-4 grid gap-3 text-sm text-slate-600 md:grid-cols-3">
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Strain / Sex / Age</p>
                  <p className="font-semibold text-slate-800">
                    {dataset.strain} · {dataset.sex} · {dataset.ageWeeks}w
                  </p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Environment</p>
                  <p className="font-semibold text-slate-800">{dataset.environment}</p>
                  <p>{dataset.sanitaryStatus}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Bedding</p>
                  <p className="font-semibold text-slate-800">{dataset.beddingMaterial}</p>
                  <p>{dataset.beddingBatch}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Facility</p>
                  <p className="font-semibold text-slate-800">{dataset.facility}</p>
                  <p>{dataset.room}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Device + Pipeline</p>
                  <p className="font-semibold text-slate-800">{dataset.device}</p>
                  <p>{dataset.pipelineVersion}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Created</p>
                  <p className="font-semibold text-slate-800">{formatDateTime(dataset.createdAt)}</p>
                </div>
              </div>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  )
}
