import { zodResolver } from '@hookform/resolvers/zod'
import { type Resolver, useForm } from 'react-hook-form'
import { z } from 'zod'
import type { EnvironmentalMetric, EnvironmentalScope } from '@/domain/zefish/zefish.types.ts'
import { Button } from '@/components/ui/button.tsx'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form.tsx'
import { Input } from '@/components/ui/input.tsx'

const observationFormSchema = z.object({
    timestamp: z.string().min(1, 'Timestamp is required.'),
    metric: z.enum(['temperature', 'pH']),
    value: z.preprocess(
        (raw) => {
            if (typeof raw === 'string') {
                const trimmed = raw.trim()
                if (trimmed === '') {
                    return undefined
                }
                return trimmed
            }
            return raw
        },
        z.coerce
            .number({ required_error: 'Value is required.' })
            .min(-10, 'Value is required.')
            .max(100, 'Value looks out of range.')
    ),
    notes: z.string().max(250, 'Notes must be 250 characters or less.').optional().or(z.literal('')),
})

export type EnvironmentalObservationFormValues = z.infer<typeof observationFormSchema>
type EnvironmentalObservationFormInput = z.input<typeof observationFormSchema>

export interface EnvironmentalObservationFormProps {
    scopeType: EnvironmentalScope
    scopeID: string
    scopeLabel: string
    defaultMetric?: EnvironmentalMetric
    isPending?: boolean
    onSubmit: (values: EnvironmentalObservationFormValues) => Promise<void> | void
}

const toLocalDateTimeInputValue = (value = new Date()) => {
    const offsetMinutes = value.getTimezoneOffset()
    const localDate = new Date(value.getTime() - offsetMinutes * 60_000)
    return localDate.toISOString().slice(0, 16)
}

export function EnvironmentalObservationForm({
    defaultMetric = 'temperature',
    isPending = false,
    onSubmit,
    scopeLabel,
    scopeID,
    scopeType,
}: EnvironmentalObservationFormProps) {
    const form = useForm<EnvironmentalObservationFormInput, undefined, EnvironmentalObservationFormValues>({
        resolver: zodResolver(observationFormSchema) as Resolver<
            EnvironmentalObservationFormInput,
            undefined,
            EnvironmentalObservationFormValues
        >,
        defaultValues: {
            timestamp: toLocalDateTimeInputValue(),
            metric: defaultMetric,
            value: 0,
            notes: '',
        },
        mode: 'onChange',
    })

    const handleSubmit = async (values: EnvironmentalObservationFormValues) => {
        await onSubmit(values)
        form.reset({
            timestamp: toLocalDateTimeInputValue(),
            metric: defaultMetric,
            value: 0,
            notes: '',
        })
    }

    return (
        <Form {...form}>
            <form className="grid gap-4 md:grid-cols-2" onSubmit={form.handleSubmit(handleSubmit)}>
                <div className="md:col-span-2 rounded-2xl border border-dashed border-line bg-surface-2/60 p-3 text-xs text-slate-500">
                    Recording for <span className="font-semibold text-slate-800">{scopeLabel}</span> ({scopeType}:{' '}
                    {scopeID}). Source is saved as manual.
                </div>

                <FormField
                    control={form.control}
                    name="timestamp"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Timestamp</FormLabel>
                            <FormControl>
                                <Input type="datetime-local" {...field} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="metric"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Metric</FormLabel>
                            <FormControl>
                                <select
                                    {...field}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                >
                                    {(['temperature', 'pH'] as EnvironmentalMetric[]).map((metric) => (
                                        <option key={metric} value={metric}>
                                            {metric === 'pH' ? 'pH' : 'Temperature'}
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
                    name="value"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Value</FormLabel>
                            <FormControl>
                                <Input
                                    type="number"
                                    step="0.1"
                                    name={field.name}
                                    ref={field.ref}
                                    onBlur={field.onBlur}
                                    onChange={field.onChange}
                                    value={typeof field.value === 'number' || typeof field.value === 'string' ? field.value : ''}
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="notes"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Notes</FormLabel>
                            <FormControl>
                                <Input
                                    {...field}
                                    placeholder="Optional context"
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <div className="md:col-span-2 flex items-center justify-end gap-3">
                    <Button type="submit" disabled={isPending || !scopeID}>
                        {isPending ? 'Saving…' : 'Save observation'}
                    </Button>
                </div>
            </form>
        </Form>
    )
}
