import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import {
    Activity,
    ArrowRight,
    CheckCircle2,
    CircleDot,
    Database,
    ExternalLink,
    FlaskConical,
    GitBranch,
    Network,
    Radio,
    ShieldCheck,
    Sparkles,
    XCircle,
} from 'lucide-react'
import { getConnectedApps } from './connected-apps.api.ts'
import { mergeConnectedAppShowcases, metadatappNetConnectedAppsContent } from './connected-apps.showcase.ts'
import { resolveConnectedAppLogoUrl } from './connected-apps.logos.ts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { formatDateTime } from '@/lib/format.ts'
import { cn } from '@/lib/utils.ts'

const directionStyle = {
    Inbound: 'border-blue-200 bg-blue-50 text-blue-700',
    Outbound: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    Bidirectional: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    Validation: 'border-amber-200 bg-amber-50 text-amber-700',
    Proxy: 'border-teal-200 bg-teal-50 text-teal-700',
}

const metricIconClass = 'h-4 w-4 text-slate-500'

export function ConnectedAppsShowcasePage() {
    const { data, isLoading } = useQuery({
        queryKey: ['connected-apps'],
        queryFn: getConnectedApps,
    })

    const liveApps = data?.['hydra:member'] ?? []
    const showcaseRows = mergeConnectedAppShowcases(liveApps)
    const activeCount = showcaseRows.filter(({ app }) => app?.isActive).length
    const implementedCount = showcaseRows.length
    const synchronizedCount = showcaseRows.filter(({ app }) => Boolean(app?.lastSyncAt)).length
    const visibleFlowCount = showcaseRows.length

    return (
        <div className="space-y-6">
            <PageHeader
                title="Connected Apps Showcase"
                subtitle="A live map of how external research systems communicate with Metadatapp."
                actions={
                    <Button asChild variant="outline">
                        <Link to="/connected-apps">
                            <Network className="mr-2 h-4 w-4" />
                            Manage apps
                        </Link>
                    </Button>
                }
            />

            <section className="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,0.9fr)]">
                <Card className="overflow-hidden border-slate-200 bg-slate-950 p-0 text-white">
                    <div className="grid min-h-[480px] gap-6 p-6 lg:grid-cols-[minmax(280px,0.95fr)_minmax(0,1.2fr)]">
                        <div className="flex flex-col justify-between gap-6">
                            <div>
                                <Badge className="border-teal-300/40 bg-teal-300/15 text-teal-100" variant="outline">
                                    Live integration graph
                                </Badge>
                                <h3 className="mt-5 max-w-xl font-display text-3xl font-semibold leading-tight text-white">
                                    Metadatapp is the hub between lab operations, protocols, datasets, repositories, and FAIR evidence.
                                </h3>
                                <p className="mt-4 max-w-lg text-sm leading-6 text-slate-300">
                                    Every connection is mediated by the backend, preserving credentials, source identifiers, and provenance while Osoma shows the current operating state.
                                </p>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                                <div className="rounded-2xl border border-white/10 bg-white/10 p-4">
                                    <p className="text-2xl font-semibold text-white">{implementedCount}</p>
                                    <p className="mt-1 text-xs uppercase tracking-wide text-slate-400">known connectors</p>
                                </div>
                                <div className="rounded-2xl border border-white/10 bg-white/10 p-4">
                                    <p className="text-2xl font-semibold text-emerald-200">{activeCount}</p>
                                    <p className="mt-1 text-xs uppercase tracking-wide text-slate-400">active now</p>
                                </div>
                                <div className="rounded-2xl border border-white/10 bg-white/10 p-4">
                                    <p className="text-2xl font-semibold text-blue-200">{synchronizedCount}</p>
                                    <p className="mt-1 text-xs uppercase tracking-wide text-slate-400">with sync history</p>
                                </div>
                            </div>
                        </div>

                        <div className="relative flex min-h-[420px] items-center justify-center">
                            <div className="absolute inset-6 rounded-full border border-white/10" />
                            <div className="absolute inset-16 rounded-full border border-white/10" />
                            <div className="z-10 flex h-44 w-44 flex-col items-center justify-center rounded-full border border-teal-300/50 bg-teal-300/15 text-center shadow-2xl shadow-teal-950/30">
                                <Network className="h-8 w-8 text-teal-100" />
                                <p className="mt-3 font-display text-xl font-semibold">Metadatapp</p>
                                <p className="mt-1 px-4 text-xs text-teal-100/80">FAIR metadata graph</p>
                            </div>
                            <div className="absolute inset-0">
                                {showcaseRows.map(({ showcase, app }, index) => {
                                    const angle = (index / visibleFlowCount) * Math.PI * 2 - Math.PI / 2
                                    const x = 50 + Math.cos(angle) * 42
                                    const y = 50 + Math.sin(angle) * 42
                                    const logoUrl = resolveConnectedAppLogoUrl(showcase.code, app?.logoUrl)

                                    return (
                                        <Link
                                            key={showcase.code}
                                            to={`/connected-apps/${app?.id ?? showcase.code}`}
                                            className="group absolute flex h-16 w-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-2xl border border-white/15 bg-white p-2 shadow-lg transition hover:scale-105"
                                            style={{ left: `${x}%`, top: `${y}%` }}
                                            aria-label={`Open ${showcase.name}`}
                                        >
                                            {logoUrl ? (
                                                <img src={logoUrl} alt="" className="max-h-full max-w-full object-contain" />
                                            ) : (
                                                <Radio className="h-7 w-7 text-slate-600" />
                                            )}
                                            <span
                                                className={cn(
                                                    'absolute -right-1 -top-1 h-3.5 w-3.5 rounded-full border-2 border-slate-950',
                                                    app?.isActive ? 'bg-emerald-400' : 'bg-slate-300'
                                                )}
                                            />
                                        </Link>
                                    )
                                })}
                            </div>
                        </div>
                    </div>
                </Card>

                <Card className="border-line bg-surface-3">
                    <div className="flex items-center gap-3">
                        <div className="rounded-xl bg-emerald-50 p-2 text-emerald-700">
                            <Sparkles className="h-5 w-5" />
                        </div>
                        <div>
                            <h3 className="font-display text-lg font-semibold text-ink">metadatapp.net content</h3>
                            <p className="text-xs text-muted">Reusable message for the interactive website.</p>
                        </div>
                    </div>
                    <h4 className="mt-6 font-display text-2xl font-semibold leading-tight text-ink">
                        {metadatappNetConnectedAppsContent.headline}
                    </h4>
                    <p className="mt-3 text-sm leading-6 text-muted">
                        {metadatappNetConnectedAppsContent.subheadline}
                    </p>
                    <div className="mt-6 space-y-3">
                        {metadatappNetConnectedAppsContent.valueProps.map((item) => (
                            <div key={item} className="flex gap-3 rounded-2xl border border-line bg-surface p-3">
                                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                <p className="text-sm text-slate-700">{item}</p>
                            </div>
                        ))}
                    </div>
                </Card>
            </section>

            <section className="grid gap-4 md:grid-cols-3">
                <Card className="flex items-start gap-4">
                    <div className="rounded-xl bg-blue-50 p-2 text-blue-700">
                        <Database className="h-5 w-5" />
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-ink">Source systems stay authoritative</p>
                        <p className="mt-1 text-sm text-muted">External IDs and links remain attached to imported records.</p>
                    </div>
                </Card>
                <Card className="flex items-start gap-4">
                    <div className="rounded-xl bg-emerald-50 p-2 text-emerald-700">
                        <ShieldCheck className="h-5 w-5" />
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-ink">Backend mediated by design</p>
                        <p className="mt-1 text-sm text-muted">Osoma talks to Metadatapp; external apps remain behind backend boundaries.</p>
                    </div>
                </Card>
                <Card className="flex items-start gap-4">
                    <div className="rounded-xl bg-amber-50 p-2 text-amber-700">
                        <GitBranch className="h-5 w-5" />
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-ink">FAIR workflows are connected</p>
                        <p className="mt-1 text-sm text-muted">Protocols, subjects, sensors, and datasets feed reporting and export.</p>
                    </div>
                </Card>
            </section>

            {isLoading ? (
                <div className="grid gap-4 lg:grid-cols-2">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <Skeleton key={index} className="h-64 w-full rounded-2xl" />
                    ))}
                </div>
            ) : (
                <section className="grid gap-4 lg:grid-cols-2">
                    {showcaseRows.map(({ showcase, app }) => {
                        const logoUrl = resolveConnectedAppLogoUrl(showcase.code, app?.logoUrl)

                        return (
                            <Card key={showcase.code} className="flex flex-col gap-5">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div className="flex min-w-0 items-center gap-4">
                                        <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-line bg-white p-2">
                                            {logoUrl ? (
                                                <img src={logoUrl} alt="" className="h-full w-full object-contain" />
                                            ) : (
                                                <Radio className="h-7 w-7 text-muted" />
                                            )}
                                        </div>
                                        <div className="min-w-0">
                                            <h3 className="font-display text-lg font-semibold text-ink">{showcase.name}</h3>
                                            <p className="text-sm text-muted">{showcase.role}</p>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="outline" className={directionStyle[showcase.direction]}>
                                            {showcase.direction}
                                        </Badge>
                                        <Badge variant={app?.isActive ? 'default' : 'outline'}>
                                            {app?.isActive ? (
                                                <CheckCircle2 className="mr-1 h-3 w-3" />
                                            ) : (
                                                <XCircle className="mr-1 h-3 w-3" />
                                            )}
                                            {app?.isActive ? 'Active' : 'Ready'}
                                        </Badge>
                                    </div>
                                </div>

                                <p className="text-sm leading-6 text-slate-600">{showcase.summary}</p>

                                <div className="rounded-2xl border border-line bg-surface-2 p-4">
                                    <div className="flex items-start gap-3">
                                        <Activity className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                        <div>
                                            <p className="text-xs font-semibold uppercase tracking-wide text-muted">Live information flow</p>
                                            <p className="mt-1 text-sm text-ink">{showcase.liveSignal}</p>
                                            <p className="mt-2 text-xs text-muted">
                                                {app?.lastSyncAt ? `Last sync: ${formatDateTime(app.lastSyncAt)}` : 'No sync timestamp yet'}
                                            </p>
                                            {app?.stats ? (
                                                <div className="mt-3 flex flex-wrap gap-2">
                                                    {Object.entries(app.stats)
                                                        .filter(([, value]) => value !== undefined)
                                                        .map(([key, value]) => (
                                                            <Badge key={key} variant="outline" className="bg-white">
                                                                {String(value)} {key.replace(/Count|Score/g, '').replace(/[A-Z]/g, ' $&').trim()}
                                                            </Badge>
                                                        ))}
                                                </div>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-wide text-muted">Metadatapp objects</p>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {showcase.metadatappObjects.map((item) => (
                                                <Badge key={item} variant="outline">{item}</Badge>
                                            ))}
                                        </div>
                                    </div>
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-wide text-muted">Relevant features</p>
                                        <ul className="mt-2 space-y-2">
                                            {showcase.features.map((feature) => (
                                                <li key={feature} className="flex gap-2 text-sm text-slate-600">
                                                    <CircleDot className={cn(metricIconClass, 'mt-0.5 h-3.5 w-3.5 shrink-0')} />
                                                    <span>{feature}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>

                                <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                                    <p className="max-w-xl text-sm text-muted">{showcase.websiteAngle}</p>
                                    <Button asChild variant="ghost" className="shrink-0">
                                        <Link to={`/connected-apps/${app?.id ?? showcase.code}`}>
                                            Open
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </Card>
                        )
                    })}
                </section>
            )}

            <Card className="border-line bg-surface-3">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 className="font-display text-lg font-semibold text-ink">Interactive website storyboards</h3>
                        <p className="mt-1 text-sm text-muted">Use these as scenes or toggles on metadatapp.net.</p>
                    </div>
                    <Badge variant="outline">
                        <FlaskConical className="mr-1 h-3 w-3" />
                        Website-ready
                    </Badge>
                </div>
                <div className="mt-5 grid gap-3 md:grid-cols-3">
                    {metadatappNetConnectedAppsContent.interactivePrompts.map((prompt) => (
                        <div key={prompt} className="rounded-2xl border border-line bg-surface p-4">
                            <ExternalLink className="h-4 w-4 text-slate-500" />
                            <p className="mt-3 text-sm font-medium leading-6 text-ink">{prompt}</p>
                        </div>
                    ))}
                </div>
            </Card>
        </div>
    )
}
