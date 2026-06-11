import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { getSamples } from './sample.api.ts'
import type { SampleFilters } from './sample.types.ts'
import type { Sample } from '@/domain/resources.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'
import { StatCard } from '@/components/StatCard.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { formatDate } from '@/lib/format.ts'
import { exportCsv } from '@/lib/export.ts'

const PAGE_SIZE = 8

function SortHead({
  label,
  keyName,
  sortBy,
  sortDirection,
  onSort,
}: {
  label: string
  keyName: keyof Sample
  sortBy: keyof Sample
  sortDirection: 'asc' | 'desc'
  onSort: (key: keyof Sample) => void
}) {
  return (
    <TableHead>
      <button type="button" className="inline-flex items-center gap-1 font-semibold" onClick={() => onSort(keyName)}>
        {label}
        <span className="text-xs text-slate-400">{sortBy === keyName ? sortDirection : 'sort'}</span>
      </button>
    </TableHead>
  )
}

const statusOptions = ['collected', 'processing', 'stored', 'consumed'] as const
const typeOptions = ['body-weight', 'blood', 'tissue', 'saliva', 'plasma', 'cell-line', 'stool', 'csf', 'wastewater'] as const
const storageOptions = ['Metadatapp registry', 'Cryo A1', 'Cryo B2', 'Freezer C', 'Shelf 4'] as const

