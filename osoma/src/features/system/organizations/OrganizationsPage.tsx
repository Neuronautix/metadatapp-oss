import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { getOrganizations, type OrganizationFilters } from './organization.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { formatDate } from '@/lib/format.ts'
import { exportCsv } from '@/lib/export.ts'

const PAGE_SIZE = 7

const typeOptions = ['university', 'biotech', 'hospital', 'consortium'] as const

export function OrganizationsPage() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')
  const queryClient = useQueryClient()

  const filters = useMemo<OrganizationFilters>(() => ({ page, pageSize: PAGE_SIZE, search, type }), [page, search, type])

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['organizations', filters],
    queryFn: () => getOrganizations(filters),
  })

  const totalPages = data ? Math.ceil(data.total / data.pageSize) : 1

  const typeVariant = (value: string) => {
    if (value === 'consortium') return 'warning'
    if (value === 'biotech') return 'default'
    return 'outline'
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Organizations"
        subtitle="Partner labs, hospitals, and consortia with active portfolios."
        actions={
          <>
            <Button
              variant="outline"
              onClick={() => {
                setSearch('')
                setType('')
                setPage(1)
              }}
            >
              Reset filters
            </Button>
            <Button
              variant="outline"
              onClick={() => exportCsv(data?.data ?? [], 'organizations')}
              disabled={!data?.data.length}
            >
              Export CSV
            </Button>
          </>
        }
      />

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
              placeholder="Search organizations"
              className="h-7 border-0 bg-transparent p-0 text-sm focus-visible:ring-0"
            />
          </div>
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
        </div>

        {isLoading ? (
          <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, index) => (
              <Skeleton key={index} className="h-12 w-full" />
            ))}
          </div>
        ) : isError ? (
          <EmptyState
            title="Organization registry unavailable"
            description={(error as Error).message}
            actionLabel="Retry"
            onAction={() => queryClient.invalidateQueries({ queryKey: ['organizations'] })}
          />
        ) : data && data.data.length === 0 ? (
          <EmptyState title="No organizations found" description="Adjust filters to locate partners." />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Organization</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Location</TableHead>
                <TableHead>Labs</TableHead>
                <TableHead>Active investigations</TableHead>
                <TableHead>Updated</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.data.map((org) => (
                <TableRow key={org.id}>
                  <TableCell>
                    <Link to={`/organizations/${org.id}`} className="font-medium text-slate-900">
                      {org.name}
                    </Link>
                    <p className="text-xs text-slate-500">{org.id}</p>
                  </TableCell>
                  <TableCell>
                    <Badge variant={typeVariant(org.type)}>{org.type}</Badge>
                  </TableCell>
                  <TableCell>{org.location}</TableCell>
                  <TableCell>{org.labs}</TableCell>
                  <TableCell>{org.activeInvestigations}</TableCell>
                  <TableCell>{formatDate(org.updatedAt)}</TableCell>
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
