import { useEffect, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { CheckCircle2, AlertTriangle, ShieldCheck, XCircle, ChevronRight, Info } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/PageHeader'
import { validateImportData } from '@/domain/curation/validation.engine'
import { useCuration } from './curation-context'

export function ValidationPage() {
  const { parsedRows, mappings, setValidationReport } = useCuration()
  const navigate = useNavigate()

  const report = useMemo(() => {
    if (parsedRows.length === 0 || mappings.length === 0) return null
    return validateImportData(parsedRows, mappings)
  }, [parsedRows, mappings])

  // Persist the latest validation result into shared context so GraphViewerPage
  // can enforce that only validated data reaches the graph step.
  useEffect(() => {
    setValidationReport(report)
  }, [report, setValidationReport])

  if (!report) {
    return (
      <div className="space-y-6">
        <PageHeader
          title="Data Validation"
          subtitle="Pre-ingestion quality checks and relationship consistency."
        />
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center">
          <p className="text-sm text-slate-500">No mapping configured yet. Complete the mapping step first.</p>
          <Button variant="outline" className="mt-4" onClick={() => navigate('../mapping')}>
            Go to Mapping
          </Button>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <PageHeader 
          title="Data Validation" 
          subtitle="Pre-ingestion quality checks and relationship consistency."
        />
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => navigate(-1)}>Back to Mapping</Button>
          <Button onClick={() => navigate('../graph')} disabled={!report.isValid}>
            Generate Graph
          </Button>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-4">
        <Card className={`md:col-span-1 border-none shadow-none ${report.isValid ? 'bg-green-600' : 'bg-red-600'} text-white`}>
            <CardContent className="flex flex-col items-center justify-center p-8 text-center space-y-4">
                {report.isValid ? (
                    <ShieldCheck className="h-16 w-16" />
                ) : (
                    <XCircle className="h-16 w-16" />
                )}
                <div>
                    <h3 className="text-xl font-bold">{report.isValid ? 'Schema Validated' : 'Validation Failed'}</h3>
                    <p className="text-sm opacity-80 mt-1">
                        {report.isValid ? 'Your data is ready for migration.' : 'Critical errors must be resolved.'}
                    </p>
                </div>
                <div className="grid grid-cols-2 gap-4 w-full">
                    <div className="bg-white/10 rounded-xl p-3">
                        <p className="text-2xl font-black">{report.counts.errors}</p>
                        <p className="text-[10px] uppercase font-bold opacity-60">Errors</p>
                    </div>
                    <div className="bg-white/10 rounded-xl p-3">
                        <p className="text-2xl font-black">{report.counts.warnings}</p>
                        <p className="text-[10px] uppercase font-bold opacity-60">Warnings</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div className="md:col-span-3 space-y-6">
            <Card>
                <CardHeader className="border-b bg-slate-50/50">
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle>Validation Issues</CardTitle>
                            <CardDescription>Review conflicts and metadata drift.</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    {report.issues.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 bg-slate-50/30">
                            <CheckCircle2 className="h-12 w-12 text-green-500 mb-4" />
                            <p className="font-semibold text-slate-800">Perfect Dataset Architecture</p>
                            <p className="text-xs text-slate-500 mt-1">Zero issues detected across all mapped properties.</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {report.issues.map((issue, i) => (
                                <div key={i} className="flex items-start gap-4 p-4 hover:bg-slate-50 transition-colors">
                                    <div className={`mt-0.5 rounded-full p-1.5 ${issue.level === 'error' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600'}`}>
                                        {issue.level === 'error' ? <XCircle className="h-4 w-4" /> : <AlertTriangle className="h-4 w-4" />}
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-center justify-between">
                                            <p className="text-sm font-semibold text-slate-800">{issue.message}</p>
                                            <span className="text-[10px] font-bold text-slate-400">Row {issue.row ?? 'N/A'}</span>
                                        </div>
                                        {issue.column && (
                                            <div className="mt-1 flex items-center gap-2">
                                                <span className="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-bold uppercase">
                                                    Col: {issue.column}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-slate-300">
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            <div className="grid gap-4 md:grid-cols-2">
                <Card className="bg-slate-50">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Info className="h-4 w-4 text-blue-500" />
                            <CardTitle className="text-sm">Ingestion Strategy</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="text-xs text-slate-500 space-y-2">
                        <p>Entities will be upserted based on <strong>ID</strong> mapping.</p>
                        <p>Relationships will be created for <strong>Cage</strong> and <strong>Sample</strong> entities.</p>
                    </CardContent>
                </Card>
                <Card className="bg-slate-50">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="h-4 w-4 text-green-500" />
                            <CardTitle className="text-sm">Compliance Report</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="text-xs text-slate-500 space-y-2">
                        <p>All required Metadatapp invariants are checked against current mapping.</p>
                    </CardContent>
                </Card>
            </div>
        </div>
      </div>
    </div>
  )
}
