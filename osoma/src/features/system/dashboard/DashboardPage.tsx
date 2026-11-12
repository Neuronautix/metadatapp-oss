import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { apiFetchHydraCollection } from '@/lib/api.ts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { StatsCard } from './components/StatsCard.tsx'
import { RecentActivityList } from './components/RecentActivityList.tsx'
import { OngoingInvestigations } from './components/OngoingInvestigations.tsx'
import { ActiveStudies } from './components/ActiveStudies.tsx'
import { FlaskConical, FolderKanban, Network, TestTube2, Heart, ArrowRight } from 'lucide-react'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { Feature } from '@/feature-flags/Feature.tsx'

// Helper to fetch counts
const fetchCount = async (resource: string) => {
    const response = await apiFetchHydraCollection(resource, {
        method: 'GET',
    })
    return response.total
}

export function DashboardPage() {
    const { data: investigationsCount, isLoading: loadingInvestigations } = useQuery({
        queryKey: ['dashboard', 'investigations-count'],
        queryFn: () => fetchCount('/projects?itemsPerPage=1'),
    })

    const { data: studiesCount, isLoading: loadingStudies } = useQuery({
        queryKey: ['dashboard', 'studies-count'],
        queryFn: () => fetchCount('/experiments?itemsPerPage=1'),
    })

    const { data: assaysCount, isLoading: loadingAssays } = useQuery({
        queryKey: ['dashboard', 'assays-count'],
        queryFn: () => fetchCount('/procedures?itemsPerPage=1'),
    })

    const { data: appsCount, isLoading: loadingApps } = useQuery({
        queryKey: ['dashboard', 'apps-count'],
        queryFn: () => fetchCount('/connected_apps?itemsPerPage=1'),
    })

    // Mock recent activity for now - in a real app this would come from an audit log or activity stream
    const recentActivity = [
        {
            id: '1',
            title: 'New Investigation Created',
            description: 'Investigation "Neuro-Analysis 2024" was started.',
            timestamp: new Date().toISOString(),
            type: 'investigation' as const,
            user: { name: 'Dr. Smith', initials: 'DS' }
        },
        {
            id: '2',
            title: 'Study Completed',
            description: 'Study EXP-2024-001 data collection finished.',
            timestamp: new Date(Date.now() - 3600000).toISOString(),
            type: 'study' as const,
            user: { name: 'Jane Doe', initials: 'JD' }
        }
    ] as any

    return (
        <div className="space-y-8">
            <PageHeader
                title="Dashboard"
                subtitle="Overview of your research operations and integrations."
            />

            {/* WellFAIR contextual banner — only shown when MetaDatApp feature surface is enabled */}
            <Feature flag="metadatapp-feat.enabled">
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-950 to-slate-900 px-6 py-5 text-white">
                    <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_right,_rgba(99,102,241,0.2),_transparent_60%)]" />
                    <div className="relative flex flex-wrap items-center justify-between gap-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/20">
                                <Heart className="h-5 w-5 text-rose-300" />
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-[0.15em] text-indigo-300">WellFAIR Framework</p>
                                <p className="font-semibold text-white">Data welfare is animal welfare</p>
                                <p className="mt-0.5 text-sm text-slate-300">
                                    FAIR-compliant metadata reduces redundant experiments and directly lowers animal use.
                                </p>
                            </div>
                        </div>
                        <Link
                            to="/wellfair"
                            className="flex items-center gap-1.5 rounded-xl border border-indigo-500/30 bg-indigo-500/20 px-4 py-2 text-sm font-medium text-indigo-200 transition hover:bg-indigo-500/30"
                        >
                            Learn more <ArrowRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            </Feature>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                {loadingInvestigations ? <Skeleton className="h-32" /> : (
                    <StatsCard
                        title="Active Investigations"
                        value={investigationsCount ?? 0}
                        icon={FolderKanban}
                        description="Total investigations in system"
                    />
                )}
                {loadingStudies ? <Skeleton className="h-32" /> : (
                    <StatsCard
                        title="Studies"
                        value={studiesCount ?? 0}
                        icon={FlaskConical}
                        description="Total studies running"
                    />
                )}
                {loadingAssays ? <Skeleton className="h-32" /> : (
                    <StatsCard
                        title="Assays"
                        value={assaysCount ?? 0}
                        icon={TestTube2}
                        description="Registered assays"
                    />
                )}
                {loadingApps ? <Skeleton className="h-32" /> : (
                    <StatsCard
                        title="Connected Apps"
                        value={appsCount ?? 0}
                        icon={Network}
                        description="Integrated external services"
                    />
                )}
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-8">
                <div className="col-span-4">
                    <OngoingInvestigations />
                </div>
                <div className="col-span-4">
                    <ActiveStudies />
                </div>
            </div>

            <div className="grid gap-4">
                <RecentActivityList
                    items={recentActivity}
                    description="Recent updates from your team and connected systems."
                />
            </div>
        </div>
    )
}
