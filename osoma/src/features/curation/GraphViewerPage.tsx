import { useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { ZoomIn, ZoomOut, Save, Database } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/PageHeader'
import { generateKnowledgeGraph } from '@/domain/graph/graph.generator'
import { useCuration } from './curation-context'

export function GraphViewerPage() {
  const { parsedRows, mappings, validationReport } = useCuration()
  const navigate = useNavigate()

  const graph = useMemo(() => {
    if (!validationReport?.isValid || parsedRows.length === 0 || mappings.length === 0) return null
    return generateKnowledgeGraph(parsedRows, mappings)
  }, [validationReport, parsedRows, mappings])

  if (!graph) {
    return (
      <div className="space-y-6">
        <PageHeader
          title="Knowledge Graph"
          subtitle="Structural visualization of integrated entities and relations."
        />
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center">
          <p className="text-sm text-slate-500">
            {validationReport && !validationReport.isValid
              ? 'Validation failed. Fix all errors in the validation step before generating the graph.'
              : 'No validated data available. Complete the validation step first.'}
          </p>
          <Button variant="outline" className="mt-4" onClick={() => navigate('../validation')}>
            Go to Validation
          </Button>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <PageHeader 
          title="Knowledge Graph" 
          subtitle="Structural visualization of integrated entities and relations."
        />
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => navigate(-1)}>Back to Validation</Button>
          <Button variant="outline" className="gap-2">
            <Save className="h-4 w-4" /> Save Profile
          </Button>
          <Button className="gap-2 bg-blue-600 hover:bg-blue-700">
            <Database className="h-4 w-4" /> Final Ingestion
          </Button>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-4 h-[700px]">
        <div className="md:col-span-3 h-full">
          <Card className="h-full relative overflow-hidden bg-slate-50 border-slate-200">
            <div className="absolute top-4 left-4 z-10 flex flex-col gap-2">
              <Button size="icon" variant="outline" className="shadow-sm border-slate-200"><ZoomIn className="h-4 w-4" /></Button>
              <Button size="icon" variant="outline" className="shadow-sm border-slate-200"><ZoomOut className="h-4 w-4" /></Button>
            </div>
            
            <div className="absolute top-4 right-4 z-10 flex gap-2 font-semibold">
                <div className="flex items-center gap-2 rounded-lg bg-white/80 backdrop-blur px-3 py-1.5 border border-slate-200 shadow-sm text-xs">
                    <div className="h-2 w-2 rounded-full bg-blue-500" /> Subject
                </div>
                <div className="flex items-center gap-2 rounded-lg bg-white/80 backdrop-blur px-3 py-1.5 border border-slate-200 shadow-sm text-xs">
                    <div className="h-2 w-2 rounded-full bg-amber-500" /> Cage
                </div>
            </div>

            <div className="flex h-full items-center justify-center">
              <div className="relative w-full h-full flex items-center justify-center p-20">
                    <div className="absolute inset-0 opacity-10 pointer-events-none" style={{ backgroundImage: 'radial-gradient(#94a3b8 1px, transparent 1px)', backgroundSize: '24px 24px' }} />
                    
                    <div className="relative w-full max-w-2xl h-96">
                        {/* Visualise a subset of unique cage nodes in the centre */}
                        {graph.nodes.filter(n => n.type === 'Cage').slice(0, 3).map((node, idx, arr) => {
                          const angle = arr.length === 1 ? 0 : (idx / arr.length) * 2 * Math.PI
                          const cx = 50 + 30 * Math.cos(angle)
                          const cy = 50 + 30 * Math.sin(angle)
                          return (
                            <div
                              key={node.id}
                              className="absolute h-20 w-20 rounded-full bg-amber-500 border-4 border-white shadow-xl flex items-center justify-center text-white font-black text-xs"
                              style={{ left: `${cx}%`, top: `${cy}%`, transform: 'translate(-50%,-50%)' }}
                            >
                              {node.id.slice(0, 6)}
                            </div>
                          )
                        })}

                        {graph.nodes.filter(n => n.type === 'Subject').slice(0, 6).map((node, idx, arr) => {
                          const angle = arr.length === 1 ? 0 : (idx / arr.length) * 2 * Math.PI - Math.PI / 4
                          const cx = 50 + 45 * Math.cos(angle)
                          const cy = 50 + 45 * Math.sin(angle)
                          return (
                            <div
                              key={node.id}
                              className="absolute h-16 w-16 rounded-full bg-blue-600 border-4 border-white shadow-lg flex items-center justify-center text-white font-bold text-xs"
                              style={{ left: `${cx}%`, top: `${cy}%`, transform: 'translate(-50%,-50%)' }}
                            >
                              {node.id.slice(0, 4)}
                            </div>
                          )
                        })}

                        <svg className="absolute inset-0 h-full w-full pointer-events-none opacity-20">
                            {graph.relationships.slice(0, 10).map((_rel, idx) => (
                              <line key={idx} x1="30%" y1="30%" x2="50%" y2="50%" stroke="currentColor" strokeWidth="2" />
                            ))}
                        </svg>
                    </div>
              </div>
            </div>

            <div className="absolute bottom-4 left-4 right-4 flex items-center justify-between text-[10px] font-bold text-slate-400 tracking-widest uppercase bg-white/50 backdrop-blur p-2 rounded-lg">
                <span>Total Nodes: {graph.nodes.length}</span>
                <span>Relationships: {graph.relationships.length}</span>
                <span>Graph Density: {graph.nodes.length > 1 ? (graph.relationships.length / (graph.nodes.length * (graph.nodes.length - 1))).toFixed(2) : '0.00'}</span>
            </div>
          </Card>
        </div>

        <div className="space-y-6">
          <Card className="h-full">
            <CardHeader className="border-b">
              <CardTitle className="text-sm">Entity Inspector</CardTitle>
            </CardHeader>
            <CardContent className="p-4 space-y-6">
                <div className="space-y-3">
                    <h4 className="text-[10px] uppercase font-bold text-slate-400 tracking-widest">Active nodes</h4>
                    <div className="space-y-2 max-h-96 overflow-y-auto pr-2">
                        {graph.nodes.map(node => (
                            <div key={node.id} className="group rounded-xl border border-slate-100 bg-white p-3 hover:border-blue-200 hover:shadow-sm transition-all cursor-pointer">
                                <div className="flex items-center justify-between">
                                    <span className={`text-[10px] font-black px-1.5 py-0.5 rounded ${node.type === 'Subject' ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600'}`}>
                                        {node.type}
                                    </span>
                                    <span className="text-[10px] text-slate-300">#{node.id.slice(0, 4)}</span>
                                </div>
                                <p className="mt-2 text-sm font-semibold text-slate-800">{node.id}</p>
                                <div className="mt-2 grid grid-cols-2 gap-1 text-[10px] text-slate-500">
                                    {Object.entries(node.properties).slice(0, 2).map(([k, v]) => (
                                        <div key={k} className="flex gap-1">
                                            <span className="font-bold opacity-50">{k}:</span>
                                            <span>{String(v)}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="space-y-3 pt-6 border-t border-slate-100">
                    <h4 className="text-[10px] uppercase font-bold text-slate-400 tracking-widest">Relations</h4>
                    <div className="space-y-2">
                        {graph.relationships.length > 0 ? (
                          <div className="flex items-center gap-2 text-xs text-slate-600 bg-slate-50 p-2 rounded-lg">
                              <Database className="h-3 w-3" />
                              <span>locatedIn: <strong>{graph.relationships.length}</strong></span>
                          </div>
                        ) : (
                          <p className="text-xs text-slate-400">No relationships detected.</p>
                        )}
                    </div>
                </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}
