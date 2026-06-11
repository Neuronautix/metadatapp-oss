import { CryoConservationSection } from '@/features/zefix/CryoConservationSection.tsx'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { DetailLayout } from '@/components/DetailLayout.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import { downloadZefishLineReportPdf, getZefishLineDetail } from '@/domain/zefish/zefish.api.ts'
import { asLineStatusVariant } from '@/domain/zefish/zefish.utils.ts'
import { formatDate } from '@/lib/format.ts'
import { useToast } from '@/components/ui/toast.tsx'

export function LineDetailPage() {
    const { lineID } = useParams()
    const resolvedLineID = lineID ?? ''
    const { role } = useRole()
    const allowEdit = canEdit(role)
    const { toast } = useToast()

    const lineQuery = useQuery({
        queryKey: ['zefix', 'lines', resolvedLineID, 'detail'],
        queryFn: () => getZefishLineDetail(resolvedLineID),
        enabled: Boolean(resolvedLineID),
    })
    const exportMutation = useMutation({
        mutationFn: () => downloadZefishLineReportPdf(resolvedLineID),
        onSuccess: () => {
            toast({ title: 'Report ready', description: 'Line PDF report downloaded.' })
        },
        onError: (error: Error) => {
            toast({ title: 'Export failed', description: error.message, variant: 'error' })
        },
    })

    if (lineQuery.isLoading) {
        return (
            <div className="space-y-4">
                <Skeleton className="h-10 w-64" />
                <Skeleton className="h-60 w-full" />
            </div>
        )
    }

    if (lineQuery.isError || !lineQuery.data) {
        return (
            <EmptyState
                title="Line unavailable"
                description={lineQuery.error?.message ?? 'Line details could not be loaded.'}
            />
        )
    }

    const detail = lineQuery.data

    return (
        <DetailLayout
            title={detail.line.name}
            subtitle={`${detail.line.lineID} · ${detail.line.genotype}`}
            backLink="/zefix/lines"
            backLabel="All lines"
            actions={
                allowEdit ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => exportMutation.mutate()}
                        disabled={exportMutation.isPending}
                    >
                        {exportMutation.isPending ? 'Exporting…' : 'Download PDF report'}
                    </Button>
                ) : undefined
            }
        >
            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardDescription>Total animals</CardDescription>
                            <CardTitle>{detail.line.totalAnimals.toLocaleString()}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Active batches</CardDescription>
                            <CardTitle>{detail.line.activeBatches}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Mortality rate</CardDescription>
                            <CardTitle>{detail.line.mortalityRate.toFixed(1)}%</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Reproduction status</CardDescription>
                            <CardTitle>
                                <Badge variant={asLineStatusVariant(detail.line.reproductionStatus)}>
                                    {detail.line.reproductionStatus}
                                </Badge>
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Line metadata</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm text-slate-600 md:grid-cols-2">
                        <p><span className="font-semibold text-slate-800">Origin:</span> {detail.line.origin}</p>
                        <p><span className="font-semibold text-slate-800">Created:</span> {formatDate(detail.line.creationDate)}</p>
                        <p><span className="font-semibold text-slate-800">Responsible scientist:</span> {detail.line.responsibleScientist}</p>
                        <p><span className="font-semibold text-slate-800">Description:</span> {detail.line.description}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Batches for this line</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {!detail.batches.length ? (
                            <EmptyState title="No batches" description="No batch is linked to this line yet." />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Batch</TableHead>
                                        <TableHead>Age</TableHead>
                                        <TableHead>Fish count</TableHead>
                                        <TableHead>Mortalities</TableHead>
                                        <TableHead>Location</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {detail.batches.map((batch) => (
                                        <TableRow key={batch.batchID}>
                                            <TableCell className="font-medium text-slate-900">
                                                <Link to={`/zefix/batches/${batch.batchID}`} className="hover:underline">
                                                    {batch.batchID}
                                                </Link>
                                            </TableCell>
                                            <TableCell>{batch.ageLabel}</TableCell>
                                            <TableCell>{batch.individualCount.toLocaleString()}</TableCell>
                                            <TableCell>{batch.mortalityCount}</TableCell>
                                            <TableCell className="max-w-xl truncate">{batch.tankLocation}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <CryoConservationSection lineID={detail.line.lineID} lineName={detail.line.name} />
            </div>
        </DetailLayout>
    )
}
