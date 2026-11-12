import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowRight, ArrowLeft, Settings2, AlertTriangle, CheckCircle2, ChevronRight, Search, Zap, Plus, Layers, BookMarked, Trash2, Save } from 'lucide-react'
import { Card, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { PageHeader } from '@/components/PageHeader'
import { METADATAPP_GRAPH_SCHEMA } from '@/domain/graph/graph.schema'
import { useCuration } from './curation-context'
import { useGetSessionSummary, useListMappingTemplates, useSaveMappingTemplate, useDeleteMappingTemplate } from './curation.api'

export function MappingStudio() {
  const { report, mappings, setMappings, sessionId } = useCuration()
  const navigate = useNavigate()
  const [searchTerm, setSearchTerm] = useState('')
  const [saveTemplateName, setSaveTemplateName] = useState('')
  const [showSaveForm, setShowSaveForm] = useState(false)

  const { data: sessionSummary } = useGetSessionSummary(sessionId)
  const { data: templates = [] } = useListMappingTemplates()
  const saveMutation = useSaveMappingTemplate()
  const deleteMutation = useDeleteMappingTemplate()

  // Auto-populate mappings from backend proposals if local state is empty
  useEffect(() => {
    const proposals = Array.isArray(sessionSummary?.schema_proposals) 
        ? sessionSummary.schema_proposals 
        : (sessionSummary?.schema_proposals as any)?.['hydra:member'] ?? []

    if (mappings.length === 0 && proposals.length) {
        const initialMappings = proposals.map((p: any) => {
            const [entity, prop] = p.target_field.split('.')
            return {
                sourceColumn: p.header,
                targetEntity: entity,
                targetProperty: prop
            }
        })
        setMappings(initialMappings)
    }
  }, [sessionSummary, mappings.length, setMappings])

  const headers = report?.headers ?? []
  const filteredHeaders = headers.filter(h => h.toLowerCase().includes(searchTerm.toLowerCase()))
  const mappingPercent = headers.length === 0 ? 0 : Math.round((mappings.length / headers.length) * 100)

  const addMapping = (header: string, entity: string, property: string) => {
    setMappings([...mappings.filter(m => m.sourceColumn !== header), {
      sourceColumn: header,
      targetEntity: entity,
      targetProperty: property
    }])
  }

  const removeMapping = (header: string) => {
    setMappings(mappings.filter(m => m.sourceColumn !== header))
  }

  const loadTemplate = (templateId: string) => {
    const tpl = templates.find(t => t.id === templateId)
    if (tpl) setMappings(tpl.mappings)
  }

  const handleSaveTemplate = () => {
    const name = saveTemplateName.trim()
    if (!name || mappings.length === 0) return
    saveMutation.mutate({ name, mappings }, {
      onSuccess: () => {
        setSaveTemplateName('')
        setShowSaveForm(false)
      },
    })
  }

  if (!report) {
    return (
      <div className="space-y-6">
        <PageHeader
          title="Mapping Studio"
          subtitle="Align imported headers with Metadatapp schema properties."
        />
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 p-12 text-center backdrop-blur-sm">
          <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <Settings2 className="h-6 w-6" />
          </div>
          <h3 className="text-lg font-semibold text-slate-900">No Data Source</h3>
          <p className="mx-auto mt-2 max-w-sm text-sm text-slate-500">
            Please import a CSV or Excel file to begin the semantic mapping process.
          </p>
          <Button variant="outline" className="mt-6 rounded-xl border-slate-200" onClick={() => navigate('../import')}>
            Go to Import
          </Button>
        </div>
      </div>
    )
  }

  return (
    <div className="flex h-[calc(100vh-12rem)] flex-col gap-6 overflow-hidden">
      {/* Header Area */}
      <div className="flex items-start justify-between">
        <PageHeader 
          title="Semantic Mapping Studio" 
          subtitle="Precision alignment of raw telemetry to domain-specific knowledge schemas."
        />
        <div className="flex flex-col items-end gap-2">
          {/* Template toolbar */}
          <div className="flex items-center gap-2">
            {/* Load template selector */}
            {templates.length > 0 && (
              <div className="flex items-center gap-1.5">
                <BookMarked className="h-3.5 w-3.5 text-slate-400" />
                <select
                  aria-label="Load a saved mapping template"
                  className="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-blue-200 shadow-sm"
                  defaultValue=""
                  onChange={(e) => {
                    if (e.target.value) loadTemplate(e.target.value)
                    e.target.value = ''
                  }}
                >
                  <option value="" disabled>Load template…</option>
                  {templates.map(t => (
                    <option key={t.id} value={t.id}>{t.name} ({t.mappings.length} fields)</option>
                  ))}
                </select>
              </div>
            )}
            {/* Save as template */}
            {showSaveForm ? (
              <div className="flex items-center gap-1.5">
                <input
                  autoFocus
                  type="text"
                  placeholder="Template name…"
                  aria-label="Template name"
                  value={saveTemplateName}
                  onChange={(e) => setSaveTemplateName(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter') handleSaveTemplate(); if (e.key === 'Escape') setShowSaveForm(false) }}
                  className="rounded-xl border border-blue-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-blue-200 shadow-sm w-44"
                />
                <Button size="sm" className="rounded-xl h-7 px-2.5 text-xs bg-blue-600 text-white hover:bg-blue-700"
                  onClick={handleSaveTemplate}
                  disabled={!saveTemplateName.trim() || mappings.length === 0 || saveMutation.isPending}
                >
                  <Save className="h-3 w-3 mr-1" /> Save
                </Button>
                <Button size="sm" variant="ghost" className="rounded-xl h-7 px-2 text-xs text-slate-400"
                  onClick={() => setShowSaveForm(false)}>
                  Cancel
                </Button>
              </div>
            ) : (
              <Button variant="outline" size="sm" className="rounded-xl border-slate-200 bg-white shadow-sm text-xs h-7"
                onClick={() => setShowSaveForm(true)} disabled={mappings.length === 0}>
                <Save className="mr-1.5 h-3 w-3" /> Save as template
              </Button>
            )}
          </div>
          {/* Manage saved templates (inline delete list) */}
          {templates.length > 0 && (
            <div className="flex flex-wrap gap-1">
              {templates.map(t => (
                <span key={t.id} className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                  {t.name}
                  <button
                    aria-label={`Delete template ${t.name}`}
                    className="ml-0.5 text-slate-400 hover:text-rose-500 transition-colors"
                    onClick={() => deleteMutation.mutate(t.id)}
                  >
                    <Trash2 className="h-2.5 w-2.5" />
                  </button>
                </span>
              ))}
            </div>
          )}
          {/* Main nav buttons */}
          <div className="flex items-center gap-2">
            <div className="flex items-center gap-1.5 px-3 py-1 bg-amber-50 rounded-full border border-amber-100 text-[10px] font-bold text-amber-700 uppercase tracking-wider">
                <Zap className="h-3 w-3" /> AI Assisted
            </div>
            <Button variant="outline" size="sm" className="rounded-xl border-slate-200 bg-white shadow-sm" onClick={() => navigate(-1)}>
              <ArrowLeft className="mr-2 h-3.5 w-3.5" /> Back
            </Button>
            <Button size="sm" className="rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-200 hover:bg-blue-700" 
                    onClick={() => navigate('../validation')} disabled={mappings.length === 0}>
              Validate Integration <ArrowRight className="ml-2 h-3.5 w-3.5" />
            </Button>
          </div>
        </div>
      </div>

      {/* Workspace Grid */}
      <div className="flex grow gap-6 overflow-hidden">
        {/* Left: Mapping Matrix */}
        <div className="flex flex-1 flex-col gap-4 overflow-hidden">
            <Card className="border-slate-200 bg-white shadow-sm flex flex-col flex-1 overflow-hidden rounded-2xl">
                <div className="p-6 border-b border-slate-100 bg-slate-50/30 flex items-center justify-between">
                    <div className="space-y-1">
                        <CardTitle className="text-base font-black text-slate-900">Field Correlation Matrix</CardTitle>
                        <CardDescription className="text-xs">Select target entities and properties for each source column.</CardDescription>
                    </div>
                    <div className="relative w-64 group">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 transition-colors group-focus-within:text-blue-500" />
                        <input 
                            type="text" 
                            placeholder="Search source headers..." 
                            className="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                </div>
                
                <div className="flex-1 overflow-y-auto min-h-0">
                    <div className="divide-y divide-slate-100">
                        {filteredHeaders.map(header => {
                        const mapping = mappings.find(m => m.sourceColumn === header)
                        return (
                            <div key={header} className="flex items-center justify-between p-6 hover:bg-slate-50/30 transition-all duration-300 group">
                                <div className="flex items-center gap-6">
                                    <div className={`flex h-12 w-12 items-center justify-center rounded-2xl transition-all shadow-sm
                                        ${mapping ? 'bg-blue-600 text-white rotate-0' : 'bg-slate-100 text-slate-400 -rotate-3 group-hover:rotate-0 group-hover:bg-slate-200'}`}>
                                        <Settings2 className="h-5 w-5" />
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-sm font-black text-slate-900 flex items-center gap-2">
                                            {header}
                                            {mapping && <CheckCircle2 className="h-3.5 w-3.5 text-blue-500" />}
                                        </p>
                                        <div className="flex items-center gap-2">
                                            {mapping ? (
                                                <div className="flex items-center gap-1.5 px-2 py-0.5 bg-blue-50 text-blue-600 rounded-md text-[10px] font-bold border border-blue-100 uppercase italic">
                                                    {mapping.targetEntity} <ChevronRight className="h-2 w-2" /> {mapping.targetProperty}
                                                </div>
                                            ) : (
                                                <span className="text-[10px] text-slate-400 font-bold uppercase tracking-widest italic flex items-center gap-1.5 opacity-60">
                                                    <div className="h-1 w-1 rounded-full bg-slate-300" /> Pending correlation
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-4">
                                    {mapping && (
                                        <Button variant="ghost" size="sm" className="text-slate-400 hover:text-rose-500 h-8 px-2" onClick={() => removeMapping(header)}>
                                            Reset
                                        </Button>
                                    )}
                                    <div className="relative">
                                        <select 
                                            className={`appearance-none rounded-xl border px-4 py-2.5 text-[11px] font-black uppercase tracking-wider outline-none transition-all cursor-pointer shadow-sm min-w-[180px]
                                                ${mapping 
                                                    ? 'bg-white border-blue-500 text-blue-600' 
                                                    : 'bg-slate-50 border-slate-200 text-slate-500 hover:border-slate-300'}`}
                                            onChange={(e) => {
                                                if (!e.target.value) {
                                                    removeMapping(header)
                                                    return
                                                }
                                                const [entity, prop] = e.target.value.split('.')
                                                if (entity && prop) addMapping(header, entity, prop)
                                            }}
                                            value={mapping ? `${mapping.targetEntity}.${mapping.targetProperty}` : ''}
                                        >
                                            <option value="">Ignore Field</option>
                                            {METADATAPP_GRAPH_SCHEMA.entities.map(entity => (
                                                <optgroup key={entity.name} label={entity.name} className="bg-white text-slate-900 font-black">
                                                    {entity.properties.map(prop => (
                                                        <option key={prop.name} value={`${entity.name}.${prop.name}`} className="font-semibold">
                                                            {prop.name}
                                                        </option>
                                                    ))}
                                                </optgroup>
                                            ))}
                                        </select>
                                        <div className={`pointer-events-none absolute right-3 top-1/2 -translate-y-1/2
                                            ${mapping ? 'text-blue-500' : 'text-slate-400'}`}>
                                            <Plus className="h-3 w-3" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )
                        })}
                        {filteredHeaders.length === 0 && (
                            <div className="p-12 text-center">
                                <p className="text-sm text-slate-400 italic">No headers matching "{searchTerm}"</p>
                            </div>
                        )}
                    </div>
                </div>
            </Card>
        </div>

        {/* Right: Insights & Progress */}
        <div className="w-80 flex flex-col gap-6 h-full">
            <Card className="border-slate-200 bg-white shadow-xl flex flex-col overflow-hidden rounded-2xl">
                <div className="p-5 border-b border-slate-100 bg-slate-50/30">
                    <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Correlation Logic</h4>
                    <div className="space-y-4">
                        <div className="flex items-center justify-between text-3xl font-black text-slate-900">
                            <span>{mappings.length} <span className="text-xs font-bold text-slate-300">/ {headers.length}</span></span>
                            <span className="text-blue-600 text-2xl tracking-tighter">{mappingPercent}%</span>
                        </div>
                        <div className="h-4 w-full rounded-full bg-slate-100 p-1 flex">
                            <div 
                                className="h-2 rounded-full bg-blue-600 transition-all duration-1000 flex items-center justify-center shadow-[0_0_12px_rgba(59,130,246,0.5)]" 
                                style={{ width: `${Math.max(mappingPercent, 2)}%` }}
                            />
                        </div>
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto p-5 space-y-8">
                    <div className="space-y-4">
                        <h5 className="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                             <Layers className="h-3 w-3" /> Target Architecture
                        </h5>
                        <div className="space-y-2">
                            {METADATAPP_GRAPH_SCHEMA.entities.map(entity => {
                                const count = mappings.filter(m => m.targetEntity === entity.name).length
                                return (
                                    <div key={entity.name} className="group flex items-center justify-between rounded-xl bg-slate-50 p-3 border border-transparent hover:bg-white hover:border-slate-200 transition-all cursor-default">
                                        <div className="flex items-center gap-3">
                                            <div className="h-6 w-6 rounded-lg bg-white flex items-center justify-center shadow-sm text-[10px] font-black text-slate-400">
                                                {entity.name.slice(0, 1)}
                                            </div>
                                            <span className="text-xs font-bold text-slate-700">{entity.name}</span>
                                        </div>
                                        <Badge variant="outline" className={`rounded-lg h-6 min-w-[24px] flex items-center justify-center p-0 text-[10px] font-black
                                            ${count > 0 ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-slate-100 text-slate-400 border-transparent opacity-40'}`}>
                                            {count}
                                        </Badge>
                                    </div>
                                )
                            })}
                        </div>
                    </div>

                    <div className="rounded-2xl bg-slate-900 p-5 shadow-inner border border-slate-800 space-y-4">
                         <h5 className="text-[10px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                             <AlertTriangle className="h-3 w-3 text-amber-500" /> Structural Guard
                        </h5>
                        <div className="space-y-3">
                            <p className="text-[11px] text-slate-400 leading-relaxed">
                                Ensure that critical identifiers like <strong>Subject.id</strong> are correlated correctly to enable topological synthesis.
                            </p>
                            <div className="flex flex-col gap-2">
                                <div className="flex items-center gap-2 text-[10px] font-bold text-slate-500">
                                    <div className="h-1.5 w-1.5 rounded-full bg-blue-500" /> Normalized Data Types
                                </div>
                                <div className="flex items-center gap-2 text-[10px] font-bold text-slate-500">
                                    <div className="h-1.5 w-1.5 rounded-full bg-blue-500" /> Relation Synthesis
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="p-4 bg-slate-50/50 border-t border-slate-100">
                    <Button variant="ghost" className="w-full text-xs text-slate-400 hover:text-slate-600 italic font-medium" 
                            onClick={() => setMappings([])} disabled={mappings.length === 0}>
                        Clear Matrix
                    </Button>
                </div>
            </Card>
        </div>
      </div>
    </div>
  )
}
