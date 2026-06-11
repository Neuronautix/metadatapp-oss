import { Link } from 'react-router-dom'
import { useMemo, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import { downloadZefishLineStatisticsCsv, getZefishLines } from '@/domain/zefish/zefish.api.ts'
import { asLineStatusVariant } from '@/domain/zefish/zefish.utils.ts'
import { useToast } from '@/components/ui/toast.tsx'

export function LinesPage() {
    const [search, setSearch] = useState('')
    const { role } = useRole()
    const allowEdit = canEdit(role)
    const { toast } = useToast()

    const linesQuery = useQuery({
        queryKey: ['zefix', 'lines', search],
        queryFn: () => getZefishLines(search),
    })
    const exportMutation = useMutation({
        mutationFn: () => downloadZefishLineStatisticsCsv(search),
        onSuccess: () => {
            toast({ title: 'Export ready', description: 'Line statistics CSV downloaded.' })
        },
        onError: (error: Error) => {
            toast({ title: 'Export failed', description: error.message, variant: 'error' })
        },
    })

    const lines = useMemo(() => linesQuery.data ?? [], [linesQuery.data])

    return (
        <div className="space-y-6">
            <PageHeader
                title="Line Management"
                subtitle="Manage zebrafish genetic lines with live indicators and active batches."
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
                        placeholder="Search line, genotype, scientist"
                        className="h-7 border-0 bg-transparent p-0 text-sm focus-visible:ring-0"
                    />
                </div>

                {linesQuery.isLoading ? (
                    <div className="space-y-2">
                        {Array.from({ length: 6 }).map((_, index) => (
                            <Skeleton key={index} className="h-12 w-full" />
                        ))}
                    </div>
                ) : linesQuery.isError ? (
                    <EmptyState title="Lines unavailable" description={linesQuery.error?.message ?? 'Unable to load lines.'} />
                ) : !lines.length ? (
                    <EmptyState title="No lines found" description="No line matches your search criteria." />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Line</TableHead>
                                <TableHead>Genotype</TableHead>
                                <TableHead>Active Batches</TableHead>
                                <TableHead>Fish Count</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {lines.map((line) => (
                                <TableRow key={line.lineID}>
                                    <TableCell className="font-medium text-slate-900">
                                        <Link to={`/zefix/lines/${line.lineID}`} className="hover:underline">
                                            {line.name}
                                        </Link>
                                        <p className="text-xs text-slate-500">{line.lineID}</p>
                                    </TableCell>
                                    <TableCell>{line.genotype}</TableCell>
                                    <TableCell>{line.activeBatches}</TableCell>
                                    <TableCell>{line.totalAnimals.toLocaleString()}</TableCell>
                                    <TableCell>
                                        <Badge variant={asLineStatusVariant(line.reproductionStatus)}>
                                            {line.reproductionStatus}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>
        </div>
    )
}
