import { useMemo } from 'react'
import { Download, Plus } from 'lucide-react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { useRole } from '@/app/role-context.tsx'
import { RestrictedActions } from '@/components/RestrictedActions.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { exportCsv } from '@/lib/export.ts'
import { formatDate } from '@/lib/format.ts'
import { getDataMode } from '@/lib/mode.ts'
import { canEdit } from '@/lib/rbac.ts'
import { createZefishCryoRecord, downloadZefishCryoInventoryCsv, getZefishCryoRecords } from '@/domain/zefish/zefish.api.ts'
import type { ZefishCryoStatus } from '@/domain/zefish/zefish.types.ts'

const cryoFormSchema = z.object({
    storageDate: z.string().min(1, 'Storage date is required.'),
    storageLocation: z.string().min(1, 'Storage location is required.').max(120, 'Storage location must be 120 characters or less.'),
    protocolUsed: z.string().min(1, 'Protocol is required.').max(120, 'Protocol must be 120 characters or less.'),
    status: z.enum(['stored', 'used', 'discarded']),
    notes: z.string().max(500, 'Notes must be 500 characters or less.').optional().or(z.literal('')),
})

type CryoFormValues = z.infer<typeof cryoFormSchema>

const statusOptions: ZefishCryoStatus[] = ['stored', 'used', 'discarded']

const statusVariant: Record<ZefishCryoStatus, 'success' | 'default' | 'outline'> = {
    stored: 'success',
    used: 'default',
    discarded: 'outline',
}

const statusLabel = (status: ZefishCryoStatus) => {
    return status.charAt(0).toUpperCase() + status.slice(1)
}

const toLocalDateInputValue = (value = new Date()) => {
    const offsetMinutes = value.getTimezoneOffset()
    const localDate = new Date(value.getTime() - offsetMinutes * 60_000)
    return localDate.toISOString().slice(0, 10)
}

