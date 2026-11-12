import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { getInvestigations } from '@/features/core/investigations/investigation.api.ts'
import { Card } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { FolderKanban, ArrowRight } from 'lucide-react'
import { fairVariantFromScore } from '@/lib/fair.ts'

export function OngoingInvestigations() {
    const { data, isLoading } = useQuery({
        queryKey: ['dashboard', 'ongoing-investigations'],
        queryFn: () => getInvestigations(),
    })

    const investigations = data?.data.slice(0, 4) || []

    return (
        <Card className="flex h-full flex-col p-5 hover:shadow-md transition-shadow duration-300">
            <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2 text-slate-800 font-semibold">
                    <FolderKanban className="h-5 w-5 text-indigo-500" />
                    <h3 className="font-display">Ongoing Investigations</h3>
                </div>
                <Link to="/investigations" className="text-xs text-indigo-600 hover:text-indigo-800 flex items-center gap-1 group">
                    View all <ArrowRight className="h-3 w-3 group-hover:translate-x-0.5 transition-transform" />
                </Link>
            </div>

            <div className="flex-1 space-y-3">
                {isLoading ? (
                    Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-16 w-full rounded-xl" />)
                ) : investigations.length === 0 ? (
                    <p className="text-sm text-slate-500">No investigations found.</p>
                ) : (
                    investigations.map(investigation => (
                        <Link
                            key={investigation.id}
                            to={`/investigations/${investigation.id}`}
                            className="block rounded-xl border border-line p-3 hover:bg-slate-50/50 hover:border-slate-300 transition-colors group"
                        >
                            <div className="flex items-start justify-between">
                                <div>
                                    <h4 className="font-medium text-sm text-slate-900 group-hover:text-indigo-700 transition-colors">{investigation.name}</h4>
                                    <p className="text-xs text-slate-500 line-clamp-1 mt-0.5">{investigation.description}</p>
                                </div>
                                <div className="flex shrink-0 items-center gap-1.5 ml-2">
                                    <Badge variant="outline" className="bg-white text-[10px]">{investigation.species}</Badge>
                                    <Badge variant={fairVariantFromScore(investigation.fairScore.total)} className="text-[10px]" aria-label={`FAIR score: ${investigation.fairScore.total} out of 100`}>
                                        {investigation.fairScore.total}/100
                                    </Badge>
                                </div>
                            </div>
                            <div className="mt-3 flex items-center justify-between text-xs text-slate-500">
                                <span>{investigation.assignedAnimalIds.length} animals</span>
                                <span>{investigation.assignedOperators.length} operators</span>
                            </div>
                        </Link>
                    ))
                )}
            </div>
        </Card>
    )
}
