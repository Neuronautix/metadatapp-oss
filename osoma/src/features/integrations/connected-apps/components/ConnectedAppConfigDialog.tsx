import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { updateConnectedApp } from '../connected-apps.api.ts'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Label } from '@/components/ui/label.tsx'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { KeyRound, Settings } from 'lucide-react'
import type { AppCode } from '../connected-apps.types.ts'

interface ConnectedAppConfigDialogProps {
    appId: string
    appName: string
    appCode: AppCode
    tokenHint?: string
    authenticationParameterHints?: Record<string, string>
    externalUrl?: string
}

type CredentialField = {
    key: string
    label: string
    type?: 'password' | 'text'
    required?: boolean
    placeholder?: string
}

const credentialFieldsByApp: Partial<Record<AppCode, CredentialField[]>> = {
    softmouse: [
        { key: 'username', label: 'Username', type: 'text', required: true, placeholder: 'SoftMouse username' },
        { key: 'password', label: 'Password', required: true, placeholder: 'SoftMouse password' },
    ],
    elabftw: [
        { key: 'apiKey', label: 'API Key', required: true, placeholder: 'eLabFTW API key' },
    ],
    fair3r: [
        { key: 'accessToken', label: 'Access Token', required: true, placeholder: 'Fair3R access token' },
        { key: 'targetDatasetName', label: 'Target Dataset', type: 'text', placeholder: 'Existing CKAN dataset slug, e.g. damien-test-manuel' },
        { key: 'ownerOrg', label: 'Owner Organization', type: 'text', placeholder: 'Optional CKAN organization id or name' },
    ],
    osf: [
        { key: 'accessToken', label: 'Access Token', required: true, placeholder: 'OSF personal access token' },
    ],
    tecniplast: [
        { key: 'accessToken', label: 'Access Token', required: true, placeholder: 'Tecniplast DVC access token' },
        { key: 'refreshToken', label: 'Refresh Token', placeholder: 'Optional refresh token' },
    ],
    protocolio: [
        { key: 'accessToken', label: 'Access Token', required: true, placeholder: 'protocols.io access token' },
    ],
    preclinicaltrials: [],
    nih_cde: [],
    jax_phenome: [],
    cedar: [
        { key: 'apiKey', label: 'API Key', required: true, placeholder: 'CEDAR API key' },
    ],
    bioportal: [
        { key: 'apiKey', label: 'API Key', required: true, placeholder: 'BioPortal API key' },
    ],
}

const getCredentialFields = (appCode: AppCode): CredentialField[] => (
    credentialFieldsByApp[appCode] ?? [
        { key: 'apiKey', label: 'API Key', required: true, placeholder: 'API key or token' },
    ]
)

const externalUrlPlaceholderByApp: Partial<Record<AppCode, string>> = {
    softmouse: 'https://your-softmouse.example',
    elabftw: 'https://your-elabftw.example',
    fair3r: 'https://validation.fair3r.fr',
    osf: 'https://osf.io',
    protocolio: 'https://www.protocols.io',
    preclinicaltrials: 'https://preclinicaltrials.eu/api/external/viewable-protocols',
    nih_cde: 'https://cde.nlm.nih.gov/api',
    jax_phenome: 'https://phenome.jax.org',
    cedar: 'https://resource.metadatacenter.org',
    bioportal: 'https://data.bioontology.org',
    tecniplast: 'https://your-dvc-api.example',
}

const getExternalUrlHelpText = (appCode: AppCode): string => {
    if (appCode === 'elabftw') {
        return 'Base URL of the external app. For eLabFTW use the instance root, not `/api/v2`.'
    }

    if (appCode === 'osf') {
        return 'Base URL of the external app. For OSF, use `https://osf.io` or leave blank.'
    }

    if (appCode === 'preclinicaltrials') {
        return 'PreclinicalTrials.eu viewable protocols endpoint. Leave blank to use the public published/no-embargo endpoint.'
    }

    if (appCode === 'protocolio') {
        return 'Base URL of the external app. For protocols.io, leave blank to use the public API.'
    }

    if (appCode === 'cedar') {
        return 'CEDAR Resource API root. Leave blank to use the public CEDAR resource API.'
    }

    if (appCode === 'bioportal') {
        return 'BioPortal API root. Leave blank to use the public BioPortal API.'
    }

    if (appCode === 'nih_cde') {
        return 'NIH CDE API root. Leave blank to use the public NIH CDE repository API.'
    }

    if (appCode === 'jax_phenome') {
        return 'Mouse Phenome Database root. Leave blank to use the public JAX Phenome (MPD) API.'
    }

    return 'Base URL of the external app.'
}

