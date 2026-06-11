import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Loader2, Plus } from 'lucide-react'
import { z } from 'zod'
import type { ZefixLocationExplorerPool } from '@/domain/zefish/zefish.types.ts'
import { createZefishBatch, getZefishLines } from '@/domain/zefish/zefish.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog.tsx'
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form.tsx'
import { Input } from '@/components/ui/input.tsx'
import { useToast } from '@/components/ui/toast.tsx'

const createBatchFormSchema = z.object({
    lineID: z.string().min(1, 'Select a zebrafish line.'),
    birthDate: z.string().min(1, 'Choose a birth date.'),
    maleCount: z.coerce.number().int().min(0, 'Male count must be zero or greater.'),
    femaleCount: z.coerce.number().int().min(0, 'Female count must be zero or greater.'),
    unknownSexCount: z.coerce.number().int().min(0, 'Unknown count must be zero or greater.'),
})

type CreateBatchFormValues = z.infer<typeof createBatchFormSchema>

const toLocalDateInputValue = (value = new Date()) => {
    const offsetMinutes = value.getTimezoneOffset()
    const localDate = new Date(value.getTime() - offsetMinutes * 60_000)
    return localDate.toISOString().slice(0, 10)
}

export function CreateBatchDialog({
    pool,
    plateauCode,
    roomName,
    rackCode,
}: {
    pool: ZefixLocationExplorerPool
    plateauCode: string
    roomName: string
    rackCode: string
}) {
    const [open, setOpen] = useState(false)
    const queryClient = useQueryClient()
    const { toast } = useToast()
    const form = useForm<CreateBatchFormValues>({
        resolver: zodResolver(createBatchFormSchema),
        defaultValues: {
            lineID: '',
            birthDate: toLocalDateInputValue(),
            maleCount: 0,
            femaleCount: 0,
            unknownSexCount: 0,
        },
        mode: 'onChange',
    })

    const linesQuery = useQuery({
        queryKey: ['zefix', 'lines', 'batch-create'],
        queryFn: () => getZefishLines(),
        enabled: open,
    })

    useEffect(() => {
        if (!open) {
            form.reset({
                lineID: '',
                birthDate: toLocalDateInputValue(),
                maleCount: 0,
                femaleCount: 0,
                unknownSexCount: 0,
            })
            return
        }

        if (!form.getValues('lineID') && linesQuery.data?.length) {
            form.setValue('lineID', linesQuery.data[0].lineID, { shouldValidate: true })
        }
    }, [form, linesQuery.data, open])

    const watchedValues = form.watch()
    const totalPopulation = watchedValues.maleCount + watchedValues.femaleCount + watchedValues.unknownSexCount

    const mutation = useMutation({
        mutationFn: async (values: CreateBatchFormValues) => {
            await createZefishBatch({
                line: values.lineID,
                pool: pool.poolId,
                birthDate: values.birthDate,
                maleCount: values.maleCount,
                femaleCount: values.femaleCount,
                unknownSexCount: values.unknownSexCount,
                totalPopulation: values.maleCount + values.femaleCount + values.unknownSexCount,
            })
        },
        onSuccess: async () => {
            toast({
                title: 'Batch created',
                description: `A batch was assigned to pool ${pool.position}.`,
                variant: 'success',
            })
            setOpen(false)
            form.reset({
                lineID: '',
                birthDate: toLocalDateInputValue(),
                maleCount: 0,
                femaleCount: 0,
                unknownSexCount: 0,
            })
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['zefix', 'batches'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'batch'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'location-explorer'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'lines'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'overview'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'systems'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'rooms'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'alerts'] }),
            ])
        },
        onError: (error: Error) => {
            toast({
                title: 'Batch creation failed',
                description: error.message,
                variant: 'error',
            })
        },
    })

    const canCreateBatch = !pool.occupied && pool.status === 'available'
    const isSubmitting = mutation.isPending || linesQuery.isLoading

    return (
        <>
            <Button
                type="button"
                variant="default"
                className="w-full"
                onClick={() => setOpen(true)}
                disabled={!canCreateBatch}
            >
                <Plus className="h-4 w-4" />
                Create batch here
            </Button>

            {!canCreateBatch ? (
                <p className="text-xs text-slate-500">
                    The selected pool must be empty and available before a batch can be created.
                </p>
            ) : null}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-[560px]">
                    <DialogHeader>
                        <DialogTitle>Create batch in {pool.position}</DialogTitle>
                        <DialogDescription>
                            Assign a new zebrafish batch to {plateauCode} / {roomName} / {rackCode}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-wrap gap-2">
                        <Badge variant="outline">{pool.status}</Badge>
                        <Badge variant="default">{pool.capacity} capacity</Badge>
                    </div>

                    <Form {...form}>
                        <form
                            className="grid gap-4"
                            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
                        >
                            <FormField
                                control={form.control}
                                name="lineID"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Line</FormLabel>
                                        <FormControl>
                                            <select
                                                {...field}
                                                className="flex h-[var(--control-height)] w-full rounded-[var(--radius-xl)] border border-line bg-surface px-[var(--control-padding-x)] py-[var(--control-padding-y)] text-sm text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                                disabled={linesQuery.isLoading || !linesQuery.data?.length}
                                            >
                                                <option value="">Select a line</option>
                                                {linesQuery.data?.map((line) => (
                                                    <option key={line.lineID} value={line.lineID}>
                                                        {line.name} · {line.reproductionStatus}
                                                    </option>
                                                ))}
                                            </select>
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    control={form.control}
                                    name="birthDate"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Birth date</FormLabel>
                                            <FormControl>
                                                <Input type="date" {...field} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <div className="rounded-[var(--radius-xl)] border border-dashed border-line bg-surface-2 px-4 py-3 text-sm text-slate-600">
                                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Population</p>
                                    <p className="mt-1 font-semibold text-slate-900">{totalPopulation.toLocaleString()} fish</p>
                                    <p className="mt-1 text-xs text-slate-500">This value is persisted as the batch total.</p>
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <FormField
                                    control={form.control}
                                    name="maleCount"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Male count</FormLabel>
                                            <FormControl>
                                                <Input type="number" min={0} step={1} {...field} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <FormField
                                    control={form.control}
                                    name="femaleCount"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Female count</FormLabel>
                                            <FormControl>
                                                <Input type="number" min={0} step={1} {...field} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <FormField
                                    control={form.control}
                                    name="unknownSexCount"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Unknown count</FormLabel>
                                            <FormControl>
                                                <Input type="number" min={0} step={1} {...field} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOpen(false)}
                                    disabled={mutation.isPending}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={isSubmitting || !linesQuery.data?.length}>
                                    {mutation.isPending ? (
                                        <>
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            Creating...
                                        </>
                                    ) : (
                                        'Create batch'
                                    )}
                                </Button>
                            </DialogFooter>
                        </form>
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    )
}
