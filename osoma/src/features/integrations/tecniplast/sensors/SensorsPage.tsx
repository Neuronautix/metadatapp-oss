import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { getSensors } from './sensors.api.ts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Button } from '@/components/ui/button.tsx'
import { LiveSensorPanel } from '@/features/demo-sensors/LiveSensorPanel.tsx'
import { formatDateTime } from '@/lib/format.ts'

const PAGE_SIZE = 10

export function SensorsPage() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['tp-sensors'],
    queryFn: () => getSensors(),
  })

  const filtered = useMemo(() => {
    let items = Array.isArray(data) ? data : []
    if (search) {
      items = items.filter((sensor) =>
        [sensor.id, sensor.scopeLabel, sensor.metric].some((value) =>
          String(value).toLowerCase().includes(search.toLowerCase())
        )
      )
    }
    if (status) {
      items = items.filter((sensor) => sensor.status === status)
    }
    return items
  }, [data, search, status])

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE))
  const pageItems = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)

    return (
      <div className="space-y-6">
        <PageHeader
          title="Sensors"
          subtitle="Live sensor health across rooms, racks, and cages."
        actions={
          <Button
            variant="outline"
            onClick={() => {
              setSearch('')
              setStatus('')
              setPage(1)
            }}
          >
            Reset filters
          </Button>
        }
        />

        <LiveSensorPanel
          title="Sovereign Sensor Demo"
          subtitle="Metadatapp proxy for the Raspberry Pi temperature, humidity, and pressure feed."
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
              placeholder="Search sensors"
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
            <option value="">All status</option>
            <option value="online">online</option>
            <option value="offline">offline</option>
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
            title="Sensors unavailable"
            description={(error as Error)?.message}
            actionLabel="Retry"
            onAction={() => refetch()}
          />
        ) : pageItems.length === 0 ? (
          <EmptyState title="No sensors" description="No sensors match the current filters." />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Sensor</TableHead>
                <TableHead>Scope</TableHead>
                <TableHead>Metric</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Last seen</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {pageItems.map((sensor) => (
                <TableRow key={sensor.id}>
                  <TableCell>
                    <Link to={`/tp/sensors/${sensor.id}`} className="font-medium text-slate-900">
                      {sensor.id}
                    </Link>
                  </TableCell>
                  <TableCell>{sensor.scopeLabel}</TableCell>
                  <TableCell>{sensor.metric}</TableCell>
                  <TableCell>{sensor.status}</TableCell>
                  <TableCell>{sensor.lastSeen ? formatDateTime(sensor.lastSeen) : 'Not reported'}</TableCell>
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