export function ConnectedAppConfigDialog({
    appId,
    appName,
    appCode,
    tokenHint,
    authenticationParameterHints,
    externalUrl,
}: ConnectedAppConfigDialogProps) {
    const [open, setOpen] = useState(false)
    const [token, setToken] = useState('')
    const [credentialValues, setCredentialValues] = useState<Record<string, string>>({})
    const [url, setUrl] = useState(externalUrl ?? '')
    const queryClient = useQueryClient()
    const navigate = useNavigate()
    const { toast } = useToast()
    const credentialFields = getCredentialFields(appCode)
    const usesDedicatedCredentials = credentialFields.length > 0
    const externalUrlPlaceholder = externalUrlPlaceholderByApp[appCode] ?? 'https://external-app.example'

    const mutation = useMutation({
        mutationFn: async () => {
            const authenticationParameters = Object.fromEntries(
                credentialFields
                    .map((field) => [field.key, credentialValues[field.key]?.trim()] as const)
                    .filter(([, value]) => value)
            )

            return updateConnectedApp(appId, {
                token: usesDedicatedCredentials ? null : token || undefined,
                authenticationParameters: Object.keys(authenticationParameters).length > 0 ? authenticationParameters : undefined,
                externalUrl: url.trim() || undefined,
            })
        },
        onSuccess: (savedApp) => {
            toast({
                title: 'Settings updated',
                description: `Configuration for ${appName} has been saved.`,
            })
            setOpen(false)
            setToken('') // Clear sensitive data
            setCredentialValues({})
            setUrl(url.trim())
            queryClient.invalidateQueries({ queryKey: ['connected-apps', appId] })
            queryClient.invalidateQueries({ queryKey: ['connected-apps'] })
            if (savedApp.id && savedApp.id !== appId) {
                navigate(`/connected-apps/${savedApp.id}`, { replace: true })
            }
        },
        onError: (err: Error) => {
            toast({
                title: 'Failed to update settings',
                description: err.message,
                variant: 'error',
            })
        },
    })

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" className="w-full">
                    <Settings className="mr-2 h-4 w-4" />
                    Edit Settings
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Edit {appName} Settings</DialogTitle>
                    <DialogDescription>
                        Update the server-side connection details for this integration.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                    <div className="grid gap-2">
                        <Label htmlFor="external-url">External URL</Label>
                        <Input
                            id="external-url"
                            placeholder={externalUrlPlaceholder}
                            value={url}
                            className="bg-white/5"
                            onChange={(e) => setUrl(e.target.value)}
                        />
                        <p className="text-xs text-muted-foreground mt-1">
                            {getExternalUrlHelpText(appCode)}
                        </p>
                    </div>
                    {!usesDedicatedCredentials && (
                        <div className="grid gap-2">
                            <Label htmlFor="token">API Token / Password</Label>
                            <Input
                                id="token"
                                type="password"
                                placeholder={tokenHint ? `Currently set as ${tokenHint}` : 'Enter token or password...'}
                                value={token}
                                className="bg-white/5"
                                onChange={(e) => setToken(e.target.value)}
                            />
                            <p className="text-xs text-muted-foreground mt-1">
                                {tokenHint ? 'Leave blank to retain current token.' : 'Stored server-side and returned only as a masked hint.'}
                            </p>
                        </div>
                    )}
                    {appCode === 'preclinicaltrials' && (
                        <p className="rounded-xl border border-line bg-slate-50 px-3 py-2 text-xs text-muted-foreground">
                            This integration can be tested without credentials. Set only the API URL when testing a non-default endpoint.
                        </p>
                    )}
                    {credentialFields.length > 0 && (
                        <div className="rounded-xl border border-line bg-slate-50 p-3">
                            <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
                                <KeyRound className="h-4 w-4" />
                                App credentials
                            </div>
                            <div className="grid gap-3">
                                {credentialFields.map((field) => {
                                    const hint = authenticationParameterHints?.[field.key]

                                    return (
                                        <div key={field.key} className="grid gap-2">
                                            <Label htmlFor={`credential-${field.key}`}>
                                                {field.label}{field.required ? ' *' : ''}
                                            </Label>
                                            <Input
                                                id={`credential-${field.key}`}
                                                type={field.type ?? 'password'}
                                                placeholder={hint ? `Currently set as ${hint}` : field.placeholder}
                                                value={credentialValues[field.key] ?? ''}
                                                className="bg-white"
                                                autoComplete="off"
                                                onChange={(e) => setCredentialValues((previous) => ({
                                                    ...previous,
                                                    [field.key]: e.target.value,
                                                }))}
                                            />
                                        </div>
                                    )
                                })}
                            </div>
                            <p className="mt-3 text-xs text-muted-foreground">
                                Leave existing credential fields blank to keep the stored value. Saved secrets are returned only as masked hints.
                            </p>
                        </div>
                    )}
                </div>
                <DialogFooter>
                    <Button
                        type="submit"
                        onClick={() => mutation.mutate()}
                        disabled={mutation.isPending}
                    >
                        {mutation.isPending ? 'Saving...' : 'Save changes'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
