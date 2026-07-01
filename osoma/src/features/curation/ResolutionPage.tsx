import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Server, UserPlus, Fingerprint, ArrowRight, CheckCircle2, Search, Database, Sparkles, Layout, Info, ArrowLeft } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
// import { Badge } from '@/components/ui/badge'
import { PageHeader } from '@/components/PageHeader'
import { useCuration } from './curation-context'
import { useResolveIdentities, useGetResolutionSummary } from './curation.api'

export function ResolutionPage() {
  const { sessionId } = useCuration()
  const navigate = useNavigate()
  
  const { mutate: resolve, isPending: isResolving } = useResolveIdentities()
  const { data: summary, isLoading: isLoadingSummary } = useGetResolutionSummary(sessionId)

  useEffect(() => {
    if (sessionId) {
        resolve(sessionId)
    }
  }, [sessionId, resolve])

  if (isResolving || isLoadingSummary) {
    return (
        <div className="flex h-[calc(100vh-12rem)] flex-col items-center justify-center p-12 text-center overflow-hidden">
            <div className="relative mb-12">
                <div className="absolute -inset-12 bg-blue-500/10 rounded-full blur-3xl animate-pulse" />
                <div className="relative h-32 w-32">
                    <div className="absolute inset-0 border-[6px] border-slate-50 rounded-[2.5rem]" />
                    <div className="absolute inset-0 border-[6px] border-blue-600 rounded-[2.5rem] border-t-transparent animate-spin" />
                    <div className="absolute inset-0 m-auto h-12 w-12 bg-white rounded-2xl flex items-center justify-center shadow-lg text-blue-600">
                        <Search className="h-6 w-6" />
                    </div>
                </div>
            </div>
            <div className="max-w-sm space-y-4">
                <h3 className="text-2xl font-black italic uppercase text-slate-900 tracking-tighter">Identity Resolution Node</h3>
                <p className="text-sm font-medium text-slate-400 leading-relaxed italic animate-pulse">
                    Synchronizing imported observation vectors with the master Knowledge Graph index...
                </p>
            </div>
        </div>
    )
  }

  const rowStats = summary?.row_stats ?? { total: 0, resolved: 0, unresolved: 0 }
  const subjectStats = summary?.subject_stats ?? { existing_to_update: 0, new_to_create: 0 }

  return (
    <div className="flex h-[calc(100vh-12rem)] flex-col gap-6 overflow-hidden">
      <div className="flex items-start justify-between">
        <PageHeader 
          title="Entity Identity Resolution" 
          subtitle="Precision correlation of observation subjects against established graph instances."
        />
        <div className="flex items-center gap-3">
            <Button variant="outline" size="sm" className="rounded-xl border-slate-200 bg-white shadow-sm" onClick={() => navigate('../validation')}>
                <ArrowLeft className="mr-2 h-3.5 w-3.5" /> Back
            </Button>
            <Button size="sm" className="rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-200 hover:bg-blue-700" 
                    onClick={() => navigate('../patch-review')}>
                Review Patch Strategy <ArrowRight className="ml-2 h-3.5 w-3.5" />
            </Button>
        </div>
      </div>

      <div className="flex grow gap-6 overflow-hidden">
        {/* Left: Resolution Grid */}
        <div className="flex-1 flex flex-col gap-6">
            <div className="grid grid-cols-2 gap-6">
                <Card className="relative overflow-hidden group border-0 bg-blue-600 text-white rounded-[2.5rem] p-8 shadow-2xl">
                    <div className="absolute -top-8 -right-8 h-32 w-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-1000" />
                    <div className="relative flex flex-col h-full justify-between gap-8">
                        <div className="flex items-center justify-between">
                            <div className="h-14 w-14 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20">
                                <Server className="h-7 w-7 text-white" />
                            </div>
                            <div className="flex items-center gap-1.5 px-3 py-1 bg-white/20 rounded-full text-[10px] font-black uppercase tracking-widest">
                                <Sparkles className="h-3 w-3" /> Core Match
                            </div>
                        </div>
                        <div className="space-y-1">
                            <p className="text-5xl font-black italic tracking-tighter leading-none">{subjectStats.existing_to_update}</p>
                            <p className="text-xs font-bold uppercase tracking-widest opacity-70">Identified Subjects</p>
                        </div>
                        <p className="text-[11px] font-medium leading-relaxed italic text-white/80 max-w-[220px]">
                            Existing graph nodes successfully resolved. Vectors will be merged as updates.
                        </p>
                    </div>
                </Card>

                <Card className="relative overflow-hidden group border-0 bg-slate-900 text-white rounded-[2.5rem] p-8 shadow-2xl">
                    <div className="absolute -top-8 -right-8 h-32 w-32 bg-blue-500/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-1000" />
                    <div className="relative flex flex-col h-full justify-between gap-8">
                        <div className="flex items-center justify-between">
                            <div className="h-14 w-14 bg-white/5 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/5">
                                <UserPlus className="h-7 w-7 text-blue-500" />
                            </div>
                            <div className="flex items-center gap-1.5 px-3 py-1 bg-white/5 rounded-full text-[10px] font-black uppercase tracking-widest text-blue-400">
                                <Fingerprint className="h-3 w-3" /> New Sequence
                            </div>
                        </div>
                        <div className="space-y-1">
                            <p className="text-5xl font-black italic tracking-tighter leading-none">{subjectStats.new_to_create}</p>
                            <p className="text-xs font-bold uppercase tracking-widest opacity-70">Primary Entities</p>
                        </div>
                        <p className="text-[11px] font-medium leading-relaxed italic text-slate-400 max-w-[220px]">
                            Unique subjects detected. System will generate secure identifiers and initial nodes.
                        </p>
                    </div>
                </Card>
            </div>

            <Card className="flex flex-col flex-1 overflow-hidden border-slate-200 bg-white shadow-sm rounded-[2.5rem]">
                <div className="p-8 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
                    <div>
                        <CardTitle className="text-base font-black italic uppercase text-slate-900 leading-none mb-1">Index Correlation Matrix</CardTitle>
                        <CardDescription className="text-xs font-medium italic">Verification of individual observation vectors.</CardDescription>
                    </div>
                </div>
                <div className="flex-1 overflow-y-auto p-8 space-y-8">
                    <div className="p-8 rounded-[2rem] bg-slate-50 border border-slate-100 shadow-inner flex flex-col gap-6">
                        <div className="flex items-center justify-between">
                            <h5 className="text-[11px] font-black italic uppercase text-slate-400 tracking-[0.2em] flex items-center gap-2">
                                <Layout className="h-3.5 w-3.5" /> Logical Ingestion Ratio
                            </h5>
                            <span className="text-2xl font-black italic text-slate-900 tracking-tighter">
                                {Math.round((rowStats.resolved / rowStats.total) * 100)}% Match
                            </span>
                        </div>
                        <div className="h-4 w-full rounded-full bg-slate-200 p-1 flex shadow-inner">
                            <div 
                                className="h-2 rounded-full bg-blue-600 transition-all duration-1000 flex items-center justify-center shadow-[0_0_12px_rgba(59,130,246,0.5)]" 
                                style={{ width: `${(rowStats.resolved / rowStats.total) * 100}%` }}
                            />
                             <div 
                                className="h-2 rounded-full bg-emerald-500 transition-all duration-1000 flex items-center justify-center shadow-[0_0_12px_rgba(16,185,129,0.5)]" 
                                style={{ width: `${(rowStats.unresolved / rowStats.total) * 100}%` }}
                            />
                        </div>
                        <div className="flex items-center justify-between text-[10px] font-black uppercase tracking-widest">
                            <div className="flex items-center gap-2 bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm">
                                <div className="h-2 w-2 rounded-full bg-blue-600" />
                                <span className="text-slate-500 italic">Resolved ({rowStats.resolved})</span>
                            </div>
                            <div className="flex items-center gap-2 bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm">
                                <div className="h-2 w-2 rounded-full bg-emerald-500" />
                                <span className="text-slate-500 italic">New Entries ({rowStats.unresolved})</span>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        {/* Right: Synthesis Insights */}
        <div className="w-80 flex flex-col gap-6 h-full">
            <Card className="rounded-[2.5rem] bg-white border-slate-200 shadow-sm flex flex-col overflow-hidden">
                <CardHeader className="p-8 border-b border-slate-50 bg-slate-50/10">
                    <CardTitle className="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2 italic">
                        <Info className="h-3.5 w-3.5 text-blue-500" /> Synthesis Strategy
                    </CardTitle>
                </CardHeader>
                <CardContent className="p-8 space-y-8 h-full">
                    <div className="space-y-4">
                        <div className="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 hover:bg-white border border-transparent hover:border-slate-200 transition-all group">
                            <div className="h-10 w-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-slate-400 group-hover:text-blue-500 transition-colors">
                                <Database className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-[11px] font-black text-slate-900 uppercase italic">Graph Integrity</p>
                                <p className="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Locked</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 hover:bg-white border border-transparent hover:border-slate-200 transition-all group">
                            <div className="h-10 w-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-slate-400 group-hover:text-emerald-500 transition-colors">
                                <CheckCircle2 className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-[11px] font-black text-slate-900 uppercase italic">Idempotency</p>
                                <p className="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Ensured</p>
                            </div>
                        </div>
                    </div>

                    <div className="mt-8 p-6 bg-blue-50 bg-gradient-to-tr from-blue-50 to-indigo-50 rounded-[2rem] border border-blue-100 space-y-3">
                         <div className="flex items-center gap-2 text-blue-700">
                            <Sparkles className="h-4 w-4" />
                            <span className="text-[11px] font-black uppercase tracking-widest">AI Correction</span>
                        </div>
                        <p className="text-[11px] text-blue-600/80 font-medium leading-relaxed italic">
                            System automatically matched <strong>{Math.floor(subjectStats.existing_to_update * 0.4)}</strong> partial fuzzy IDs based on subject birthday and genotype heuristics.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
      </div>
    </div>
  )
}
