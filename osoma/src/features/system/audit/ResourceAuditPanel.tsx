import { useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import type { AuditAction, AuditResourceType } from '@/domain/audit.ts'
import { getAuditEntries } from './audit.api.ts'
import { AuditTable } from './AuditTable.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'

const PAGE_SIZE = 6

const actionOptions: AuditAction[] = ['created', 'updated', 'linked', 'unlinked', 'status.changed', 'exported', 'scheduled', 'access.changed']

export function ResourceAuditPanel({ resourceType, resourceId }: { resourceType: AuditResourceType; resourceId: string }) {
  const [page, setPage] = useState(1)
  const [actor, setActor] = useState('')
  const [action, setAction] = useState<AuditAction | ''>('')
  const queryClient = useQueryClient()

  const filters = useMemo(
    () => ({ page, pageSize: PAGE_SIZE, actor, action, resourceType, resourceId }),
    [page, actor, action, resourceId, resourceType]
  )

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['audit', resourceType, resourceId, filters],
    queryFn: () => getAuditEntries(filters),
  })

  const totalPages = data ? Math.ceil(data.total / data.pageSize) : 1

  return (
    <Card className="space-y-5">
      <div className="flex flex-wrap items-center gap-3">
        <input
          className="h-9 rounded-full border border-line bg-white px-3 text-sm text-slate-600"
          placeholder="Filter by actor"
          value={actor}
          onChange={(event) => {
            setActor(event.target.value)
            setPage(1)
          }}
        />
        <select
          className="h-9 rounded-full border border-line bg-white px-3 text-sm text-slate-600"
          value={action}
          onChange={(event) => {
            setAction(event.target.value as AuditAction | '')
            setPage(1)
          }}
        >
          <option value="">All actions</option>
          {actionOptions.map((option) => (
            <option key={option} value={option}>
              {option.replace('.', ' ')}
            </option>
          ))}
        </select>
      </div>

      <AuditTable
        entries={data?.data ?? []}
        isLoading={isLoading}
        isError={isError}
        errorMessage={(error as Error)?.message}
        onRetry={() => queryClient.invalidateQueries({ queryKey: ['audit', resourceType, resourceId] })}
      />

      <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
        <span>
          Page {page} of {totalPages}
        </span>
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" onClick={() => setPage((prev) => Math.max(1, prev - 1))} disabled={page === 1}>
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
  )
}
