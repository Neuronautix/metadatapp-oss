import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Bot, CheckCircle2, KeyRound, Loader2, ShieldCheck, XCircle } from 'lucide-react'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { getLlmProviders, saveLlmProvider, testLlmProvider, type LlmProviderCredential, type LlmProviderName } from './ai-providers.api.ts'

const providerLabels: Record<LlmProviderName, string> = {
    openai: 'OpenAI',
    anthropic: 'Anthropic',
}

const defaultModels: Record<LlmProviderName, string[]> = {
    openai: ['gpt-5', 'gpt-5-mini', 'gpt-4.1'],
    anthropic: ['claude-opus-4-1-20250805', 'claude-opus-4-20250514', 'claude-sonnet-4-20250514'],
}

type ProviderFormState = {
    apiKey: string
    model: string
}

function ProviderCard({ provider }: { provider: LlmProviderCredential }) {
    const queryClient = useQueryClient()
    const { toast } = useToast()
    const [formState, setFormState] = useState<ProviderFormState>({
        apiKey: '',
        model: provider.model,
    })

    const saveMutation = useMutation({
        mutationFn: () => saveLlmProvider(provider.provider, {
            apiKey: formState.apiKey.trim() || undefined,
            model: formState.model.trim() || provider.model,
            active: true,
        }),
        onSuccess: (saved) => {
            toast({
                title: 'Provider saved',
                description: `${providerLabels[saved.provider]} is configured for ${saved.model}.`,
            })
            setFormState((previous) => ({ ...previous, apiKey: '', model: saved.model }))
            queryClient.invalidateQueries({ queryKey: ['llm-providers'] })
            queryClient.invalidateQueries({ queryKey: ['ai-status'] })
        },
        onError: (error: Error) => {
            toast({ title: 'Save failed', description: error.message, variant: 'error' })
        },
    })

    const activeMutation = useMutation({
        mutationFn: (active: boolean) => saveLlmProvider(provider.provider, {
            apiKey: active ? formState.apiKey.trim() || undefined : undefined,
            model: formState.model.trim() || provider.model,
            active,
        }),
        onSuccess: (saved) => {
            toast({
                title: saved.active ? 'Provider activated' : 'Provider deactivated',
                description: saved.active
                    ? `${providerLabels[saved.provider]} can now be used by the AI assistant.`
                    : `${providerLabels[saved.provider]} will not be used by the AI assistant.`,
            })
            setFormState((previous) => ({ ...previous, model: saved.model }))
            queryClient.invalidateQueries({ queryKey: ['llm-providers'] })
            queryClient.invalidateQueries({ queryKey: ['ai-status'] })
        },
        onError: (error: Error) => {
            toast({ title: 'Provider update failed', description: error.message, variant: 'error' })
        },
    })

    const testMutation = useMutation({
        mutationFn: () => testLlmProvider(provider.provider),
        onSuccess: ({ status }) => {
            toast({
                title: status.available ? 'Connection verified' : 'Connection failed',
                description: status.message,
                variant: status.available ? 'default' : 'error',
            })
            queryClient.invalidateQueries({ queryKey: ['ai-status'] })
        },
        onError: (error: Error) => {
            toast({ title: 'Connection test failed', description: error.message, variant: 'error' })
        },
    })

    const configuredLabel = provider.configured
        ? provider.source === 'environment'
            ? 'Environment key'
            : 'User key'
        : 'Not configured'

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <Bot className="h-5 w-5" />
                            {providerLabels[provider.provider]}
                        </CardTitle>
                        <CardDescription>
                            Server-side API key used by the governed AI assistant.
                        </CardDescription>
                    </div>
                    <Badge variant={provider.configured ? 'default' : 'outline'} className="gap-1">
                        {provider.configured ? <CheckCircle2 className="h-3 w-3" /> : <XCircle className="h-3 w-3" />}
                        {configuredLabel}
                    </Badge>
                    <Badge variant={provider.active ? 'default' : 'outline'}>
                        {provider.active ? 'Active for chat' : 'Inactive'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor={`${provider.provider}-model`}>Default model</Label>
                    <Input
                        id={`${provider.provider}-model`}
                        list={`${provider.provider}-models`}
                        value={formState.model}
                        onChange={(event) => setFormState((previous) => ({ ...previous, model: event.target.value }))}
                    />
                    <datalist id={`${provider.provider}-models`}>
                        {defaultModels[provider.provider].map((model) => (
                            <option key={model} value={model} />
                        ))}
                    </datalist>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${provider.provider}-key`}>API key</Label>
                    <Input
                        id={`${provider.provider}-key`}
                        type="password"
                        autoComplete="off"
                        placeholder={provider.apiKeyHint ? `Currently set as ${provider.apiKeyHint}` : `Paste ${providerLabels[provider.provider]} API key`}
                        value={formState.apiKey}
                        onChange={(event) => setFormState((previous) => ({ ...previous, apiKey: event.target.value }))}
                    />
                    <p className="text-xs text-slate-500">
                        Leave blank to keep the existing key. Keys are stored server-side and returned only as masked hints.
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
                        {saveMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <KeyRound className="mr-2 h-4 w-4" />}
                        Save and activate
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => activeMutation.mutate(!provider.active)}
                        disabled={activeMutation.isPending || (!provider.configured && !formState.apiKey.trim())}
                    >
                        {activeMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Bot className="mr-2 h-4 w-4" />}
                        {provider.active ? 'Deactivate chat' : 'Activate chat'}
                    </Button>
                    <Button variant="outline" onClick={() => testMutation.mutate()} disabled={testMutation.isPending || !provider.configured}>
                        {testMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <ShieldCheck className="mr-2 h-4 w-4" />}
                        Test connection
                    </Button>
                </div>
            </CardContent>
        </Card>
    )
}

export function AiProvidersPage() {
    const providersQuery = useQuery({
        queryKey: ['llm-providers'],
        queryFn: getLlmProviders,
    })

    const providers = useMemo(() => providersQuery.data?.providers ?? [], [providersQuery.data?.providers])

    if (providersQuery.isLoading) {
        return (
            <div className="space-y-4">
                <Skeleton className="h-44 w-full" />
                <Skeleton className="h-44 w-full" />
            </div>
        )
    }

    if (providersQuery.isError) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>AI providers unavailable</CardTitle>
                    <CardDescription>{(providersQuery.error as Error).message}</CardDescription>
                </CardHeader>
            </Card>
        )
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>AI providers</CardTitle>
                    <CardDescription>
                        Configure and activate LLM providers for the AI assistant and LLM chat. Raw keys are never displayed after saving.
                    </CardDescription>
                </CardHeader>
            </Card>

            <div className="grid gap-4">
                {providers.map((provider) => (
                    <ProviderCard key={provider.provider} provider={provider} />
                ))}
            </div>
        </div>
    )
}
