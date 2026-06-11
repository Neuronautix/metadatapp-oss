import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    getConnectedApp,
    pushConnectedApp,
    syncConnectedApp,
    testBioPortalConnection,
    testCedarConnection,
    testElabftwConnection,
    testFair3rConnection,
    testOsfConnection,
    testPreclinicalTrialsConnection,
    testProtocolsIoConnection,
} from './connected-apps.api.ts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { formatDateTime } from '@/lib/format.ts'
import {
    ChevronLeft,
    Network,
    ShieldCheck,
    Activity,
    ExternalLink,
    PawPrint,
    Boxes,
    FlaskConical,
    Database,
} from 'lucide-react'
import { useFeatureFlag } from '@/feature-flags/useFeatureFlag.ts'
import { useToast } from '@/components/ui/toast.tsx'
import { ConnectedAppConfigDialog } from './components/ConnectedAppConfigDialog.tsx'
import { DVCIntegrationTasks } from '../tecniplast/dvc/DVCIntegrationTasks.tsx'
import { Fair3rSyncPanel } from './components/Fair3rSyncPanel.tsx'
import { Fair3rLiveDemoPanel } from './components/Fair3rLiveDemoPanel.tsx'
import { LiveSensorPanel } from '@/features/demo-sensors/LiveSensorPanel.tsx'
import { resolveConnectedAppLogoUrl } from './connected-apps.logos.ts'
import type { AppCode } from './connected-apps.types.ts'

type SyncCapability = {
    label: string
    description: string
    imports: string[]
    warning?: string
}

const inboundSyncCapabilities: Partial<Record<AppCode, SyncCapability>> = {
    elabftw: {
        label: 'Import eLabFTW Experiments',
        description: 'Pulls eLabFTW experiments into Metadatapp studies.',
        imports: ['Experiments'],
    },
    osf: {
        label: 'Import OSF Projects',
        description: 'Pulls OSF projects and repository links into Metadatapp studies/resources.',
        imports: ['Studies', 'Connected resource links'],
    },
    preclinicaltrials: {
        label: 'Import Published Protocols',
        description: 'Pulls published, non-embargoed PreclinicalTrials.eu protocols into investigations.',
        imports: ['Investigations'],
    },
    tecniplast: {
        label: 'Import DVC Metrics',
        description: 'Pulls Tecniplast metric definitions into Metadatapp variables. Use the DVC task panel for animal/cage data imports.',
        imports: ['Variables'],
    },
}

const appSpecificNotes: Partial<Record<AppCode, string[]>> = {
    fair3r: ['Outbound repository publication is handled by the FAIR3R schema exchange panel below.'],
    protocolio: ['Only live connection testing is implemented for protocols.io right now. Protocol import is not wired yet.'],
    cedar: ['Only live API-key validation is implemented for CEDAR right now. Template import is not wired yet.'],
    bioportal: ['Only live API-key validation is implemented for BioPortal right now. Ontology import is not wired yet.'],
    sensor_agent: ['This is a read-only sensor proxy. Data appears through the live sensor panel, not through manual sync.'],
}

