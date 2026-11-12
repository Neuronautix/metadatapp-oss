import { useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import type { AuditAction, AuditResourceType } from '@/domain/audit.ts'
import { getAuditEntries } from './audit.api.ts'
import { AuditTable } from './AuditTable.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Feature } from '@/feature-flags/Feature.tsx'
import { FeatureFallback } from '@/feature-flags/FeatureFallback.tsx'
import { useNavigate } from 'react-router-dom'

const PAGE_SIZE = 10

const actionOptions: AuditAction[] = ['created', 'updated', 'linked', 'unlinked', 'status.changed', 'exported', 'scheduled', 'access.changed']
const resourceOptions: AuditResourceType[] = [
  'investigation',
  'study',
  'sample',
  'subject',
  'assay',
  'dataset',
  'user',
  'organization',
  'calendar',
  'facility',
  'room',
  'rack',
  'cage',
  'alarm',
  'sensor',
  'workorder',
]

export function AuditPage() {
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [actor, setActor] = useState('')
  const [action, setAction] = useState<AuditAction | ''>('')
  const [resourceType, setResourceType] = useState<AuditResourceType | ''>('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const queryClient = useQueryClient()

  const filters = useMemo(
    () => ({ page, pageSize: PAGE_SIZE, actor, action, resourceType, startDate, endDate }),
    [page, actor, action, resourceType, startDate, endDate]
  )

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['audit', filters],
    queryFn: () => getAuditEntries(filters),
  })

  const totalPages = data ? Math.ceil(data.total / data.pageSize) : 1

  return (
    <Feature
      flag="feature.auditLogs"
      fallback={
        <FeatureFallback
          title="Audit logs are disabled"
          description="Turn on the Audit Logs feature in the Feature Flag Studio to explore this workspace-wide trail."
          warning="Compliance"
          actionLabel="Open Flag Studio"
          onAction={() => navigate('/admin/feature-flags')}
        />
      }
    >
      <div className="space-y-6">
        <PageHeader
          title="Audit Log"
          subtitle="System-wide audit trail with correlation IDs and resource lineage."
          actions={
            <Button
              variant="outline"
              onClick={() => {
                setActor('')
                setAction('')
                setResourceType('')
                setStartDate('')
                setEndDate('')
                setPage(1)
              }}
            >
              Reset filters
            </Button>
          }
        />

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
            <select
              className="h-9 rounded-full border border-line bg-white px-3 text-sm text-slate-600"
              value={resourceType}
              onChange={(event) => {
                setResourceType(event.target.value as AuditResourceType | '')
                setPage(1)
              }}
            >
              <option value="">All resources</option>
              {resourceOptions.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
            <input
              className="h-9 rounded-full border border-line bg-white px-3 text-sm text-slate-600"
              type="date"
              value={startDate}
              onChange={(event) => {
                setStartDate(event.target.value)
                setPage(1)
              }}
            />
            <input
              className="h-9 rounded-full border border-line bg-white px-3 text-sm text-slate-600"
              type="date"
              value={endDate}
              onChange={(event) => {
                setEndDate(event.target.value)
                setPage(1)
              }}
            />
          </div>

          <AuditTable
            entries={data?.data ?? []}
            isLoading={isLoading}
            isError={isError}
            errorMessage={(error as Error)?.message}
            onRetry={() => queryClient.invalidateQueries({ queryKey: ['audit'] })}
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
      </div>
    </Feature>
  )
}
