import { useMemo } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { DetailLayout } from '@/components/DetailLayout.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { HorizontalTimeline } from '@/components/ui/HorizontalTimeline.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { RestrictedActions } from '@/components/RestrictedActions.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import { downloadZefishMortalityLogsCsv, createZefishMortalityRecord, getZefishBatchDetail } from '@/domain/zefish/zefish.api.ts'
import type { MortalityReason } from '@/domain/zefish/zefish.types.ts'
import { formatDate, formatDateTime } from '@/lib/format.ts'
import { useToast } from '@/components/ui/toast.tsx'

const mortalityFormSchema = z.object({
    date: z.string().min(1, 'Mortality date is required.'),
    count: z.coerce.number().int().min(1, 'Count must be at least 1.'),
    reason: z.enum(['natural', 'disease', 'experimental endpoint', 'unknown', 'system issue']),
    notes: z.string().max(500, 'Notes must be 500 characters or less.').optional().or(z.literal('')),
})

type MortalityFormValues = z.infer<typeof mortalityFormSchema>

const toLocalDateInputValue = (value = new Date()) => {
    const offsetMinutes = value.getTimezoneOffset()
    const localDate = new Date(value.getTime() - offsetMinutes * 60_000)
    return localDate.toISOString().slice(0, 10)
}