export function SamplesPage() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [type, setType] = useState('')
  const [storage, setStorage] = useState('')
  const [sortBy, setSortBy] = useState<keyof Sample>('collectedAt')
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc')
  const [selectedIds, setSelectedIds] = useState<string[]>([])
  const queryClient = useQueryClient()
  const { toast } = useToast()

  const filters = useMemo<SampleFilters>(
    () => ({ page, pageSize: PAGE_SIZE, search, status, type, storage, sortBy, sortDirection }),
    [page, search, status, type, storage, sortBy, sortDirection]
  )

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['samples', filters],
    queryFn: () => getSamples(filters),
  })

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setSelectedIds([])
  }, [page, search, status, type, storage])

  const toggleSort = (key: keyof Sample) => {
    if (sortBy === key) {
      setSortDirection((current) => current === 'asc' ? 'desc' : 'asc')
      return
    }
    setSortBy(key)
    setSortDirection('asc')
    setPage(1)
  }

  const totalPages = data ? Math.ceil(data.total / data.pageSize) : 1
  const storedCount = data?.data.filter((sample) => sample.status === 'stored').length ?? 0
  const qcReviewCount = data?.data.filter((sample) => sample.qcStatus !== 'pass').length ?? 0
  const recentCollected = data?.data.filter((sample) => sample.status === 'collected').length ?? 0

  const statusVariant = (value: string) => {
    if (value === 'stored') return 'success'
    if (value === 'processing') return 'warning'
    return 'outline'
  }

  const qcVariant = (value: string) => {
    if (value === 'pass') return 'success'
    if (value === 'review') return 'warning'
    return 'outline'
  }

  const toggleAll = () => {
    if (!data) return
    if (selectedIds.length === data.data.length) {
      setSelectedIds([])
      return
    }
    setSelectedIds(data.data.map((sample) => sample.id))
  }

  const toggleOne = (id: string) => {
    setSelectedIds((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]))
  }

  const handleBulk = (action: string) => {
    toast({
      title: `Bulk action: ${action}`,
      description: `${selectedIds.length} samples queued in the workflow.`,
    })
    setSelectedIds([])
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Samples"
        subtitle="Specimen registry with chain-of-custody, storage locations, and QC status."
        actions={
          <>
            <Button asChild>
              <Link to="/metadata/weight_measurements/new">New sample</Link>
            </Button>
            <Button
              variant="outline"
              onClick={() => {
                setSearch('')
                setStatus('')
                setType('')
                setStorage('')
                setSortBy('collectedAt')
                setSortDirection('desc')
                setPage(1)
              }}
            >
              Reset filters
            </Button>
            <Button
              variant="outline"
              onClick={() => exportCsv(data?.data ?? [], 'samples')}
              disabled={!data?.data.length}
            >
              Export CSV
            </Button>
          </>
        }
      />

      <div className="grid gap-4 md:grid-cols-3">
        <StatCard title="Stored" value={`${storedCount}`} helper="Cold storage inventory" />
        <StatCard title="QC review" value={`${qcReviewCount}`} helper="Needs attention" />
        <StatCard title="Recent collection" value={`${recentCollected}`} helper="Last 24 hours" />
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
              placeholder="Search by label, subject, investigation"
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
            value={type}
            onChange={(event) => {
              setType(event.target.value)
              setPage(1)
            }}
          >
            <option value="">All types</option>
            {typeOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={storage}
            onChange={(event) => {
              setStorage(event.target.value)
              setPage(1)
            }}
          >
            <option value="">All storage</option>
            {storageOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        </div>

        {selectedIds.length > 0 ? (
          <div className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-line bg-surface-2 px-4 py-3 text-sm">
            <span>{selectedIds.length} selected</span>
            <div className="flex flex-wrap items-center gap-2">
              <Button size="sm" onClick={() => handleBulk('Queue QC')}>
                Queue QC
              </Button>
              <Button size="sm" variant="outline" onClick={() => handleBulk('Move storage')}>
                Move storage
              </Button>
              <Button size="sm" variant="ghost" onClick={() => handleBulk('Export manifest')}>
                Export manifest
              </Button>
            </div>
          </div>
        ) : null}

        {isLoading ? (
          <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, index) => (
              <Skeleton key={index} className="h-12 w-full" />
            ))}
          </div>
        ) : isError ? (
          <EmptyState
            title="Sample feed unavailable"
            description={(error as Error).message}
            actionLabel="Retry"
            onAction={() => queryClient.invalidateQueries({ queryKey: ['samples'] })}
          />
        ) : data && data.data.length === 0 ? (
          <EmptyState title="No samples found" description="Adjust filters to find specimens." />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>
                  <input
                    type="checkbox"
                    className="h-4 w-4 rounded border-line"
                    checked={data?.data.length ? selectedIds.length === data.data.length : false}
                    onChange={toggleAll}
                  />
                </TableHead>
                <SortHead sortBy={sortBy} sortDirection={sortDirection} onSort={toggleSort} label="Sample" keyName="label" />
                <SortHead sortBy={sortBy} sortDirection={sortDirection} onSort={toggleSort} label="Subject" keyName="subjectLabel" />
                <SortHead sortBy={sortBy} sortDirection={sortDirection} onSort={toggleSort} label="Investigation" keyName="investigationName" />
                <SortHead sortBy={sortBy} sortDirection={sortDirection} onSort={toggleSort} label="Type" keyName="type" />
                <SortHead sortBy={sortBy} sortDirection={sortDirection} onSort={toggleSort} label="Status" keyName="status" />
                <SortHead sortBy={sortBy} sortDirection={sortDirection} onSort={toggleSort} label="QC" keyName="qcStatus" />
                <SortHead sortBy={sortBy} sortDirection={sortDirection} onSort={toggleSort} label="Storage" keyName="storageLocation" />
                <SortHead sortBy={sortBy} sortDirection={sortDirection} onSort={toggleSort} label="Collected" keyName="collectedAt" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.data.map((sample) => (
                <TableRow key={sample.id}>
                  <TableCell>
                    <input
                      type="checkbox"
                      className="h-4 w-4 rounded border-line"
                      checked={selectedIds.includes(sample.id)}
                      onChange={() => toggleOne(sample.id)}
                    />
                  </TableCell>
                  <TableCell>
                    <Link to={`/samples/${sample.id}`} className="font-medium text-slate-900">
                      {sample.label}
                    </Link>
                    <p className="text-xs text-slate-500">{sample.id}</p>
                  </TableCell>
                  <TableCell>
                    <p className="font-medium text-slate-700">{sample.subjectLabel}</p>
                    <p className="text-xs text-slate-400">{sample.subjectId}</p>
                  </TableCell>
                  <TableCell>{sample.investigationName}</TableCell>
                  <TableCell className="capitalize">{sample.type}</TableCell>
                  <TableCell>
                    <Badge variant={statusVariant(sample.status)}>{sample.status}</Badge>
                  </TableCell>
                  <TableCell>
                    <Badge variant={qcVariant(sample.qcStatus)}>{sample.qcStatus}</Badge>
                  </TableCell>
                  <TableCell>{sample.storageLocation}</TableCell>
                  <TableCell>{formatDate(sample.collectedAt)}</TableCell>
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
