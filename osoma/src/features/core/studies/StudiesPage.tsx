import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { getStudies } from './study.api.ts'
import type { StudyFilters } from './study.types.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'
import { StatCard } from '@/components/StatCard.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { ElabftwSyncBadge } from '@/components/ElabftwSyncBadge.tsx'
import { formatDate, formatNumber } from '@/lib/format.ts'
import { exportCsv } from '@/lib/export.ts'

const PAGE_SIZE = 7

const statusOptions = ['draft', 'running', 'paused', 'completed', 'failed'] as const
const qcOptions = ['pass', 'review', 'fail'] as const
const investigationOptions = [
  'Microbiome Resilience Atlas',
  'Neuroimmune Synapse Map',
  'Rare Disease Variant Bank',
  'Wastewater Pathogen Sentinel',
  'Greenhouse Stress Transcriptome',
  'Metabolomics Kinetic Panel',
]

export function StudiesPage() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [investigation, setInvestigation] = useState('')
  const [qcStatus, setQcStatus] = useState('')
  const queryClient = useQueryClient()

  const filters = useMemo<StudyFilters>(
    () => ({ page, pageSize: PAGE_SIZE, search, status, investigation, qcStatus }),
    [page, search, status, investigation, qcStatus]
  )

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['studies', filters],
    queryFn: () => getStudies(filters),
  })

  const totalPages = data ? Math.ceil(data.total / data.pageSize) : 1
  const runningCount = data?.data.filter((study) => study.status === 'running').length ?? 0
  const qcFlagged = data?.data.filter((study) => study.qcStatus !== 'pass').length ?? 0
  const dataVolume = data?.data.reduce((sum, study) => sum + study.dataVolumeGb, 0) ?? 0

  const statusVariant = (value: string) => {
    if (value === 'running') return 'success'
    if (value === 'paused') return 'warning'
    if (value === 'failed') return 'outline'
    return 'default'
  }

  const qcVariant = (value: string) => {
    if (value === 'pass') return 'success'
    if (value === 'review') return 'warning'
    return 'outline'
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Studies"
        subtitle="Live workflows with protocol lineage, QC checkpoints, and data volume tracking."
        actions={
          <>
            <Button asChild>
              <Link to="/metadata/experiments/new">New study</Link>
            </Button>
            <Button
              variant="outline"
              onClick={() => {
                setSearch('')
                setStatus('')
                setInvestigation('')
                setQcStatus('')
                setPage(1)
              }}
            >
              Reset filters
            </Button>
            <Button
              variant="outline"
              onClick={() => exportCsv(data?.data ?? [], 'studies')}
              disabled={!data?.data.length}
            >
              Export CSV
            </Button>
          </>
        }
      />

      <div className="grid gap-4 md:grid-cols-3">
        <StatCard title="Running now" value={`${runningCount}`} helper="Active workflows" />
        <StatCard title="QC attention" value={`${qcFlagged}`} helper="Needs review" />
        <StatCard title="Data volume" value={`${formatNumber(dataVolume)} GB`} helper="Current page total" />
      </div>

      <Card className="space-y-6">
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex w-full items-center gap-2 rounded-full border border-line bg-surface px-3 py-2 text-sm text-slate-500 md:max-w-xs">
            <Search className="h-4 w-4" />
            <Input
              value={search}
              onChange={(event) => {
                setSearch(event.target.value)
                setPage(1)
              }}
              placeholder="Search study, protocol, investigation"
              className="h-7 border-0 bg-transparent p-0 text-sm focus-visible:ring-0"
            />
          </div>
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={status}
            onChange={(event) => {
              setStatus(event.target.value)
              setPage(1)
            }}
          >
            <option value="">All statuses</option>
            {statusOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={investigation}
            onChange={(event) => {
              setInvestigation(event.target.value)
              setPage(1)
            }}
          >
            <option value="">All investigations</option>
            {investigationOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={qcStatus}
            onChange={(event) => {
              setQcStatus(event.target.value)
              setPage(1)
            }}
          >
            <option value="">All QC</option>
            {qcOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        </div>

        {isLoading ? (
          <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, index) => (
              <Skeleton key={index} className="h-12 w-full" />
            ))}
          </div>
        ) : isError ? (
          <EmptyState
            title="Study feed unavailable"
            description={(error as Error).message}
            actionLabel="Retry"
            onAction={() => queryClient.invalidateQueries({ queryKey: ['studies'] })}
          />
        ) : data && data.data?.length === 0 ? (
          <EmptyState
            title="No studies found"
            description="Adjust filters or launch a new protocol run."
          />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Study</TableHead>
                <TableHead>Investigation</TableHead>
                <TableHead>Protocol</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>QC</TableHead>
                <TableHead>Data volume</TableHead>
                <TableHead>Updated</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.data?.map((study) => (
                <TableRow key={study.id}>
                  <TableCell>
                    <Link to={`/studies/${study.id}`} className="font-medium text-slate-900">
                      {study.name}
                    </Link>
                    <div className="mt-1 flex flex-wrap items-center gap-2">
                      <p className="text-xs text-slate-500">{study.id}</p>
                      {study.elaftwExternalId ? <ElabftwSyncBadge elaftwExternalId={study.elaftwExternalId} /> : null}
                    </div>
                  </TableCell>
                  <TableCell>
                    <p className="font-medium text-slate-700">{study.investigationName}</p>
                    <p className="text-xs text-slate-400">{study.investigationId}</p>
                  </TableCell>
                  <TableCell>{study.assayName}</TableCell>
                  <TableCell>
                    <Badge variant={statusVariant(study.status)}>{study.status}</Badge>
                  </TableCell>
                  <TableCell>
                    <Badge variant={qcVariant(study.qcStatus)}>{study.qcStatus}</Badge>
                  </TableCell>
                  <TableCell>{formatNumber(study.dataVolumeGb)} GB</TableCell>
                  <TableCell>{formatDate(study.updatedAt)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}

        <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
          <span>
            Page {page} of {totalPages}
          </span>
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setPage((prev) => Math.max(1, prev - 1))}
              disabled={page === 1}
            >
              Previous
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setPage((prev) => Math.min(totalPages, prev + 1))}
              disabled={page === totalPages}
            >
              Next
            </Button>
          </div>
        </div>
      </Card>
    </div>
  )
}
