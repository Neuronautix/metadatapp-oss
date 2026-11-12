import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { getUsers, type UsersFilters } from './user.api.ts'
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
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import { summarizeAccessAreas } from '@/lib/user-access.ts'

const PAGE_SIZE = 7

const statusOptions = ['active', 'invited', 'suspended'] as const
const accessOptions = ['viewer', 'editor', 'admin'] as const

export function UsersPage() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [accessLevel, setAccessLevel] = useState('')
  const queryClient = useQueryClient()
  const { role } = useRole()
  const allowEdit = canEdit(role)

  const filters = useMemo<UsersFilters>(
    () => ({ page, pageSize: PAGE_SIZE, search, status, accessLevel }),
    [page, search, status, accessLevel]
  )

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['users', filters],
    queryFn: () => getUsers(filters),
  })

  const totalPages = data ? Math.ceil(data.total / data.pageSize) : 1

  const statusVariant = (value: string) => {
    if (value === 'active') return 'success'
    if (value === 'invited') return 'warning'
    return 'outline'
  }

  const accessVariant = (value: string) => {
    if (value === 'admin') return 'default'
    if (value === 'editor') return 'warning'
    return 'outline'
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Users"
        subtitle="Lab operators with access levels, audit context, and last activity. Access scope is summarized per profile."
        actions={
          <>
            {allowEdit ? (
              <Button asChild>
                <Link to="/users/new">New user</Link>
              </Button>
            ) : null}
            <Button
              variant="outline"
              onClick={() => {
                setSearch('')
                setStatus('')
                setAccessLevel('')
                setPage(1)
              }}
            >
              Reset filters
            </Button>
            <Button
              variant="outline"
              onClick={() => exportCsv(data?.data ?? [], 'users')}
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
              placeholder="Search by name, role, lab"
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
            value={accessLevel}
            onChange={(event) => {
              setAccessLevel(event.target.value)
              setPage(1)
            }}
          >
            <option value="">All access</option>
            {accessOptions.map((option) => (
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
            title="User directory unavailable"
            description={(error as Error).message}
            actionLabel="Retry"
            onAction={() => queryClient.invalidateQueries({ queryKey: ['users'] })}
          />
        ) : data && data.data.length === 0 ? (
          <EmptyState title="No users found" description="Adjust filters to locate staff." />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>User</TableHead>
                <TableHead>Role</TableHead>
                <TableHead>Lab</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Access</TableHead>
                <TableHead>Scope</TableHead>
                <TableHead>Last active</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.data.map((user) => (
                <TableRow key={user.id}>
                  <TableCell>
                    <Link to={`/users/${user.id}`} className="font-medium text-slate-900">
                      {user.name}
                    </Link>
                    <p className="text-xs text-slate-500">{user.id}</p>
                  </TableCell>
                  <TableCell>{user.role}</TableCell>
                  <TableCell>{user.lab}</TableCell>
                  <TableCell>
                    <Badge variant={statusVariant(user.status)}>{user.status}</Badge>
                  </TableCell>
                  <TableCell>
                    <Badge variant={accessVariant(user.accessLevel)}>{user.accessLevel}</Badge>
                  </TableCell>
                  <TableCell className="text-sm text-slate-500">
                    {summarizeAccessAreas(user.accessAreas, user.accessLevel)}
                  </TableCell>
                  <TableCell>{formatDate(user.lastActive)}</TableCell>
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