export function CryoConservationSection({
    lineID,
    lineName,
}: {
    lineID: string
    lineName: string
}) {
    const queryClient = useQueryClient()
    const { toast } = useToast()
    const { role } = useRole()
    const allowEdit = canEdit(role)

    const cryoQuery = useQuery({
        queryKey: ['zefix', 'lines', lineID, 'cryo-records'],
        queryFn: () => getZefishCryoRecords(lineID),
        enabled: Boolean(lineID),
    })

    const cryoRecords = useMemo(
        () =>
            (cryoQuery.data ?? [])
                .slice()
                .sort((left, right) => new Date(right.storageDate).getTime() - new Date(left.storageDate).getTime()),
        [cryoQuery.data]
    )

    const exportMutation = useMutation({
        mutationFn: async () => {
            if (!cryoRecords.length) {
                throw new Error('No CRYO records are available to export.')
            }

            if (getDataMode() === 'real') {
                await downloadZefishCryoInventoryCsv(lineID)
                return
            }

            exportCsv(
                cryoRecords.map((record) => ({
                    lineID: record.lineID,
                    storageDate: formatDate(record.storageDate),
                    storageLocation: record.storageLocation,
                    protocolUsed: record.protocolUsed,
                    status: statusLabel(record.status),
                    notes: record.notes ?? '',
                })),
                `${lineID}-cryo-inventory`
            )
        },
        onSuccess: () => {
            toast({
                title: 'CRYO inventory exported',
                description: 'The current line inventory CSV has been downloaded.',
                variant: 'success',
            })
        },
        onError: (error) => {
            toast({
                title: 'Export failed',
                description: error instanceof Error ? error.message : 'Unable to export the CRYO inventory.',
                variant: 'error',
            })
        },
    })

    const form = useForm<CryoFormValues>({
        resolver: zodResolver(cryoFormSchema),
        defaultValues: {
            storageDate: toLocalDateInputValue(),
            storageLocation: '',
            protocolUsed: '',
            status: 'stored',
            notes: '',
        },
        mode: 'onChange',
    })

    const createMutation = useMutation({
        mutationFn: async (values: CryoFormValues) => {
            await createZefishCryoRecord({
                lineID,
                storageDate: values.storageDate,
                storageLocation: values.storageLocation.trim(),
                protocolUsed: values.protocolUsed.trim(),
                status: values.status,
                notes: values.notes?.trim() ? values.notes.trim() : null,
            })
        },
        onSuccess: async () => {
            toast({
                title: 'CRYO record saved',
                description: `The conservation log for ${lineName} has been updated.`,
                variant: 'success',
            })
            form.reset({
                storageDate: toLocalDateInputValue(),
                storageLocation: '',
                protocolUsed: '',
                status: 'stored',
                notes: '',
            })
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['zefix', 'lines', lineID, 'cryo-records'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'lines', lineID, 'detail'] }),
            ])
        },
        onError: (error) => {
            toast({
                title: 'CRYO record failed',
                description: error instanceof Error ? error.message : 'Unable to save the CRYO record.',
                variant: 'error',
            })
        },
    })

    const summary = useMemo(() => {
        const latest = cryoRecords[0]

        return {
            total: cryoRecords.length,
            stored: cryoRecords.filter((record) => record.status === 'stored').length,
            used: cryoRecords.filter((record) => record.status === 'used').length,
            discarded: cryoRecords.filter((record) => record.status === 'discarded').length,
            latestStorageDate: latest?.storageDate ?? null,
        }
    }, [cryoRecords])

    if (cryoQuery.isError) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>CRYO conservation</CardTitle>
                    <CardDescription>Line-linked storage inventory and retrieval history.</CardDescription>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        title="CRYO inventory unavailable"
                        description={cryoQuery.error?.message ?? 'Unable to load the conservation records.'}
                    />
                </CardContent>
            </Card>
        )
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle>CRYO conservation</CardTitle>
                        <CardDescription>Line-linked storage inventory and retrieval history for {lineName}.</CardDescription>
                    </div>
                    {allowEdit ? (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => exportMutation.mutate()}
                            disabled={!cryoRecords.length || exportMutation.isPending}
                        >
                            <Download className="h-4 w-4" />
                            {exportMutation.isPending ? 'Exporting…' : 'Export inventory CSV'}
                        </Button>
                    ) : null}
                </div>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid gap-4 md:grid-cols-5">
                    <Card>
                        <CardHeader>
                            <CardDescription>Total records</CardDescription>
                            <CardTitle>{summary.total}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Stored</CardDescription>
                            <CardTitle>{summary.stored}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Used</CardDescription>
                            <CardTitle>{summary.used}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Discarded</CardDescription>
                            <CardTitle>{summary.discarded}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Latest storage</CardDescription>
                            <CardTitle>{summary.latestStorageDate ? formatDate(summary.latestStorageDate) : 'No records yet'}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                {allowEdit ? (
                    <Card className="border-line/70 bg-surface-2/40">
                        <CardHeader>
                            <CardTitle>Record CRYO storage</CardTitle>
                            <CardDescription>Store a conservation entry against this zebrafish line.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form {...form}>
                                <form
                                    className="grid gap-4 md:grid-cols-2"
                                    onSubmit={form.handleSubmit((values) => createMutation.mutate(values))}
                                >
                                    <FormField
                                        control={form.control}
                                        name="storageDate"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Storage date</FormLabel>
                                                <FormControl>
                                                    <Input type="date" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="status"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Status</FormLabel>
                                                <FormControl>
                                                    <select
                                                        {...field}
                                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                                    >
                                                        {statusOptions.map((status) => (
                                                            <option key={status} value={status}>
                                                                {statusLabel(status)}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="storageLocation"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Storage location</FormLabel>
                                                <FormControl>
                                                    <Input {...field} placeholder="Cryo A1 / Rack 1 / Box 02" />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="protocolUsed"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Protocol used</FormLabel>
                                                <FormControl>
                                                    <Input {...field} placeholder="Controlled-rate freeze v1" />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="notes"
                                        render={({ field }) => (
                                            <FormItem className="md:col-span-2">
                                                <FormLabel>Notes</FormLabel>
                                                <FormControl>
                                                    <Textarea {...field} rows={4} placeholder="Optional storage context or recovery notes" />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <div className="md:col-span-2 flex justify-end">
                                        <Button type="submit" disabled={createMutation.isPending}>
                                            <Plus className="h-4 w-4" />
                                            {createMutation.isPending ? 'Saving…' : 'Save CRYO record'}
                                        </Button>
                                    </div>
                                </form>
                            </Form>
                        </CardContent>
                    </Card>
                ) : (
                    <RestrictedActions
                        title="Read-only access"
                        description="Recording CRYO inventory entries requires editor access."
                        actionLabel="Editor access required"
                        disabled
                        onAction={() => {}}
                    />
                )}

                <Card className="border-line/70">
                    <CardHeader>
                        <CardTitle>Inventory records</CardTitle>
                        <CardDescription>Most recent persisted CRYO entries for this line.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {cryoQuery.isLoading ? (
                            <EmptyState title="Loading CRYO inventory" description="Fetching conservation records." />
                        ) : cryoRecords.length ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Storage date</TableHead>
                                        <TableHead>Location</TableHead>
                                        <TableHead>Protocol</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Notes</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {cryoRecords.map((record) => (
                                        <TableRow key={record.cryoRecordID}>
                                            <TableCell className="font-medium text-slate-900">{formatDate(record.storageDate)}</TableCell>
                                            <TableCell>{record.storageLocation}</TableCell>
                                            <TableCell>{record.protocolUsed}</TableCell>
                                            <TableCell>
                                                <Badge variant={statusVariant[record.status]}>{statusLabel(record.status)}</Badge>
                                            </TableCell>
                                            <TableCell className="max-w-xl truncate text-slate-500">
                                                {record.notes ?? 'No notes'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <EmptyState
                                title="No CRYO records yet"
                                description="Add the first conservation record to start the line inventory trail."
                            />
                        )}
                    </CardContent>
                </Card>
            </CardContent>
        </Card>
    )
}
