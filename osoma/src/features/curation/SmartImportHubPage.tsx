import { useNavigate } from 'react-router-dom'
import {
  Upload,
  Filter,
  Sparkles,
  ArrowRight,
  Database,
  GitMerge,
  ShieldCheck,
  Network,
  FileText,
  LayoutGrid,
  CheckCircle2,
  Zap,
  Bot,
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { PageHeader } from '@/components/PageHeader'
import { useFeatureFlag } from '@/feature-flags/useFeatureFlag'

type ToolCardProps = {
  icon: React.ComponentType<{ className?: string }>
  iconBg: string
  iconColor: string
  badge?: string
  title: string
  description: string
  steps?: string[]
  cta: string
  onClick: () => void
  disabled?: boolean
  disabledReason?: string
}

function ToolCard({
  icon: Icon,
  iconBg,
  iconColor,
  badge,
  title,
  description,
  steps,
  cta,
  onClick,
  disabled,
  disabledReason,
}: ToolCardProps) {
  return (
    <Card className="flex flex-col border-slate-200 bg-white shadow-sm rounded-2xl overflow-hidden transition-shadow hover:shadow-md">
      <CardHeader className="p-6 pb-4">
        <div className="flex items-start justify-between gap-4">
          <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${iconBg}`}>
            <Icon className={`h-6 w-6 ${iconColor}`} />
          </div>
          {badge && (
            <Badge className="shrink-0 rounded-lg text-[10px] font-bold uppercase tracking-wider">
              {badge}
            </Badge>
          )}
        </div>
        <CardTitle className="mt-4 text-base font-bold text-slate-900">{title}</CardTitle>
        <CardDescription className="text-sm text-slate-500 leading-relaxed">{description}</CardDescription>
      </CardHeader>

      {steps && steps.length > 0 && (
        <CardContent className="px-6 pb-4 pt-0">
          <ol className="space-y-1">
            {steps.map((step, i) => (
              <li key={i} className="flex items-center gap-2 text-xs text-slate-500">
                <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-600">
                  {i + 1}
                </span>
                {step}
              </li>
            ))}
          </ol>
        </CardContent>
      )}

      <CardContent className="mt-auto px-6 pb-6 pt-0">
        {disabled ? (
          <div className="space-y-2">
            <Button className="w-full rounded-xl" disabled>
              {cta} <ArrowRight className="ml-2 h-4 w-4" />
            </Button>
            {disabledReason && (
              <p className="text-center text-[11px] text-slate-400">{disabledReason}</p>
            )}
          </div>
        ) : (
          <Button className="w-full rounded-xl bg-slate-900 text-white hover:bg-black" onClick={onClick}>
            {cta} <ArrowRight className="ml-2 h-4 w-4" />
          </Button>
        )}
      </CardContent>
    </Card>
  )
}

export function SmartImportHubPage() {
  const navigate = useNavigate()
  const metadatappEnabled = useFeatureFlag('metadatapp-feat.enabled')
  const curationWorkflowEnabled = useFeatureFlag('feature.curationWorkflow.enabled')
  const importFeatureEnabled = useFeatureFlag('import.enabled')
  const dataEntryFeatureEnabled = useFeatureFlag('dataEntry.enabled')
  const importEnabled = metadatappEnabled && importFeatureEnabled
  const dataEntryEnabled = metadatappEnabled && dataEntryFeatureEnabled

  return (
    <div className="space-y-8">
      <PageHeader
        title="Smart Import & Curation Hub"
        subtitle="All data ingestion and AI-powered curation tools in one place. Choose the right pathway for your data."
      />

      {/* Workflow pipeline overview */}
      <Card className="border-slate-200 bg-gradient-to-br from-slate-50 to-white shadow-sm rounded-2xl">
        <CardContent className="p-6">
          <p className="mb-4 text-xs font-bold uppercase tracking-widest text-slate-400">
            Recommended Pipeline
          </p>
          <div className="flex flex-wrap items-center gap-2">
            {[
              { label: 'Upload', icon: Upload },
              { label: 'Map', icon: GitMerge },
              { label: 'Validate', icon: ShieldCheck },
              { label: 'Resolve', icon: Network },
              { label: 'Patch Review', icon: Filter },
              { label: 'Apply', icon: Database },
            ].map(({ label, icon: Icon }, i, arr) => (
              <div key={label} className="flex items-center gap-2">
                <div className="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                  <Icon className="h-3.5 w-3.5 text-slate-500" />
                  <span className="text-xs font-semibold text-slate-700">{label}</span>
                </div>
                {i < arr.length - 1 && <ArrowRight className="h-3.5 w-3.5 text-slate-300" />}
              </div>
            ))}
          </div>
          <p className="mt-4 text-xs text-slate-400">
            The <strong>Advanced Curation Workflow</strong> guides you through all steps automatically. 
            The <strong>AI Assistant</strong> can help at any stage.
          </p>
        </CardContent>
      </Card>

      {/* Primary tools */}
      <div>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-widest text-slate-400">
          Import Pathways
        </h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <ToolCard
            icon={Zap}
            iconBg="bg-blue-50"
            iconColor="text-blue-600"
            badge="Recommended"
            title="Advanced Curation Workflow"
            description="Full AI-powered pipeline: upload CSV or Excel, auto-map columns to your schema, validate, resolve identities, and apply patches with human review at every step."
            steps={[
              'Upload file (CSV / XLSX)',
              'AI schema mapping proposals',
              'Data validation & QC',
              'Identity resolution',
              'Patch review & apply',
            ]}
            cta="Open Curation Workflow"
            onClick={() => navigate('/curation/import')}
            disabled={!curationWorkflowEnabled}
            disabledReason="Enable the 'API-Backed Data Curation' feature flag to use this tool."
          />

          <ToolCard
            icon={Upload}
            iconBg="bg-emerald-50"
            iconColor="text-emerald-600"
            title="CSV Import Wizard"
            description="Lightweight 4-step wizard to import CSV data with column mapping, subject ID lookup, and confirmation. Ideal for quick imports with a known format."
            steps={[
              'Upload CSV file',
              'Map columns to fields',
              'Lookup subject IDs',
              'Confirm & import',
            ]}
            cta="Open CSV Import"
            onClick={() => navigate('/import/csv')}
            disabled={!importEnabled}
            disabledReason={
              !metadatappEnabled
                ? "Requires the 'MetaDatAPI Features' flag to be enabled."
                : "Enable the 'CSV Import Wizard' feature flag to use this tool."
            }
          />

          <ToolCard
            icon={LayoutGrid}
            iconBg="bg-violet-50"
            iconColor="text-violet-600"
            title="Spreadsheet Data Entry"
            description="Bulk data entry grid with paste support and inline validation. Enter or paste structured data directly into the system without a file upload."
            cta="Open Data Entry Grid"
            onClick={() => navigate('/data-entry')}
            disabled={!dataEntryEnabled}
            disabledReason={
              !metadatappEnabled
                ? "Requires the 'MetaDatAPI Features' flag to be enabled."
                : "Enable the 'Spreadsheet Data Entry' feature flag to use this tool."
            }
          />
        </div>
      </div>

      {/* AI & curation tools */}
      <div>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-widest text-slate-400">
          AI & Smart Curation Tools
        </h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <ToolCard
            icon={Bot}
            iconBg="bg-amber-50"
            iconColor="text-amber-600"
            badge="AI"
            title="AI Assistant"
            description="Conversational assistant for metadata drafting, query filter generation, FAIR assessment explanations, and guided curation decisions. Uses MCP tools to query live data."
            cta="Open AI Assistant"
            onClick={() => navigate('/assistant')}
          />

          <ToolCard
            icon={Sparkles}
            iconBg="bg-pink-50"
            iconColor="text-pink-600"
            badge="AI"
            title="Per-Subject LLM Curation"
            description="Request LLM-powered curation suggestions for individual subjects. Detects value normalisation issues, missing required fields, and ontology candidates. Suggestions require human review before being applied."
            cta="Browse Subjects"
            onClick={() => navigate('/subjects')}
          />

          <ToolCard
            icon={FileText}
            iconBg="bg-slate-100"
            iconColor="text-slate-600"
            title="Schema Profiles & MappView"
            description="Manage reusable mapping profiles and explore the schema graph with MappView. Inspect how columns are mapped to entities and find the right target fields."
            cta="Open Schema Profiles"
            onClick={() => navigate('/curation/schemas')}
            disabled={!curationWorkflowEnabled}
            disabledReason="Enable the 'API-Backed Data Curation' feature flag to use this tool."
          />
        </div>
      </div>

      {/* How to choose */}
      <Card className="border-slate-200 bg-white shadow-sm rounded-2xl">
        <CardHeader className="p-6 pb-4">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="h-5 w-5 text-emerald-500" />
            <CardTitle className="text-base font-bold text-slate-900">How to choose the right tool</CardTitle>
          </div>
        </CardHeader>
        <CardContent className="px-6 pb-6 pt-0">
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-3">
              <div className="rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                <p className="text-xs font-bold text-blue-700">Use the Advanced Curation Workflow when:</p>
                <ul className="mt-2 space-y-1 text-xs text-blue-600">
                  <li>• The file has unknown or complex column names</li>
                  <li>• You need AI to propose schema mappings</li>
                  <li>• The data contains new subjects that need resolution</li>
                  <li>• You want full audit trail and patch review</li>
                </ul>
              </div>
              <div className="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                <p className="text-xs font-bold text-emerald-700">Use the CSV Import Wizard when:</p>
                <ul className="mt-2 space-y-1 text-xs text-emerald-600">
                  <li>• You have a well-known CSV format</li>
                  <li>• You want a quick, lightweight import</li>
                  <li>• Subjects already exist in the system</li>
                </ul>
              </div>
            </div>
            <div className="space-y-3">
              <div className="rounded-xl border border-violet-100 bg-violet-50/50 p-4">
                <p className="text-xs font-bold text-violet-700">Use Data Entry Grid when:</p>
                <ul className="mt-2 space-y-1 text-xs text-violet-600">
                  <li>• You are manually entering small amounts of data</li>
                  <li>• You want to paste from a spreadsheet application</li>
                  <li>• No file to upload — data is in your clipboard</li>
                </ul>
              </div>
              <div className="rounded-xl border border-amber-100 bg-amber-50/50 p-4">
                <p className="text-xs font-bold text-amber-700">Use the AI Assistant when:</p>
                <ul className="mt-2 space-y-1 text-xs text-amber-600">
                  <li>• You need guidance on metadata requirements</li>
                  <li>• You want to draft filters or export structure</li>
                  <li>• You need a FAIR score explained</li>
                </ul>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
