import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { getDatasets, type DatasetFilters } from './dataset.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { formatDate, formatNumber } from '@/lib/format.ts'
import { exportCsv } from '@/lib/export.ts'
import { fairVariantFromScore } from '@/lib/fair.ts'

const PAGE_SIZE = 7

const statusOptions = ['draft', 'published', 'restricted'] as const
const formatOptions = ['FASTQ', 'CSV', 'HDF5', 'JSON-LD'] as const

export function DatasetsPage() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [format, setFormat] = useState('')
  const queryClient = useQueryClient()

  const filters = useMemo<DatasetFilters>(
    () => ({ page, pageSize: PAGE_SIZE, search, status, format }),
    [page, search, status, format]
  )

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['datasets', filters],
    queryFn: () => getDatasets(filters),
  })

  const totalPages = data ? Math.ceil(data.total / data.pageSize) : 1

  const statusVariant = (value: string) => {
    if (value === 'published') return 'success'
    if (value === 'restricted') return 'warning'
    return 'outline'
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Datasets"
        subtitle="Reusable data assets with FAIR scoring and release gates."
        actions={
          <>
            <Button disabled title="Dataset create is not available in the MAPP registry">
              New dataset unavailable
            </Button>
            <Button
              variant="outline"
              onClick={() => {
                setSearch('')
                setStatus('')
                setFormat('')
                setPage(1)
              }}
            >
              Reset filters
            </Button>
            <Button
              variant="outline"
              onClick={() => exportCsv(data?.data ?? [], 'datasets')}
              disabled={!data?.data.length}
            >
              Export CSV
            </Button>
          </>
        }
      />

      <Card className="space-y-6">
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex w-full items-center gap-2 rounded-full border border-line bg-surface px-3 py-2 text-sm text-muted md:max-w-xs">
            <Search className="h-4 w-4" />
            <Input
              value={search}
              onChange={(event) => {
                setSearch(event.target.value)
                setPage(1)
              }}
              placeholder="Search by dataset or investigation"
              className="h-7 border-0 bg-transparent p-0 text-sm focus-visible:ring-0"
            />
          </div>
          <select
            className="rounded-full border border-line bg-surface px-3 py-2 text-sm text-ink"
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
            className="rounded-full border border-line bg-surface px-3 py-2 text-sm text-ink"
            value={format}
            onChange={(event) => {
              setFormat(event.target.value)
              setPage(1)
            }}
          >
            <option value="">All formats</option>
            {formatOptions.map((option) => (
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
            title="Dataset registry unavailable"
            description={(error as Error).message}
            actionLabel="Retry"
            onAction={() => queryClient.invalidateQueries({ queryKey: ['datasets'] })}
          />
        ) : data && data.data.length === 0 ? (
          <EmptyState title="No datasets found" description="Adjust filters to locate outputs." />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Dataset</TableHead>
                <TableHead>Investigation</TableHead>
                <TableHead>Format</TableHead>
                <TableHead>Size</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>FAIR</TableHead>
                <TableHead>Updated</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.data.map((dataset) => (
                <TableRow key={dataset.id}>
                  <TableCell>
                    <Link to={`/datasets/${dataset.id}`} className="font-medium text-ink">
                      {dataset.title}
                    </Link>
                    <p className="text-xs text-muted">{dataset.id}</p>
                  </TableCell>
                  <TableCell>{dataset.investigationName}</TableCell>
                  <TableCell>{dataset.format}</TableCell>
                  <TableCell>{formatNumber(dataset.sizeGb)} GB</TableCell>
                  <TableCell>
                    <Badge variant={statusVariant(dataset.status)}>{dataset.status}</Badge>
                  </TableCell>
                  <TableCell>
                    <Badge variant={fairVariantFromScore(dataset.fairScore.total)}>{dataset.fairScore.total}/100</Badge>
                  </TableCell>
                  <TableCell>{formatDate(dataset.updatedAt)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}

        <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted">
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