export function ConnectedAppDetailPage() {
    const { appId } = useParams<{ appId: string }>()
    const queryClient = useQueryClient()
    const { toast } = useToast()
    const deepDiveEnabled = useFeatureFlag('feature.connectedApps.deepDive')

    const [selectedFair3rDataset, setSelectedFair3rDataset] = useState<string | null>(null)

    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['connected-apps', appId],
        queryFn: () => getConnectedApp(appId!),
        enabled: !!appId,
    })

    const syncMutation = useMutation({
        mutationFn: () => syncConnectedApp(appId!),
        onSuccess: () => {
            toast({
                title: 'Synchronization started',
                description: 'The background synchronization task has been queued.',
            })
            // Optionally invalidate queries to refresh the lastSyncAt later
            queryClient.invalidateQueries({ queryKey: ['connected-apps', appId] })
        },
        onError: (err: Error) => {
            toast({
                title: 'Synchronization failed',
                description: err.message,
                variant: 'error',
            })
        },
    })

    const pushMutation = useMutation({
        mutationFn: () => pushConnectedApp(appId!),
        onSuccess: () => {
            toast({
                title: 'Push started',
                description: 'The outbound synchronization task has been queued.',
            })
            queryClient.invalidateQueries({ queryKey: ['connected-apps', appId] })
        },
        onError: (err: Error) => {
            toast({
                title: 'Push failed',
                description: err.message,
                variant: 'error',
            })
        },
    })

    const testConnectionMutation = useMutation({
        mutationFn: () => testElabftwConnection(appId!),
        onSuccess: (result) => {
            const name = [result.user.firstname, result.user.lastname].filter(Boolean).join(' ').trim() || result.user.email || `User ${result.user.userid}`
            toast({
                title: 'Connection valid',
                description: `Connected to ${result.externalUrl} as ${name}.`,
            })
        },
        onError: (err: Error) => {
            toast({
                title: 'Connection test failed',
                description: err.message,
                variant: 'error',
            })
        },
    })

    const testFair3rConnectionMutation = useMutation({
        mutationFn: () => testFair3rConnection(appId!),
        onSuccess: (result) => {
            toast({
                title: 'Connection valid',
                description: `Connected to ${result.externalUrl} — ${result.datasetsCount} dataset(s) found.`,
            })
        },
        onError: (err: Error) => {
            toast({
                title: 'Connection test failed',
                description: err.message,
                variant: 'error',
            })
        },
    })

    const testOsfConnectionMutation = useMutation({
        mutationFn: () => testOsfConnection(appId!),
        onSuccess: (result) => {
            const name = result.user.fullName || result.user.email || result.user.id || 'OSF user'
            toast({
                title: 'Connection valid',
                description: `Connected to ${result.externalUrl} as ${name}.`,
            })
        },
        onError: (err: Error) => {
            toast({
                title: 'Connection test failed',
                description: err.message,
                variant: 'error',
            })
        },
    })

    const testProtocolsIoConnectionMutation = useMutation({
        mutationFn: () => testProtocolsIoConnection(appId!),
        onSuccess: (result) => {
            const name = result.user.name || result.user.username || 'protocols.io user'
            toast({
                title: 'Connection valid',
                description: `Connected to ${result.externalUrl} as ${name}.`,
            })
        },
        onError: (err: Error) => {
            toast({
                title: 'Connection test failed',
                description: err.message,
                variant: 'error',
            })
        },
    })

    const testPreclinicalTrialsConnectionMutation = useMutation({
        mutationFn: () => testPreclinicalTrialsConnection(appId!),
        onSuccess: (result) => {
            toast({
                title: 'Connection valid',
                description: `Connected to ${result.externalUrl} — ${result.protocolsCount} protocol(s) found.`,
            })
        },
        onError: (err: Error) => {
            toast({
                title: 'Connection test failed',
                description: err.message,
                variant: 'error',
            })
        },
    })

    const testCedarConnectionMutation = useMutation({
        mutationFn: () => testCedarConnection(appId!),
        onSuccess: (result) => {
            toast({
                title: 'Connection valid',
                description: `Connected to ${result.externalUrl}.`,
            })
        },
        onError: (err: Error) => {
            toast({
                title: 'Connection test failed',
                description: err.message,
                variant: 'error',
            })
        },
    })

    const testBioPortalConnectionMutation = useMutation({
        mutationFn: () => testBioPortalConnection(appId!),
        onSuccess: (result) => {
            toast({
                title: 'Connection valid',
                description: `Connected to ${result.externalUrl}.`,
            })
        },
        onError: (err: Error) => {
            toast({
                title: 'Connection test failed',
                description: err.message,
                variant: 'error',
            })
        },
    })

    if (isLoading) {
        return (
            <div className="space-y-6">
                <Skeleton className="h-12 w-48" />
                <Card className="h-96 w-full" />
            </div>
        )
    }

    if (isError || !data) {
        return (
            <EmptyState
                title="Integration not found"
                description={error ? (error as Error).message : 'The requested integration could not be loaded.'}
                actionLabel="Back to integrations"
                onAction={() => window.history.back()}
            />
        )
    }

    const isSensorAgent = data.code === 'sensor_agent'
    const isElabftw = data.code === 'elabftw'
    const isFair3r = data.code === 'fair3r'
    const isOsf = data.code === 'osf'
    const isProtocolsIo = data.code === 'protocolio'
    const isPreclinicalTrials = data.code === 'preclinicaltrials'
    const isCedar = data.code === 'cedar'
    const isBioPortal = data.code === 'bioportal'
    const inboundSyncCapability = inboundSyncCapabilities[data.code]
    const supportsInboundSync = Boolean(inboundSyncCapability)
    const logoUrl = resolveConnectedAppLogoUrl(data.code, data.logoUrl)
    const notes = appSpecificNotes[data.code] ?? []

    return (
        <div className="space-y-6">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" asChild className="rounded-xl">
                    <Link to="/connected-apps">
                        <ChevronLeft className="h-5 w-5" />
                    </Link>
                </Button>
                <PageHeader
                    title={data.name}
                    subtitle={`Integration Code: ${data.code}`}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-6">
                    <Card>
                        <h3 className="font-display text-lg font-semibold">About this integration</h3>
                        <p className="mt-4 text-muted leading-relaxed">
                            {data.description || 'No description provided for this integration.'}
                        </p>

                        <div className="mt-8 grid gap-4 sm:grid-cols-2">
                            <div className="flex items-start gap-3 rounded-2xl border border-line p-4">
                                <div className="rounded-xl bg-blue-50 p-2 text-blue-600">
                                    <ShieldCheck className="h-5 w-5" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-ink">Status</p>
                                    <p className="text-xs text-muted mt-1">
                                        This integration is currently {data.isActive ? 'active' : 'inactive'}.
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3 rounded-2xl border border-line p-4">
                                <div className="rounded-xl bg-purple-50 p-2 text-purple-600">
                                    <Activity className="h-5 w-5" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-ink">Last Sync</p>
                                    <p className="text-xs text-muted mt-1">
                                        {data.lastSyncAt ? formatDateTime(data.lastSyncAt) : 'Never synchronized'}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {deepDiveEnabled && data.stats && (
                            <div className="mt-12 space-y-6">
                                <div className="flex items-center gap-3">
                                    <div className="h-px flex-1 bg-line"></div>
                                    <h4 className="text-xs font-bold uppercase tracking-widest text-muted">Deep Dive Data</h4>
                                    <div className="h-px flex-1 bg-line"></div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {data.stats.animalsCount !== undefined && (
                                        <div className="rounded-2xl bg-surface-2 p-5 border border-line shadow-sm">
                                            <div className="flex items-center justify-between">
                                                <PawPrint className="h-5 w-5 text-indigo-500" />
                                                <span className="text-2xl font-display font-bold text-ink">{data.stats.animalsCount}</span>
                                            </div>
                                            <p className="mt-2 text-sm font-medium text-muted">Tracked Animals</p>
                                            <p className="mt-1 text-xs text-muted">Total subjects linked to this integration</p>
                                        </div>
                                    )}
                                    {data.stats.cagesCount !== undefined && (
                                        <div className="rounded-2xl bg-surface-2 p-5 border border-line shadow-sm">
                                            <div className="flex items-center justify-between">
                                                <Boxes className="h-5 w-5 text-emerald-500" />
                                                <span className="text-2xl font-display font-bold text-ink">{data.stats.cagesCount}</span>
                                            </div>
                                            <p className="mt-2 text-sm font-medium text-muted">Active Cages</p>
                                            <p className="mt-1 text-xs text-muted">Currently mapped to tp-rooms</p>
                                        </div>
                                    )}
                                    {data.stats.studiesCount !== undefined && (
                                        <div className="rounded-2xl bg-surface-2 p-5 border border-line shadow-sm">
                                            <div className="flex items-center justify-between">
                                                <FlaskConical className="h-5 w-5 text-amber-500" />
                                                <span className="text-2xl font-display font-bold text-ink">{data.stats.studiesCount}</span>
                                            </div>
                                            <p className="mt-2 text-sm font-medium text-muted">Studies</p>
                                            <p className="mt-1 text-xs text-muted">Linked Elabftw notebooks</p>
                                        </div>
                                    )}
                                    {data.stats.datasetsCount !== undefined && (
                                        <div className="rounded-2xl bg-surface-2 p-5 border border-line shadow-sm">
                                            <div className="flex items-center justify-between">
                                                <Database className="h-5 w-5 text-blue-500" />
                                                <span className="text-2xl font-display font-bold text-ink">{data.stats.datasetsCount}</span>
                                            </div>
                                            <p className="mt-2 text-sm font-medium text-muted">Datasets</p>
                                            <p className="mt-1 text-xs text-muted">FAIR repositories published</p>
                                        </div>
                                    )}
                                    {data.stats.fairComplianceScore !== undefined && (
                                        <div className="rounded-2xl bg-surface-2 p-5 border border-line shadow-sm">
                                            <div className="flex items-center justify-between">
                                                <ShieldCheck className="h-5 w-5 text-purple-500" />
                                                <div className="flex items-baseline gap-1">
                                                    <span className="text-2xl font-display font-bold text-ink">{data.stats.fairComplianceScore}</span>
                                                    <span className="text-xs font-bold text-muted">%</span>
                                                </div>
                                            </div>
                                            <p className="mt-2 text-sm font-medium text-muted">FAIR Score</p>
                                            <div className="mt-2 h-1.5 w-full bg-line rounded-full overflow-hidden">
                                                <div className="h-full bg-purple-500" style={{ width: `${data.stats.fairComplianceScore}%` }}></div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </Card>

                    <Card className="border-line bg-surface-3">
                        <h3 className="font-display text-lg font-semibold text-ink">App-specific behavior</h3>
                        {inboundSyncCapability ? (
                            <div className="mt-4 space-y-4">
                                <p className="text-sm text-muted">{inboundSyncCapability.description}</p>
                                <div className="flex flex-wrap gap-2">
                                    {inboundSyncCapability.imports.map((item) => (
                                        <Badge key={item} variant="outline">{item}</Badge>
                                    ))}
                                </div>
                                {inboundSyncCapability.warning && (
                                    <p className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                        {inboundSyncCapability.warning}
                                    </p>
                                )}
                            </div>
                        ) : (
                            <p className="mt-4 text-sm text-muted">
                                This integration does not currently expose a generic inbound synchronization action.
                            </p>
                        )}
                        {notes.length > 0 && (
                            <div className="mt-4 space-y-2">
                                {notes.map((note) => (
                                    <p key={note} className="rounded-xl border border-line bg-white px-3 py-2 text-xs text-muted">
                                        {note}
                                    </p>
                                ))}
                            </div>
                        )}
                    </Card>

                    {data.code === 'tecniplast' && (
                        <Card className="border-indigo-100 bg-indigo-50/30">
                            <DVCIntegrationTasks appId={appId!} tokenHint={data.tokenHint} />
                        </Card>
                    )}

                    {isSensorAgent && (
                        <LiveSensorPanel
                            title="Sovereign Sensor Agent"
                            subtitle="Live Raspberry Pi readings proxied through Metadatapp for the demo."
                        />
                    )}

                    {isFair3r && (
                        <Card className="bg-surface-3 border-line">
                            <div className="flex items-center gap-3 mb-5">
                                <img src="/fair3r-logo.png" alt="FAIR3R" className="h-8 w-auto object-contain" />
                                <h3 className="font-display text-lg font-semibold text-ink">FAIR3R Schema Exchange</h3>
                            </div>
                            <div className="mb-5">
                                <Fair3rLiveDemoPanel appId={appId!} externalUrl={data.externalUrl} datasetName={selectedFair3rDataset ?? undefined} />
                            </div>
                            <Fair3rSyncPanel appId={appId!} externalUrl={data.externalUrl} onDatasetSelect={setSelectedFair3rDataset} />
                        </Card>
                    )}

                </div>

                <div className="space-y-6">
                    <Card className="bg-surface-3 border-line shadow-panel">
                        <div className="flex items-center gap-4 mb-6">
                            <div className="rounded-xl flex-shrink-0 bg-surface p-2 flex items-center justify-center w-14 h-14 overflow-hidden border border-line">
                                {logoUrl ? (
                                    <img src={logoUrl} alt={data.name} className="object-contain w-full h-full" />
                                ) : isFair3r ? (
                                    <img src="/fair3r-logo.png" alt="FAIR3R" className="object-contain w-full h-full p-1" />
                                ) : (
                                    <Network className="h-6 w-6 text-muted" />
                                )}
                            </div>
                            <h3 className="font-display text-lg font-semibold text-ink">Configuration</h3>
                        </div>

                        <div className="space-y-4">
                            <div className="flex items-center justify-between text-sm py-2 border-b border-line">
                                <span className="text-muted">Application</span>
                                <span className="font-mono text-xs text-ink">{data.code}</span>
                            </div>
                            <div className="flex items-center justify-between text-sm py-2 border-b border-line">
                                <span className="text-muted">Last Checked</span>
                                <span className="text-ink">{isSensorAgent ? 'Read-only proxy' : data.lastSyncAt ? 'Online' : 'Pending'}</span>
                            </div>
                        </div>

                        <div className="mt-8 space-y-3">
                            {supportsInboundSync && (
                                <Button
                                    className="w-full"
                                    onClick={() => syncMutation.mutate()}
                                    disabled={syncMutation.isPending}
                                >
                                    {syncMutation.isPending ? 'Synchronizing...' : inboundSyncCapability?.label}
                                </Button>
                            )}
                            {isElabftw && (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => testConnectionMutation.mutate()}
                                    disabled={testConnectionMutation.isPending}
                                >
                                    {testConnectionMutation.isPending ? 'Testing...' : 'Test Connection'}
                                </Button>
                            )}
                            {isElabftw && (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => pushMutation.mutate()}
                                    disabled={pushMutation.isPending}
                                >
                                    {pushMutation.isPending ? 'Pushing...' : 'Push to eLabFTW'}
                                </Button>
                            )}
                            {isFair3r && (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => testFair3rConnectionMutation.mutate()}
                                    disabled={testFair3rConnectionMutation.isPending}
                                >
                                    {testFair3rConnectionMutation.isPending ? 'Testing...' : 'Test Connection'}
                                </Button>
                            )}
                            {isOsf && (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => testOsfConnectionMutation.mutate()}
                                    disabled={testOsfConnectionMutation.isPending}
                                >
                                    {testOsfConnectionMutation.isPending ? 'Testing...' : 'Test Connection'}
                                </Button>
                            )}
                            {isProtocolsIo && (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => testProtocolsIoConnectionMutation.mutate()}
                                    disabled={testProtocolsIoConnectionMutation.isPending}
                                >
                                    {testProtocolsIoConnectionMutation.isPending ? 'Testing...' : 'Test Connection'}
                                </Button>
                            )}
                            {isPreclinicalTrials && (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => testPreclinicalTrialsConnectionMutation.mutate()}
                                    disabled={testPreclinicalTrialsConnectionMutation.isPending}
                                >
                                    {testPreclinicalTrialsConnectionMutation.isPending ? 'Testing...' : 'Test Connection'}
                                </Button>
                            )}
                            {isCedar && (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => testCedarConnectionMutation.mutate()}
                                    disabled={testCedarConnectionMutation.isPending}
                                >
                                    {testCedarConnectionMutation.isPending ? 'Testing...' : 'Test Connection'}
                                </Button>
                            )}
                            {isBioPortal && (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => testBioPortalConnectionMutation.mutate()}
                                    disabled={testBioPortalConnectionMutation.isPending}
                                >
                                    {testBioPortalConnectionMutation.isPending ? 'Testing...' : 'Test Connection'}
                                </Button>
                            )}
                            {data.externalUrl && (
                                <Button variant="outline" className="w-full" asChild>
                                    <a href={data.externalUrl} target="_blank" rel="noopener noreferrer">
                                        <ExternalLink className="mr-2 h-4 w-4" />
                                        Open {data.name}
                                    </a>
                                </Button>
                            )}
                            {!isSensorAgent ? (
                                <ConnectedAppConfigDialog
                                    appId={appId!}
                                    appName={data.name}
                                    appCode={data.code}
                                    tokenHint={data.tokenHint}
                                    authenticationParameterHints={data.authenticationParameterHints}
                                    externalUrl={data.externalUrl}
                                />
                            ) : (
                                <p className="rounded-2xl border border-line px-4 py-3 text-xs text-muted">
                                    Sensor upstream credentials stay server-side. This integration is strictly read-only.
                                </p>
                            )}
                        </div>

                    </Card>

                    <Card>
                        <h4 className="font-display font-semibold text-ink">Security & Access</h4>
                        <p className="mt-2 text-xs text-muted">
                            Access to this integration is managed by your account administrator.
                        </p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {(() => {
                                const securityBadges: Record<string, string[]> = {
                                    fair3r: ['Access Token'],
                                    osf: ['Access Token'],
                                    elabftw: ['API Key'],
                                    tecniplast: ['Access Token', 'Refresh Token'],
                                    protocolio: ['Access Token'],
                                    preclinicaltrials: ['Public API'],
                                    cedar: ['API Key'],
                                    bioportal: ['API Key'],
                                    sensor_agent: ['Read-only'],
                                }
                                const badges = securityBadges[data.code] ?? ['OAuth2', 'API Key']
                                return badges.map(b => <Badge key={b} variant="outline">{b}</Badge>)
                            })()}
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    )
}
