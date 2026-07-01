import { Link } from 'react-router-dom'
import { useMemo, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import { downloadZefishBatchHistoryCsv, getZefishBatches } from '@/domain/zefish/zefish.api.ts'
import { formatDate } from '@/lib/format.ts'
import { useToast } from '@/components/ui/toast.tsx'

const formatSexRatio = (male: number, female: number, unknown: number) => `M ${male} / F ${female} / U ${unknown}`

export function BatchesPage() {
    const [search, setSearch] = useState('')
    const { role } = useRole()
    const allowEdit = canEdit(role)
    const { toast } = useToast()

    const batchesQuery = useQuery({
        queryKey: ['zefix', 'batches', search],
        queryFn: () => getZefishBatches(search),
    })
    const exportMutation = useMutation({
        mutationFn: () => downloadZefishBatchHistoryCsv(search),
        onSuccess: () => {
            toast({ title: 'Export ready', description: 'Batch history CSV downloaded.' })
        },
        onError: (error: Error) => {
            toast({ title: 'Export failed', description: error.message, variant: 'error' })
        },
    })

    const batches = useMemo(() => batchesQuery.data ?? [], [batchesQuery.data])

    return (
        <div className="space-y-6">
            <PageHeader
                title="Batch / Lot Management"
                subtitle="Track breeding batches, location changes, and mortality events with full timeline traceability."
                actions={
                    allowEdit ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => exportMutation.mutate()}
                        disabled={exportMutation.isPending}
                    >
                        {exportMutation.isPending ? 'Exporting…' : 'Export CSV'}
                    </Button>
                    ) : undefined
                }
            />

            <Card className="space-y-4">
                <div className="flex w-full items-center gap-2 rounded-full border border-line bg-surface px-3 py-2 text-sm text-slate-500 md:max-w-sm">
                    <Search className="h-4 w-4" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search batch, line, room, system"
                        className="h-7 border-0 bg-transparent p-0 text-sm focus-visible:ring-0"
                    />
                </div>

                {batchesQuery.isLoading ? (
                    <div className="space-y-2">
                        {Array.from({ length: 7 }).map((_, index) => (
                            <Skeleton key={index} className="h-12 w-full" />
                        ))}
                    </div>
                ) : batchesQuery.isError ? (
                    <EmptyState title="Batches unavailable" description={batchesQuery.error?.message ?? 'Unable to load batches.'} />
                ) : !batches.length ? (
                    <EmptyState title="No batches found" description="No batch matches the current filters." />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Batch</TableHead>
                                <TableHead>Line</TableHead>
                                <TableHead>Birth date</TableHead>
                                <TableHead>Age</TableHead>
                                <TableHead>Sex ratio</TableHead>
                                <TableHead>Individuals</TableHead>
                                <TableHead>System</TableHead>
                                <TableHead>Room</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {batches.map((batch) => (
                                <TableRow key={batch.batchID}>
                                    <TableCell className="font-medium text-slate-900">
                                        <Link to={`/zefix/batches/${batch.batchID}`} className="hover:underline">
                                            {batch.batchID}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{batch.lineName}</TableCell>
                                    <TableCell>{formatDate(batch.birthDate)}</TableCell>
                                    <TableCell>{batch.ageLabel}</TableCell>
                                    <TableCell>
                                        {formatSexRatio(batch.sexRatio.male, batch.sexRatio.female, batch.sexRatio.unknown)}
                                    </TableCell>
                                    <TableCell>{batch.individualCount.toLocaleString()}</TableCell>
                                    <TableCell>{batch.systemID}</TableCell>
                                    <TableCell>{batch.roomName}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>
        </div>
    )
}
