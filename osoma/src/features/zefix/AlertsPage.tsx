import { useMemo } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import {
    acknowledgeZefishAlert,
    getZefishAlerts,
    resolveZefishAlert,
} from '@/domain/zefish/zefish.api.ts'
import { asAlertSeverityVariant, asAlertStatusVariant } from '@/domain/zefish/zefish.utils.ts'
import { formatDateTime } from '@/lib/format.ts'

export function AlertsPage() {
    const queryClient = useQueryClient()
    const { role } = useRole()
    const allowEdit = canEdit(role)

    const alertsQuery = useQuery({
        queryKey: ['zefix', 'alerts'],
        queryFn: () => getZefishAlerts(),
    })

    const acknowledgeMutation = useMutation({
        mutationFn: (alertID: string) => acknowledgeZefishAlert(alertID),
        onSuccess: async () => {
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['zefix', 'alerts'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'alerts', 'active'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'overview'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'location-explorer'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'systems'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'rooms'] }),
            ])
        },
    })

    const resolveMutation = useMutation({
        mutationFn: (alertID: string) => resolveZefishAlert(alertID),
        onSuccess: async () => {
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['zefix', 'alerts'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'alerts', 'active'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'overview'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'location-explorer'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'systems'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'rooms'] }),
            ])
        },
    })

    const grouped = useMemo(() => {
        const source = alertsQuery.data ?? []
        return {
            critical: source.filter((alert) => alert.severity === 'CRITICAL'),
            warning: source.filter((alert) => alert.severity === 'WARNING'),
            info: source.filter((alert) => alert.severity === 'INFO'),
        }
    }, [alertsQuery.data])

    return (
        <div className="space-y-6">
            <PageHeader
                title="Alerts"
                subtitle="Severity-based alerting for temperature drift, mortality spikes, population drops, and telemetry failures."
            />

            {alertsQuery.isLoading ? (
                <div className="space-y-3">
                    {Array.from({ length: 5 }).map((_, index) => (
                        <Skeleton key={index} className="h-24 w-full" />
                    ))}
                </div>
            ) : alertsQuery.isError ? (
                <EmptyState title="Alert stream unavailable" description={alertsQuery.error?.message ?? 'Unable to load alerts.'} />
            ) : !alertsQuery.data?.length ? (
                <EmptyState title="No alerts" description="No alerts found for this facility." />
            ) : (
                <>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader>
                                <CardDescription>Critical</CardDescription>
                                <CardTitle>{grouped.critical.length}</CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardDescription>Warning</CardDescription>
                                <CardTitle>{grouped.warning.length}</CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardDescription>Info</CardDescription>
                                <CardTitle>{grouped.info.length}</CardTitle>
                            </CardHeader>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Alert panel</CardTitle>
                            <CardDescription>
                                Alert type, affected entity, timestamp, and recommended action.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {alertsQuery.data.map((alert) => (
                                <div key={alert.alertID} className="rounded-xl border border-line bg-surface-2 p-3">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-slate-800">{alert.type}</p>
                                            <p className="text-xs text-slate-500">
                                                {alert.affectedLabel} · {formatDateTime(alert.timestamp)}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant={asAlertSeverityVariant(alert.severity)}>{alert.severity}</Badge>
                                            <Badge variant={asAlertStatusVariant(alert.status)}>{alert.status}</Badge>
                                        </div>
                                    </div>
                                    <p className="mt-2 text-sm text-slate-600">
                                        <span className="font-semibold text-slate-800">Recommended action:</span>{' '}
                                        {alert.recommendedAction}
                                    </p>
                                    {allowEdit && alert.status !== 'resolved' ? (
                                        <div className="mt-3 flex gap-2">
                                            {alert.status === 'active' ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => acknowledgeMutation.mutate(alert.alertID)}
                                                    disabled={acknowledgeMutation.isPending}
                                                >
                                                    Acknowledge
                                                </Button>
                                            ) : null}
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => resolveMutation.mutate(alert.alertID)}
                                                disabled={resolveMutation.isPending}
                                            >
                                                Resolve
                                            </Button>
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    )
}
