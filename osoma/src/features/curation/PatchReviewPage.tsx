import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Database, Zap, ArrowRight, Loader2, CheckCircle2, AlertCircle, History, ShieldCheck, ArrowLeft, Archive, Layout, Sparkles } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { PageHeader } from '@/components/PageHeader'
import { useCuration } from './curation-context'
import { useBuildPatches, useGetPatchSummary, useApplyPatches } from './curation.api'

export function PatchReviewPage() {
  const { sessionId } = useCuration()
  const navigate = useNavigate()
  
  const { mutate: build, isPending: isBuilding } = useBuildPatches()
  const { data: summary, isLoading: isLoadingSummary } = useGetPatchSummary(sessionId)
  const { mutate: apply, isPending: isApplying, isSuccess: wasApplied } = useApplyPatches()

  useEffect(() => {
    if (sessionId) {
        build(sessionId)
    }
  }, [sessionId, build])

  if (isBuilding || isLoadingSummary) {
    return (
        <div className="flex h-[calc(100vh-12rem)] flex-col items-center justify-center p-12 text-center overflow-hidden">
            <div className="relative mb-12">
                <div className="absolute -inset-12 bg-orange-500/10 rounded-full blur-3xl animate-pulse" />
                <div className="relative h-32 w-32">
                    <div className="absolute inset-0 border-[6px] border-slate-50 rounded-[2.5rem]" />
                    <div className="absolute inset-0 border-[6px] border-orange-600 rounded-[2.5rem] border-t-transparent animate-spin" />
                    <div className="absolute inset-0 m-auto h-12 w-12 bg-white rounded-2xl flex items-center justify-center shadow-lg text-orange-600">
                        <History className="h-6 w-6" />
                    </div>
                </div>
            </div>
            <div className="max-w-sm space-y-4">
                <h3 className="text-2xl font-black italic uppercase text-slate-900 tracking-tighter">Delta Computation</h3>
                <p className="text-sm font-medium text-slate-400 leading-relaxed italic animate-pulse">
                    Calculating multi-dimensional patches and structural mutations...
                </p>
            </div>
        </div>
    )
  }

  if (wasApplied) {
    return (
        <div className="flex h-[calc(100vh-12rem)] flex-col items-center justify-center p-12 text-center overflow-hidden animate-in fade-in zoom-in duration-700">
            <div className="relative mb-12">
                <div className="absolute -inset-24 bg-emerald-500/10 rounded-full blur-[100px]" />
                <div className="relative h-40 w-40 bg-emerald-50 rounded-[3rem] border border-emerald-100 flex items-center justify-center text-emerald-500 shadow-2xl shadow-emerald-100">
                    <CheckCircle2 className="h-20 w-20" />
                </div>
                <div className="absolute -bottom-4 -right-4 h-16 w-16 bg-white rounded-2xl shadow-xl flex items-center justify-center border border-slate-50 text-blue-600 animate-bounce">
                    <ShieldCheck className="h-8 w-8" />
                </div>
            </div>
            <div className="max-w-md space-y-6">
                <div>
                    <h2 className="text-3xl font-black italic uppercase text-slate-900 tracking-tighter">Knowledge Synthesis Complete</h2>
                    <p className="text-sm font-bold text-slate-400 tracking-widest uppercase mt-2">Deltas successfully committed to master index</p>
                </div>
                <p className="text-sm font-medium text-slate-500 leading-relaxed italic">
                    Your curation vector has been successfully merged. Temporal graph invariants have been updated and are now live for all discovery nodes.
                </p>
                <div className="pt-8 flex flex-col items-center gap-4">
                    <Button onClick={() => navigate('/investigations')} size="lg" className="rounded-2xl px-12 bg-slate-900 text-white shadow-xl hover:shadow-2xl transition-all h-14 font-black italic uppercase tracking-tighter">
                        View Investigation Node
                    </Button>
                    <Button onClick={() => window.location.href = '/'} variant="ghost" className="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-900">
                        Return to Nucleus
                    </Button>
                </div>
            </div>
        </div>
    )
  }

  const subjectDeltas = summary?.subject_deltas ?? []
  const totalPatches = summary?.total_patches ?? 0

  return (
    <div className="flex h-[calc(100vh-12rem)] flex-col gap-6 overflow-hidden">
      <div className="flex items-start justify-between">
        <PageHeader 
          title="Differential Patch Review" 
          subtitle="Structural mutation log across global subject indices."
        />
        <div className="flex items-center gap-3">
            <Button variant="outline" size="sm" className="rounded-xl border-slate-200 bg-white" onClick={() => navigate('../resolution')}>
                <ArrowLeft className="mr-2 h-3.5 w-3.5" /> Back
            </Button>
            <Button 
                size="sm"
                onClick={() => sessionId && apply(sessionId)} 
                disabled={totalPatches === 0 || isApplying}
                className="rounded-xl bg-orange-600 text-white shadow-lg shadow-orange-100 hover:bg-orange-700 h-9 font-black italic uppercase tracking-tighter px-6"
            >
                {isApplying ? (
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                ) : (
                    <Zap className="mr-2 h-4 w-4" />
                )}
                Commit {totalPatches} Mutations
            </Button>
        </div>
      </div>

      <div className="flex grow gap-6 overflow-hidden">
        {/* Main Delta List */}
        <div className="flex-1 flex flex-col gap-6 overflow-hidden">
            <Card className="flex flex-col flex-1 overflow-hidden border-slate-200 bg-white shadow-sm rounded-[2.5rem]">
                <div className="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between sticky top-0 z-10">
                    <div>
                        <CardTitle className="text-base font-black italic uppercase text-slate-900 leading-none mb-1">Mutation Queue</CardTitle>
                        <CardDescription className="text-xs font-medium italic">Record-level atomic updates.</CardDescription>
                    </div>
                    {subjectDeltas.length > 0 && (
                        <div className="flex items-center gap-2">
                            <Badge variant="outline" className="rounded-lg h-7 px-3 text-[10px] font-black uppercase bg-white border-slate-200">
                                {subjectDeltas.length} Indices Affected
                            </Badge>
                        </div>
                    )}
                </div>
                
                <div className="flex-1 overflow-y-auto p-8 pt-6">
                    {subjectDeltas.length === 0 ? (
                        <div className="flex flex-col items-center justify-center h-full p-12 text-center">
                            <div className="relative mb-6">
                                <div className="absolute -inset-4 bg-orange-500/5 rounded-full blur-xl" />
                                <div className="relative h-20 w-20 bg-orange-50 border border-orange-100 rounded-3xl flex items-center justify-center text-orange-500">
                                    <AlertCircle className="h-10 w-10" />
                                </div>
                            </div>
                            <h4 className="text-lg font-black italic uppercase text-slate-900 tracking-tighter">State Synchronized</h4>
                            <p className="text-sm font-medium text-slate-400 mt-2 italic max-w-sm mx-auto leading-relaxed text-balance">
                                No deltas detected against the current master index. Database state matches observation vectors.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-6">
                            {subjectDeltas.map((delta: any) => (
                                <Card key={delta.subject_id} className="overflow-hidden border-slate-200 rounded-3xl group hover:border-orange-200 transition-all duration-300">
                                    <div className="px-6 py-4 bg-slate-50 group-hover:bg-orange-50/50 border-b border-slate-100 flex items-center justify-between transition-colors">
                                        <div className="flex items-center gap-3">
                                            <div className="h-8 w-8 rounded-xl bg-white border border-slate-100 flex items-center justify-center shadow-sm text-slate-400 group-hover:text-orange-500 transition-colors">
                                                <Archive className="h-4 w-4" />
                                            </div>
                                            <span className="text-[11px] font-mono font-black italic text-slate-900 uppercase">SUBJECT: {delta.subject_id}</span>
                                        </div>
                                        <Badge variant="outline" className="rounded-md h-5 px-1.5 text-[9px] font-black uppercase tracking-widest bg-white border-slate-200 group-hover:border-orange-200 group-hover:text-orange-600 transition-all">
                                            {delta.patch_count} Mutations
                                        </Badge>
                                    </div>
                                    <div className="divide-y divide-slate-100">
                                        {delta.fields.map((f: any, i: number) => (
                                            <div key={i} className="grid grid-cols-12 gap-4 px-6 py-4 hover:bg-slate-50/50 transition-colors items-center">
                                                <div className="col-span-4 flex items-center gap-3">
                                                    <div className="h-1.5 w-1.5 rounded-full bg-orange-400 shadow-[0_0_8px_rgba(251,146,60,0.5)]" />
                                                    <span className="text-[11px] font-black text-slate-500 uppercase tracking-widest italic">{f.target_field}</span>
                                                </div>
                                                <div className="col-span-8 flex items-center justify-between bg-slate-50/80 px-4 py-2 rounded-xl border border-slate-100 group-hover:bg-white transition-colors">
                                                    <div className="flex flex-col">
                                                        <span className="text-[9px] font-black uppercase tracking-widest text-slate-400 leading-none mb-1">Proposed Value</span>
                                                        <span className="text-[13px] font-black text-slate-900 tracking-tight italic">{f.proposed_value}</span>
                                                    </div>
                                                    <ArrowRight className="h-4 w-4 text-orange-200 group-hover:text-orange-500 transition-colors" />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </Card>
        </div>

        {/* Right Sidebar: Patch Intelligence */}
        <div className="w-80 flex flex-col gap-6 h-full">
            <Card className="relative overflow-hidden rounded-[2.5rem] border-0 bg-slate-900 text-white shadow-2xl p-8">
                <div className="absolute -top-12 -right-12 h-40 w-40 bg-orange-500/10 rounded-full blur-3xl animate-pulse" />
                <div className="relative space-y-8">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-orange-400">
                            <Database className="h-5 w-5" />
                        </div>
                        <div>
                            <p className="text-[10px] font-black text-orange-400 uppercase tracking-widest leading-none">Global Batch</p>
                            <p className="text-sm font-black italic uppercase mt-1">Mutation Payload</p>
                        </div>
                    </div>
                    
                    <div className="grid grid-cols-1 gap-4">
                        <div className="bg-white/5 border border-white/10 rounded-2xl p-6">
                            <p className="text-4xl font-black italic tracking-tighter leading-none mb-1">+{totalPatches}</p>
                            <p className="text-[10px] font-black uppercase tracking-widest text-slate-500">Atomic Properties</p>
                        </div>
                        <div className="bg-white/5 border border-white/10 rounded-2xl p-6">
                            <p className="text-4xl font-black italic tracking-tighter leading-none mb-1">{subjectDeltas.length}</p>
                            <p className="text-[10px] font-black uppercase tracking-widest text-slate-500">Affected Subjects</p>
                        </div>
                    </div>

                    <div className="p-5 rounded-2xl bg-orange-500/10 border border-orange-500/20 text-[11px] font-medium leading-relaxed italic text-orange-200/80">
                         Committing these changes will trigger real-time re-indexation of the Graph Discovery Node.
                    </div>
                </div>
            </Card>

            <Card className="flex-1 rounded-[2.5rem] bg-white border-slate-200 shadow-sm flex flex-col overflow-hidden">
                <CardHeader className="p-8 border-b border-slate-50 bg-slate-50/10">
                    <CardTitle className="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2 italic">
                        <Layout className="h-3.5 w-3.5 text-blue-500" /> Synthesis Node
                    </CardTitle>
                </CardHeader>
                <CardContent className="p-8 space-y-8">
                    <div className="space-y-4">
                        <div className="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 hover:bg-white border border-transparent hover:border-slate-200 transition-all cursor-default group">
                            <div className="h-10 w-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-slate-400 group-hover:text-blue-500 transition-colors">
                                <ShieldCheck className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-[11px] font-black text-slate-900 uppercase italic">Invar. Check</p>
                                <p className="text-[10px] text-slate-400 font-bold uppercase tracking-widest uppercase">Verified</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 hover:bg-white border border-transparent hover:border-slate-200 transition-all cursor-default group">
                            <div className="h-10 w-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-slate-400 group-hover:text-emerald-500 transition-colors">
                                <Sparkles className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-[11px] font-black text-slate-900 uppercase italic">Auto-Sync</p>
                                <p className="text-[10px] text-slate-400 font-bold uppercase tracking-widest uppercase">Active</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
      </div>
    </div>
  )
}
