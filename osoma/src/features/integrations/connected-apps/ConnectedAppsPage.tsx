import { useQuery } from '@tanstack/react-query'
import { getConnectedApps } from './connected-apps.api.ts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { formatDateTime } from '@/lib/format.ts'
import { Link } from 'react-router-dom'
import { Network, CheckCircle2, XCircle, Clock, PawPrint, Boxes, FlaskConical, Database } from 'lucide-react'
import { useFeatureFlag } from '@/feature-flags/useFeatureFlag.ts'
import { resolveConnectedAppLogoUrl } from './connected-apps.logos.ts'

export function ConnectedAppsPage() {
    const deepDiveEnabled = useFeatureFlag('feature.connectedApps.deepDive')
    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['connected-apps'],
        queryFn: getConnectedApps,
    })

    return (
        <div className="space-y-6">
            <PageHeader
                title="Connected Applications"
                subtitle="Manage external integrations and synchronization services."
            />

            {isLoading ? (
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {Array.from({ length: 3 }).map((_, i) => (
                        <Skeleton key={i} className="h-48 w-full rounded-2xl" />
                    ))}
                </div>
            ) : isError ? (
                <EmptyState
                    title="Failed to load connected apps"
                    description={(error as Error).message}
                />
            ) : data?.['hydra:member']?.length === 0 ? (
                <EmptyState
                    title="No integrations configured"
                    description="Connect your first external application to start synchronizing data."
                />
            ) : (
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {data?.['hydra:member']?.map((app) => {
                        const logoUrl = resolveConnectedAppLogoUrl(app.code, app.logoUrl)

                        return (
                        <Card key={app.id} className="group relative flex flex-col overflow-hidden transition-all hover:border-brand/30 hover:shadow-subtle">
                            <div className="flex flex-1 flex-col p-6">
                                <div className="flex items-start justify-between">
                                    <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-slate-50 p-2 text-slate-600 transition-colors group-hover:bg-brand/10 group-hover:text-brand">
                                        {logoUrl ? (
                                            <img src={logoUrl} alt={app.name} className="h-full w-full object-contain" />
                                        ) : app.code === 'fair3r' ? (
                                            <img src="/fair3r-logo.png" alt="FAIR3R" className="h-full w-full object-contain" />
                                        ) : (
                                            <Network className="h-6 w-6" />
                                        )}
                                    </div>
                                    <Badge variant={app.isActive ? 'default' : 'outline'} className="capitalize">
                                        {app.isActive ? (
                                            <CheckCircle2 className="mr-1 h-3 w-3" />
                                        ) : (
                                            <XCircle className="mr-1 h-3 w-3" />
                                        )}
                                        {app.isActive ? 'Active' : 'Inactive'}
                                    </Badge>
                                </div>

                                <div className="mt-4">
                                    <h3 className="font-display text-lg font-semibold text-slate-900">{app.name}</h3>
                                    <p className="mt-1 text-sm text-slate-500 line-clamp-2">{app.description}</p>
                                </div>

                                {deepDiveEnabled && app.stats && (
                                    <div className="mt-6 grid grid-cols-2 gap-4 border-t border-line pt-4">
                                        {app.stats.animalsCount !== undefined && (
                                            <div className="flex items-center gap-2">
                                                <PawPrint className="h-4 w-4 text-slate-400" />
                                                <span className="text-xs font-semibold text-slate-700">{app.stats.animalsCount} Animals</span>
                                            </div>
                                        )}
                                        {app.stats.studiesCount !== undefined && (
                                            <div className="flex items-center gap-2">
                                                <FlaskConical className="h-4 w-4 text-slate-400" />
                                                <span className="text-xs font-semibold text-slate-700">{app.stats.studiesCount} Expts</span>
                                            </div>
                                        )}
                                        {app.stats.datasetsCount !== undefined && (
                                            <div className="flex items-center gap-2">
                                                <Database className="h-4 w-4 text-slate-400" />
                                                <span className="text-xs font-semibold text-slate-700">{app.stats.datasetsCount} Datasets</span>
                                            </div>
                                        )}
                                        {app.stats.cagesCount !== undefined && (
                                            <div className="flex items-center gap-2">
                                                <Boxes className="h-4 w-4 text-slate-400" />
                                                <span className="text-xs font-semibold text-slate-700">{app.stats.cagesCount} Cages</span>
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div className="mt-auto pt-6">
                                    <div className="flex items-center text-xs text-slate-400">
                                        <Clock className="mr-1 h-3 w-3" />
                                        {app.lastSyncAt ? (
                                            <span>Last sync: {formatDateTime(app.lastSyncAt)}</span>
                                        ) : (
                                            <span>Never synced</span>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <Link
                                to={`/connected-apps/${app.id}`}
                                className="absolute inset-0 z-10"
                                aria-label={`View details for ${app.name}`}
                            />
                        </Card>
                        )
                    })}
                </div>
            )}
        </div>
    )
}
