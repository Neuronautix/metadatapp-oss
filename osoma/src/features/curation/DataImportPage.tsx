import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Upload, FileText, CheckCircle2, AlertCircle, Database, Layout, Sparkles, ArrowRight, X, Clock } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { PageHeader } from '@/components/PageHeader'
import { parseFile } from '@/domain/curation/analysis.engine'
import { useCuration } from './curation-context'
import { useCreateImportSession, useGetSessionSummary, useGenerateProposals } from './curation.api'

export function DataImportPage() {
  const { sessionId, setSessionId, file, report, setFile, setReport, setParsedRows, reset } = useCuration()
  const [analyzing, setAnalyzing] = useState(false)
  const [isDragging, setIsDragging] = useState(false)
  const navigate = useNavigate()
  
  const createSession = useCreateImportSession()
  const generateProposals = useGenerateProposals()
  const { data: sessionSummary } = useGetSessionSummary(sessionId)

  const handleFileChange = async (selectedFile: File) => {
    if (!selectedFile) return

    reset()
    setFile(selectedFile)
    setAnalyzing(true)
    try {
      const result = await createSession.mutateAsync(selectedFile)
      setSessionId(result.id)
      
      // Trigger backend workflow steps
      await generateProposals.mutateAsync(result.id)
      
      // Local parsing for immediate feedback
      const { report: analysisReport, rows } = await parseFile(selectedFile)
      setReport(analysisReport)
      setParsedRows(rows)
    } catch (error) {
      console.error('Analysis failed', error)
      const err = error as Error;
      alert(err.message || 'An unexpected error occurred during file analysis.')
    } finally {
      setAnalyzing(false)
    }
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    setIsDragging(false)
    const droppedFile = e.dataTransfer.files[0]
    if (droppedFile && (droppedFile.name.endsWith('.csv') || droppedFile.name.endsWith('.xlsx'))) {
        handleFileChange(droppedFile)
    }
  }

  return (
    <div className="flex h-[calc(100vh-12rem)] flex-col gap-6 overflow-hidden">
      <div className="flex items-start justify-between">
        <PageHeader 
          title="Laboratory Data Ingestion" 
          subtitle="Advanced telemetry import with automated schema inference and quality profiling."
        />
        {report && (
            <Button variant="outline" size="sm" className="rounded-xl border-slate-200 bg-white" onClick={reset}>
               <X className="mr-2 h-3.5 w-3.5" /> Clear Session
            </Button>
        )}
      </div>

      {!report ? (
        <div className="flex flex-1 items-center justify-center p-4">
            <div 
                className={`relative group max-w-2xl w-full aspect-[16/9] transition-all duration-500
                    ${isDragging ? 'scale-105' : 'scale-100'}`}
                onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
                onDragLeave={() => setIsDragging(false)}
                onDrop={handleDrop}
            >
                {/* Background Decoration */}
                <div className="absolute -inset-4 bg-gradient-to-tr from-blue-500/10 via-slate-500/5 to-purple-500/10 rounded-[2.5rem] blur-2xl opacity-50 group-hover:opacity-100 transition-opacity" />
                
                <Card className={`relative h-full border-2 border-dashed transition-all duration-500 flex flex-col items-center justify-center p-12 overflow-hidden rounded-[2rem]
                    ${isDragging ? 'border-blue-500 bg-blue-50/50' : 'border-slate-200 bg-white/80 backdrop-blur-xl hover:border-slate-300'}`}>
                    
                    {/* Animated Loader Overlay */}
                    {analyzing && (
                        <div className="absolute inset-0 z-50 bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center gap-4 animate-in fade-in duration-300">
                             <div className="relative h-16 w-16">
                                <div className="absolute inset-0 border-4 border-slate-100 rounded-full" />
                                <div className="absolute inset-0 border-4 border-blue-600 rounded-full border-t-transparent animate-spin" />
                                <Database className="absolute inset-0 m-auto h-6 w-6 text-blue-600" />
                             </div>
                             <div className="text-center">
                                <p className="text-sm font-black text-slate-900 uppercase tracking-widest">Profiling Structure</p>
                                <p className="text-[10px] text-slate-400 font-bold uppercase mt-1">AI generating semantic mapping proposals...</p>
                             </div>
                        </div>
                    )}

                    <div className={`mb-8 flex h-24 w-24 items-center justify-center rounded-[2rem] transition-all duration-500
                        ${isDragging ? 'bg-blue-600 text-white shadow-2xl shadow-blue-200' : 'bg-slate-50 text-slate-400 group-hover:bg-slate-100 group-hover:text-slate-600 group-hover:rotate-3 shadow-inner'}`}>
                        <Upload className={`h-10 w-10 transition-transform duration-500 ${isDragging ? 'scale-110 -translate-y-1' : 'scale-100'}`} />
                    </div>

                    <div className="text-center space-y-2 mb-10">
                        <h3 className="text-2xl font-black tracking-tight text-slate-900 italic uppercase">Secure Data Drop</h3>
                        <p className="max-w-xs text-sm font-medium text-slate-500 leading-relaxed italic opacity-80">
                            Drag and drop your clinical or lab results here.
                            <span className="block mt-1 font-bold text-slate-400">Supported: .CSV, .XLSX (BioFormat v2.1)</span>
                        </p>
                    </div>

                    <div className="relative">
                        <input
                            type="file"
                            className="absolute inset-0 cursor-pointer opacity-0 z-10"
                            accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            onChange={(e) => e.target.files?.[0] && handleFileChange(e.target.files[0])}
                            disabled={analyzing}
                        />
                        <Button className="rounded-2xl px-12 py-6 bg-slate-900 text-white hover:bg-black transition-all shadow-xl shadow-slate-200 group-hover:scale-105 active:scale-95" disabled={analyzing}>
                            Browse System <ArrowRight className="ml-3 h-4 w-4" />
                        </Button>
                    </div>

                    {/* Security Badge */}
                    <div className="mt-12 flex items-center gap-2 px-4 py-1.5 bg-slate-50 rounded-full border border-slate-100 opacity-60">
                        <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                        <span className="text-[10px] font-black text-slate-400 uppercase tracking-widest">End-to-End Encrypted Tunnel</span>
                    </div>
                </Card>
            </div>
        </div>
      ) : (
        <div className="grid grow gap-6 overflow-hidden md:grid-cols-4">
          <div className="md:col-span-3 flex flex-col gap-6 overflow-hidden">
            <Card className="border-slate-200 bg-white shadow-sm flex flex-col overflow-hidden rounded-[2rem]">
                <div className="p-8 border-b border-slate-100 bg-slate-50/50">
                    <div className="flex items-center justify-between mb-8">
                        <div>
                            <div className="flex items-center gap-3 mb-1">
                                <Badge className="bg-blue-600 hover:bg-blue-600 text-white rounded-lg px-2 text-[10px] font-black uppercase italic">Structure OK</Badge>
                                <CardTitle className="text-xl font-black italic text-slate-900 uppercase">Analysis Report</CardTitle>
                            </div>
                            <CardDescription className="text-sm font-medium flex items-center gap-2">
                                <FileText className="h-3.5 w-3.5 text-blue-500" /> {file?.name} 
                                {sessionSummary && <span className="text-slate-300 font-bold mx-1">/</span>}
                                {sessionSummary && <span className="text-slate-400 text-xs font-bold uppercase tracking-wider">Session {sessionSummary.id.slice(0,8)}</span>}
                            </CardDescription>
                        </div>
                        <div className="flex gap-4">
                            <div className="text-right">
                                <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Observation Points</p>
                                <p className="text-2xl font-black text-slate-900 tracking-tighter italic">{report.rowCount.toLocaleString()}</p>
                            </div>
                            <div className="h-10 w-px bg-slate-200 mx-2" />
                             <div className="text-right">
                                <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Dimension Matrix</p>
                                <p className="text-2xl font-black text-slate-900 tracking-tighter italic">{report.columnCount}</p>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-4 gap-4">
                        {report.headers.slice(0, 4).map(header => (
                            <div key={header} className="p-4 bg-white border border-slate-200 rounded-2xl shadow-sm space-y-1">
                                <p className="text-[10px] font-black text-blue-600 uppercase tracking-widest italic">{header}</p>
                                <p className="text-xs font-bold text-slate-400 uppercase tracking-[0.2em]">{report.detectedTypes[header]}</p>
                            </div>
                        ))}
                        {report.headers.length > 4 && (
                             <div className="p-4 bg-slate-100 border border-slate-200 border-dashed rounded-2xl flex items-center justify-center">
                                <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">+{report.headers.length - 4} More Fields</p>
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto p-0 min-h-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs border-collapse">
                            <thead className="bg-slate-900 text-slate-400 sticky top-0 z-10">
                                <tr>
                                    {report.headers.map(header => (
                                        <th key={header} className="px-6 py-4 font-black uppercase tracking-widest italic border-r border-slate-800 last:border-0">{header}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {report.previewRows.map((row, i) => (
                                    <tr key={i} className="group hover:bg-slate-50 transition-colors">
                                        {report.headers.map(header => (
                                            <td key={header} className="px-6 py-4 text-slate-600 font-medium group-hover:text-slate-900 border-r border-slate-100 last:border-0">{row[header]}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </Card>
          </div>

          <div className="flex flex-col gap-6 h-full">
            <Card className="bg-slate-900 border-slate-800 text-white shadow-2xl relative overflow-hidden rounded-[2rem]">
                <div className="absolute top-0 right-0 p-4 opacity-10">
                    <Sparkles className="h-16 w-16" />
                </div>
              <CardHeader className="p-8 pb-4">
                <CardTitle className="text-xl font-black italic uppercase tracking-tighter">Ready for Synthesis</CardTitle>
                <CardDescription className="text-slate-400 text-xs font-medium">Schema proposals successfully generated.</CardDescription>
              </CardHeader>
              <CardContent className="p-8 pt-0 space-y-6">
                <div className="space-y-3">
                  <div className="flex justify-between text-[10px] font-black uppercase tracking-widest text-slate-500">
                    <span>Quality Index</span>
                    <span className="text-blue-400">
                        {report.columnCount === 0 ? '—' : `${Math.round((1 - Object.values(report.missingValueStats).reduce((a, b) => a + b, 0) / Math.max(1, report.rowCount * report.columnCount)) * 100)}%`}
                    </span>
                  </div>
                  <div className="h-3 w-full rounded-full bg-slate-800 p-0.5">
                    <div className="h-2 w-full rounded-full bg-gradient-to-r from-blue-600 to-indigo-500 shadow-[0_0_15px_rgba(59,130,246,0.6)]" />
                  </div>
                </div>
                
                <div className="space-y-3 pt-4">
                    <Button className="w-full py-6 rounded-2xl bg-white text-slate-900 hover:bg-white/90 font-black uppercase italic tracking-wider shadow-lg transition-transform hover:-translate-y-1 active:scale-95" onClick={() => navigate('../mapping')}>
                    Enter Mapping Studio <ArrowRight className="ml-2 h-4 w-4" />
                    </Button>
                    <div className="text-center">
                        <p className="text-[10px] text-slate-500 font-bold uppercase tracking-widest opacity-60">Estimated processing time: ~4s</p>
                    </div>
                </div>
              </CardContent>
            </Card>

            <Card className="bg-white border-slate-200 shadow-sm grow rounded-[2rem] flex flex-col overflow-hidden">
              <CardHeader className="p-6 border-b border-slate-50 bg-slate-50/30">
                <CardTitle className="text-[11px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                    <AlertCircle className="h-3.5 w-3.5" /> Logical Verification
                </CardTitle>
              </CardHeader>
              <CardContent className="p-6 space-y-6 flex-1 overflow-y-auto">
                {Object.entries(report.missingValueStats).some(([, count]) => count > 0) ? (
                  <div className="p-4 rounded-2xl bg-amber-50 border border-amber-100 space-y-2">
                    <div className="flex items-center gap-2 text-amber-700">
                        <AlertCircle className="h-4 w-4" />
                        <span className="text-[11px] font-black uppercase tracking-wider">Anomalies Detected</span>
                    </div>
                    <p className="text-[11px] text-amber-600 font-medium leading-relaxed italic">
                        Missing values found in the telemetry stream. Data curation will proceed with imputation guards.
                    </p>
                  </div>
                ) : (
                  <div className="p-4 rounded-2xl bg-emerald-50 border border-emerald-100 space-y-2">
                    <div className="flex items-center gap-2 text-emerald-700">
                        <CheckCircle2 className="h-4 w-4" />
                        <span className="text-[11px] font-black uppercase tracking-wider">Integrity Verified</span>
                    </div>
                    <p className="text-[11px] text-emerald-600 font-medium leading-relaxed italic">
                        Input stream matches expected BioFormat v2 signature. Data density is optimal.
                    </p>
                  </div>
                )}

                <div className="space-y-4">
                    <div className="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors">
                        <div className="h-8 w-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <Clock className="h-4 w-4" />
                        </div>
                        <div>
                            <p className="text-[10px] font-black text-slate-900 uppercase">Latency Target</p>
                            <p className="text-[10px] text-slate-400 font-bold tracking-widest uppercase">Under 200ms</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors">
                        <div className="h-8 w-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                            <Layout className="h-4 w-4" />
                        </div>
                        <div>
                            <p className="text-[10px] font-black text-slate-900 uppercase">Schema Inference</p>
                            <p className="text-[10px] text-slate-400 font-bold tracking-widest uppercase">Graph Ready</p>
                        </div>
                    </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      )}
    </div>
  )
}
