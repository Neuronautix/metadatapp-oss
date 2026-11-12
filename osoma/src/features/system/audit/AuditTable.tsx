import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import type { AuditLogEntry } from '@/domain/audit.ts'
import { formatDateTime } from '@/lib/format.ts'

export function AuditTable({
  entries,
  isLoading,
  isError,
  errorMessage,
  onRetry,
}: {
  entries: AuditLogEntry[]
  isLoading: boolean
  isError: boolean
  errorMessage?: string
  onRetry: () => void
}) {
  if (isLoading) {
    return (
      <div className="space-y-3">
        {Array.from({ length: 6 }).map((_, index) => (
          <Skeleton key={index} className="h-12 w-full" />
        ))}
      </div>
    )
  }

  if (isError) {
    return (
      <EmptyState
        title="Audit log unavailable"
        description={errorMessage ?? 'Unable to load audit entries.'}
        actionLabel="Retry"
        onAction={onRetry}
      />
    )
  }

  if (!entries.length) {
    return <EmptyState title="No audit entries" description="No audit events match the current filters." />
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Time</TableHead>
          <TableHead>Actor</TableHead>
          <TableHead>Action</TableHead>
          <TableHead>Resource</TableHead>
          <TableHead>Summary</TableHead>
          <TableHead>Correlation</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {entries.map((entry) => (
          <TableRow key={entry.id}>
            <TableCell>{formatDateTime(entry.at)}</TableCell>
            <TableCell>{entry.actor}</TableCell>
            <TableCell className="capitalize">{entry.action.replace('.', ' ')}</TableCell>
            <TableCell className="uppercase text-xs text-slate-500">
              {entry.resourceType} {entry.resourceId}
            </TableCell>
            <TableCell className="text-slate-700">{entry.summary}</TableCell>
            <TableCell className="text-xs text-slate-500">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => navigator.clipboard.writeText(entry.correlationId)}
              >
                {entry.correlationId}
              </Button>
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
