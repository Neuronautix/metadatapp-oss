import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { getStudies } from '@/features/core/studies/study.api.ts'
import { Card } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { FlaskConical, ArrowRight } from 'lucide-react'
import { formatNumber } from '@/lib/format.ts'

export function ActiveStudies() {
    const { data, isLoading } = useQuery({
        queryKey: ['dashboard', 'active-studies'],
        queryFn: () => getStudies({ page: 1, pageSize: 5, status: 'running', search: '', investigation: '', qcStatus: '' }),
    })

    const studies = data?.data || []

    return (
        <Card className="flex h-full flex-col p-5 hover:shadow-md transition-shadow duration-300">
            <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2 text-slate-800 font-semibold">
                    <FlaskConical className="h-5 w-5 text-emerald-500" />
                    <h3 className="font-display">Active Studies</h3>
                </div>
                <Link to="/studies" className="text-xs text-emerald-600 hover:text-emerald-800 flex items-center gap-1 group">
                    View all <ArrowRight className="h-3 w-3 group-hover:translate-x-0.5 transition-transform" />
                </Link>
            </div>

            <div className="flex-1 space-y-3">
                {isLoading ? (
                    Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-16 w-full rounded-xl" />)
                ) : studies.length === 0 ? (
                    <p className="text-sm text-slate-500">No currently running studies.</p>
                ) : (
                    studies.map(exp => (
                        <Link
                            key={exp.id}
                            to={`/studies/${exp.id}`}
                            className="block rounded-xl border border-line p-3 hover:bg-slate-50/50 hover:border-slate-300 transition-colors group"
                        >
                            <div className="flex items-start justify-between">
                                <div>
                                    <h4 className="font-medium text-sm text-slate-900 group-hover:text-emerald-700 transition-colors">{exp.name}</h4>
                                    <p className="text-xs text-slate-500 line-clamp-1 mt-0.5 font-mono">{exp.id}</p>
                                </div>
                                <Badge variant="success" className="shrink-0 ml-2 text-[10px] capitalize">{exp.status}</Badge>
                            </div>
                            <div className="mt-3 flex items-center justify-between text-xs text-slate-500">
                                <span className="truncate max-w-[140px]">{exp.assayName}</span>
                                <span className="font-medium text-slate-700">{formatNumber(exp.dataVolumeGb)} GB</span>
                            </div>
                        </Link>
                    ))
                )}
            </div>
        </Card>
    )
}
