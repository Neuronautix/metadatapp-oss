import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { StatCard } from '@/components/StatCard.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { getZefishAlerts, getZefixOverview } from '@/domain/zefish/zefish.api.ts'
import { asAlertSeverityVariant } from '@/domain/zefish/zefish.utils.ts'
import { formatDateTime } from '@/lib/format.ts'

const dateTick = (value: string) =>
    new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short' }).format(new Date(value))

export function ZefixDashboard() {
    const overviewQuery = useQuery({
        queryKey: ['zefix', 'overview'],
        queryFn: getZefixOverview,
    })

    const alertsQuery = useQuery({
        queryKey: ['zefix', 'alerts', 'active'],
        queryFn: () => getZefishAlerts('active'),
    })

    if (overviewQuery.isLoading || alertsQuery.isLoading) {
        return (
            <div className="space-y-6">
                <Skeleton className="h-12 w-96" />
                <div className="grid gap-4 md:grid-cols-5">
                    {Array.from({ length: 5 }).map((_, index) => (
                        <Skeleton key={index} className="h-32" />
                    ))}
                </div>
            </div>
        )
    }

    if (overviewQuery.isError || alertsQuery.isError || !overviewQuery.data) {
        return (
            <EmptyState
                title="ZEFIX dashboard unavailable"
                description={overviewQuery.error?.message ?? alertsQuery.error?.message ?? 'Unable to load ZEFIX overview.'}
            />
        )
    }

    const overview = overviewQuery.data
    const activeAlerts = alertsQuery.data ?? []

    return (
        <div className="space-y-6">
            <PageHeader
                title="Zebrafish / ZEFIX"
                subtitle="Centralized colony monitoring, lineage tracking, and facility-wide operational awareness."
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link to="/zefix/location">Open location explorer</Link>
                        </Button>
                        <Button asChild>
                            <Link to="/zefix/alerts">Open alert panel</Link>
                        </Button>
                    </div>
                }
            />

            <div className="grid gap-4 md:grid-cols-5">
                <StatCard title="Active lines" value={overview.totalLines.toLocaleString()} helper="Genetic lines currently in service" />
                <StatCard title="Total batches" value={overview.totalBatches.toLocaleString()} helper="Breeding lots monitored" />
                <StatCard title="Total fish" value={overview.totalFish.toLocaleString()} helper="Live fish across facility" />
                <StatCard title="Today's mortalities" value={overview.todaysMortalities.toLocaleString()} helper="Last 24h mortality events" />
                <StatCard title="Active alerts" value={activeAlerts.length.toLocaleString()} helper="Info, warning, and critical" />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Population by line</CardTitle>
                        <CardDescription>Real-time fish census split by genetic line.</CardDescription>
                    </CardHeader>
                    <CardContent className="h-72">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={overview.populationByLine}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="line" tick={{ fontSize: 11 }} interval={0} angle={-22} textAnchor="end" height={70} />
                                <YAxis />
                                <Tooltip />
                                <Bar dataKey="fish" fill="#2563eb" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Mortality trend</CardTitle>
                        <CardDescription>Daily mortality count across the last 14 days.</CardDescription>
                    </CardHeader>
                    <CardContent className="h-72">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={overview.mortalityTrend}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="date" tickFormatter={dateTick} />
                                <YAxis />
                                <Tooltip labelFormatter={(value) => formatDateTime(String(value))} />
                                <Line type="monotone" dataKey="mortalities" stroke="#dc2626" strokeWidth={2.5} dot={{ r: 3 }} />
                            </LineChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Temperature trend (SYS-02)</CardTitle>
                        <CardDescription>Detect drift before welfare impact escalates.</CardDescription>
                    </CardHeader>
                    <CardContent className="h-72">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={overview.temperatureTrend}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="timestamp" tickFormatter={dateTick} />
                                <YAxis domain={[26, 'auto']} />
                                <Tooltip labelFormatter={(value) => formatDateTime(String(value))} />
                                <Line type="monotone" dataKey="temperature" stroke="#f59e0b" strokeWidth={2.5} dot={false} />
                            </LineChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Breeding output</CardTitle>
                        <CardDescription>Monthly batch births used for production planning.</CardDescription>
                    </CardHeader>
                    <CardContent className="h-72">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={overview.breedingOutput}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="period" />
                                <YAxis />
                                <Tooltip />
                                <Bar dataKey="births" fill="#16a34a" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Active alert spotlight</CardTitle>
                    <CardDescription>Most recent unresolved alerts requiring operator action.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {activeAlerts.length ? (
                        activeAlerts.slice(0, 4).map((alert) => (
                            <div key={alert.alertID} className="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-line bg-surface-2 p-3">
                                <div>
                                    <p className="font-semibold text-slate-800">{alert.type}</p>
                                    <p className="text-xs text-slate-500">{alert.affectedLabel} · {formatDateTime(alert.timestamp)}</p>
                                    <p className="mt-1 text-xs text-slate-500">{alert.recommendedAction}</p>
                                </div>
                                <Badge variant={asAlertSeverityVariant(alert.severity)}>{alert.severity}</Badge>
                            </div>
                        ))
                    ) : (
                        <p className="text-sm text-slate-500">No active alerts.</p>
                    )}
                </CardContent>
            </Card>
        </div>
    )
}
