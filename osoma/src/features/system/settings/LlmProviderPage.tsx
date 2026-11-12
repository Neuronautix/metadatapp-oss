import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Bot, CheckCircle, AlertCircle, Eye, EyeOff } from 'lucide-react'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import {
    Form,
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form.tsx'
import { Input } from '@/components/ui/input.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { apiFetch } from '@/lib/api.ts'

type LlmProviderConfig = {
    provider: string | null
    keyPreview: string | null
    model: string | null
    configured: boolean
}

const PROVIDER_OPTIONS = [
    { value: 'openai', label: 'OpenAI', models: ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'] },
    { value: 'anthropic', label: 'Anthropic (Claude)', models: ['claude-opus-4-5', 'claude-sonnet-4-5', 'claude-3-5-haiku-20241022'] },
    { value: 'gemini', label: 'Google Gemini', models: ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-2.0-flash-exp'] },
]

const formSchema = z.object({
    provider: z.enum(['openai', 'anthropic', 'gemini'], {
        required_error: 'Please select a provider.',
    }),
    apiKey: z.string().min(1, 'API key is required.'),
    model: z.string().optional(),
})

type FormValues = z.infer<typeof formSchema>

function getLlmProviderConfig() {
    return apiFetch<LlmProviderConfig>('/ai/provider-config')
}

function updateLlmProviderConfig(payload: { provider: string; apiKey: string; model?: string }) {
    return apiFetch<LlmProviderConfig>('/ai/provider-config', {
        method: 'PATCH',
        body: JSON.stringify(payload),
    })
}

function clearLlmProviderConfig() {
    return apiFetch<LlmProviderConfig>('/ai/provider-config', {
        method: 'PATCH',
        body: JSON.stringify({ provider: null, apiKey: '', model: null }),
    })
}

export function LlmProviderPage() {
    const { toast } = useToast()
    const queryClient = useQueryClient()
    const [showKey, setShowKey] = useState(false)

    const { data: config, isLoading } = useQuery({
        queryKey: ['ai-provider-config'],
        queryFn: getLlmProviderConfig,
    })

    const updateMutation = useMutation({
        mutationFn: updateLlmProviderConfig,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['ai-provider-config'] })
            queryClient.invalidateQueries({ queryKey: ['ai-status'] })
            toast({ title: 'AI provider saved', description: 'Your LLM provider configuration has been updated.' })
            form.resetField('apiKey')
            setShowKey(false)
        },
        onError: (error: Error) => {
            toast({ title: 'Failed to save', description: error.message, variant: 'error' })
        },
    })

    const clearMutation = useMutation({
        mutationFn: clearLlmProviderConfig,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['ai-provider-config'] })
            queryClient.invalidateQueries({ queryKey: ['ai-status'] })
            toast({ title: 'AI provider removed', description: 'LLM provider configuration cleared.' })
            form.reset({ provider: undefined, apiKey: '', model: '' })
        },
        onError: (error: Error) => {
            toast({ title: 'Failed to clear', description: error.message, variant: 'error' })
        },
    })

    const selectedProvider = PROVIDER_OPTIONS.find((p) => p.value === config?.provider) ?? null

    const form = useForm<FormValues>({
        resolver: zodResolver(formSchema),
        defaultValues: {
            provider: (config?.provider as FormValues['provider']) ?? undefined,
            apiKey: '',
            model: config?.model ?? '',
        },
    })

    const watchedProvider = form.watch('provider')
    const providerModels = PROVIDER_OPTIONS.find((p) => p.value === watchedProvider)?.models ?? []

    function onSubmit(data: FormValues) {
        updateMutation.mutate({
            provider: data.provider,
            apiKey: data.apiKey,
            model: data.model ?? undefined,
        })
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Bot className="h-5 w-5" />
                        AI Provider
                    </CardTitle>
                    <CardDescription>
                        Configure the LLM provider used for AI-assisted features across your organisation.
                        Your API key is stored securely and never exposed in full.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <p className="text-sm text-slate-500">Loading configuration…</p>
                    ) : config?.configured ? (
                        <div className="flex items-center gap-3">
                            <CheckCircle className="h-5 w-5 text-green-500" />
                            <div>
                                <p className="text-sm font-semibold text-slate-800">
                                    {selectedProvider?.label ?? config.provider}
                                </p>
                                <p className="font-mono text-xs text-slate-500">{config.keyPreview}</p>
                                {config.model && (
                                    <Badge variant="outline" className="mt-1 text-xs">{config.model}</Badge>
                                )}
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-center gap-3">
                            <AlertCircle className="h-5 w-5 text-amber-500" />
                            <p className="text-sm text-slate-600">
                                No LLM provider configured. AI-assisted features are unavailable.
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{config?.configured ? 'Update provider' : 'Configure provider'}</CardTitle>
                    <CardDescription>
                        Enter a new API key to {config?.configured ? 'replace the current configuration' : 'enable AI features'}.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form {...form}>
                        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                            <FormField
                                control={form.control}
                                name="provider"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Provider</FormLabel>
                                        <FormControl>
                                            <select
                                                {...field}
                                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                            >
                                                <option value="">Select a provider…</option>
                                                {PROVIDER_OPTIONS.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </FormControl>
                                        <FormDescription>
                                            The LLM provider whose API will be used for chat, suggestions, and other AI tasks.
                                        </FormDescription>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />

                            <FormField
                                control={form.control}
                                name="apiKey"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>API Key</FormLabel>
                                        <FormControl>
                                            <div className="relative">
                                                <Input
                                                    type={showKey ? 'text' : 'password'}
                                                    placeholder={
                                                        config?.configured
                                                            ? 'Enter new key to replace existing'
                                                            : 'sk-… or your provider key'
                                                    }
                                                    {...field}
                                                    className="pr-10"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => setShowKey((v) => !v)}
                                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                                    aria-label={showKey ? 'Hide key' : 'Show key'}
                                                >
                                                    {showKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                </button>
                                            </div>
                                        </FormControl>
                                        <FormDescription>
                                            The key is stored server-side and shown only as a masked preview after saving.
                                        </FormDescription>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />

                            {providerModels.length > 0 && (
                                <FormField
                                    control={form.control}
                                    name="model"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>
                                                Model <span className="text-slate-400">(optional)</span>
                                            </FormLabel>
                                            <FormControl>
                                                <select
                                                    {...field}
                                                    value={field.value ?? ''}
                                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                                >
                                                    <option value="">Use provider default</option>
                                                    {providerModels.map((m) => (
                                                        <option key={m} value={m}>{m}</option>
                                                    ))}
                                                </select>
                                            </FormControl>
                                            <FormDescription>
                                                Leave blank to use the recommended default model for the selected provider.
                                            </FormDescription>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                            )}

                            <div className="flex gap-3">
                                <Button type="submit" disabled={updateMutation.isPending}>
                                    {updateMutation.isPending ? 'Saving…' : 'Save configuration'}
                                </Button>
                                {config?.configured && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => clearMutation.mutate()}
                                        disabled={clearMutation.isPending}
                                    >
                                        {clearMutation.isPending ? 'Clearing…' : 'Remove configuration'}
                                    </Button>
                                )}
                            </div>
                        </form>
                    </Form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-sm">Where to get your API key</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm text-slate-600">
                    <p>
                        <strong>OpenAI:</strong>{' '}
                        <a
                            href="https://platform.openai.com/api-keys"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-blue-600 hover:underline"
                        >
                            platform.openai.com/api-keys
                        </a>
                    </p>
                    <p>
                        <strong>Anthropic:</strong>{' '}
                        <a
                            href="https://console.anthropic.com/settings/keys"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-blue-600 hover:underline"
                        >
                            console.anthropic.com/settings/keys
                        </a>
                    </p>
                    <p>
                        <strong>Google Gemini:</strong>{' '}
                        <a
                            href="https://aistudio.google.com/app/apikey"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-blue-600 hover:underline"
                        >
                            aistudio.google.com/app/apikey
                        </a>
                    </p>
                    <p className="pt-2 text-xs text-slate-400">
                        The API key is stored at the organisation level and applies to all users in this account.
                        Only organisation admins can modify it.
                    </p>
                </CardContent>
            </Card>
        </div>
    )
}