export function BatchDetailPage() {
    const { batchID } = useParams()
    const resolvedBatchID = batchID ?? ''
    const queryClient = useQueryClient()
    const { toast } = useToast()
    const { role } = useRole()
    const allowEdit = canEdit(role)

    const detailQuery = useQuery({
        queryKey: ['zefix', 'batch', resolvedBatchID],
        queryFn: () => getZefishBatchDetail(resolvedBatchID),
        enabled: Boolean(resolvedBatchID),
    })
    const exportMutation = useMutation({
        mutationFn: () => downloadZefishMortalityLogsCsv(resolvedBatchID),
    })

    const form = useForm<MortalityFormValues>({
        resolver: zodResolver(mortalityFormSchema),
        defaultValues: {
            date: toLocalDateInputValue(),
            count: 1,
            reason: 'natural',
            notes: '',
        },
        mode: 'onChange',
    })

    const mortalityMutation = useMutation({
        mutationFn: async (values: MortalityFormValues) => {
            if (!detailQuery.data) {
                throw new Error('Batch detail is not loaded yet.')
            }

            await createZefishMortalityRecord({
                batchID: detailQuery.data.batch.batchID,
                date: values.date,
                count: values.count,
                reason: values.reason as MortalityReason,
                notes: values.notes?.trim() ? values.notes.trim() : null,
            })
        },
        onSuccess: async () => {
            toast({
                title: 'Mortality recorded',
                description: 'Batch population and aggregate views will refresh from persisted data.',
                variant: 'success',
            })
            form.reset({
                date: toLocalDateInputValue(),
                count: 1,
                reason: 'natural',
                notes: '',
            })
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['zefix', 'batch', resolvedBatchID] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'batches'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'lines'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'rooms'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'overview'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'alerts'] }),
            ])
        },
        onError: (error) => {
            toast({
                title: 'Mortality entry failed',
                description: error instanceof Error ? error.message : 'Unable to save mortality entry.',
                variant: 'error',
            })
        },
    })

    const timelineNodes = useMemo(
        () =>
            (detailQuery.data?.timeline ?? []).map((point) => ({
                id: point.id,
                title: point.title,
                subtitle: point.subtitle,
                timestamp: new Date(point.timestamp),
                color: point.color,
            })),
        [detailQuery.data?.timeline]
    )

    if (detailQuery.isLoading) {
        return (
            <div className="space-y-4">
                <Skeleton className="h-10 w-72" />
                <Skeleton className="h-64 w-full" />
            </div>
        )
    }

    if (detailQuery.isError || !detailQuery.data) {
        return (
            <EmptyState
                title="Batch unavailable"
                description={detailQuery.error?.message ?? 'Unable to load batch detail.'}
            />
        )
    }

    const detail = detailQuery.data

    return (
        <DetailLayout
            title={`Batch ${detail.batch.batchID}`}
            subtitle={`${detail.line.name} · ${detail.batch.ageLabel}`}
            backLink="/zefix/batches"
            backLabel="All batches"
            actions={
                allowEdit ? (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => exportMutation.mutate()}
                    disabled={exportMutation.isPending}
                >
                    {exportMutation.isPending ? 'Exporting…' : 'Export mortality CSV'}
                </Button>
                ) : undefined
            }
        >
            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardDescription>Birth date</CardDescription>
                            <CardTitle>{formatDate(detail.batch.birthDate)}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Current population</CardDescription>
                            <CardTitle>{detail.batch.individualCount.toLocaleString()}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Mortalities</CardDescription>
                            <CardTitle>{detail.batch.mortalityCount.toLocaleString()}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Tank location</CardDescription>
                            <CardTitle className="text-sm">{detail.batch.tankLocation}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                {allowEdit ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Record mortality</CardTitle>
                            <CardDescription>Persist a mortality entry and let the backend recalculate the batch population.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form {...form}>
                                <form className="grid gap-4 md:grid-cols-2" onSubmit={form.handleSubmit((values) => mortalityMutation.mutate(values))}>
                                    <FormField
                                        control={form.control}
                                        name="date"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Date</FormLabel>
                                                <FormControl>
                                                    <Input type="date" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="count"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Count</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min={1} step={1} {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="reason"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Reason</FormLabel>
                                                <FormControl>
                                                    <select
                                                        {...field}
                                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                                    >
                                                        {(['natural', 'disease', 'experimental endpoint', 'unknown', 'system issue'] as MortalityReason[]).map((reason) => (
                                                            <option key={reason} value={reason}>
                                                                {reason}
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
                                        name="notes"
                                        render={({ field }) => (
                                            <FormItem className="md:col-span-2">
                                                <FormLabel>Notes</FormLabel>
                                                <FormControl>
                                                    <textarea
                                                        {...field}
                                                        rows={4}
                                                        className="flex min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                                        placeholder="Optional context for the mortality entry"
                                                    />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <div className="md:col-span-2 flex flex-wrap items-center justify-end gap-3">
                                        <Button type="submit" disabled={mortalityMutation.isPending}>
                                            {mortalityMutation.isPending ? 'Saving…' : 'Save mortality entry'}
                                        </Button>
                                    </div>
                                </form>
                            </Form>
                        </CardContent>
                    </Card>
                ) : (
                    <RestrictedActions
                        title="Read-only access"
                        description="Recording mortalities and exporting mortality logs require editor access."
                        actionLabel="Editor access required"
                        disabled
                        onAction={() => {}}
                    />
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Batch timeline</CardTitle>
                        <CardDescription>Persisted batch event history from the backend.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <HorizontalTimeline nodes={timelineNodes} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Mortality log</CardTitle>
                        <CardDescription>Each mortality event is tracked with date, count, and reason.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!detail.mortalityEvents.length ? (
                            <EmptyState title="No mortalities" description="No mortality event is recorded for this batch." />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Number</TableHead>
                                        <TableHead>Reason</TableHead>
                                        <TableHead>Notes</TableHead>
                                        <TableHead>Recorder</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {detail.mortalityEvents.map((event) => (
                                        <TableRow key={event.mortalityRecordID ?? event.id ?? `${event.date}-${event.count ?? event.number ?? 0}`}>
                                            <TableCell>{formatDateTime(event.date)}</TableCell>
                                            <TableCell>{event.count ?? event.number ?? 0}</TableCell>
                                            <TableCell>{event.reason ?? event.category ?? 'unknown'}</TableCell>
                                            <TableCell>{event.notes ?? '—'}</TableCell>
                                            <TableCell>{event.recorder ?? '—'}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </DetailLayout>
    )
}
