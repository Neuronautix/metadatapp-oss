import { Activity, Database, LineChart, Workflow } from 'lucide-react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { formatDateTime } from '@/lib/format.ts'
import { buildSinusoidCurve } from '@/domain/dvc/circadianModel.ts'
import type { CageHcmResponse, CageHcmSystem } from '../cage.types.ts'
import {
  CartesianGrid,
  Line,
  LineChart as RechartsLineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'

const formatNumber = (value: number) => new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(value)

const formatProvenanceAt = (value: string) => (value.includes('T') ? formatDateTime(value) : value)

const traceColors = ['#2563eb', '#16a34a', '#dc2626', '#7c3aed']

const Metric = ({ label, value }: { label: string; value: string | number }) => (
  <div className="rounded-lg border border-line bg-surface-2 p-3">
    <p className="text-xs uppercase tracking-[0.18em] text-slate-400">{label}</p>
    <p className="mt-1 text-lg font-semibold text-slate-900">{value}</p>
  </div>
)

const SystemHeader = ({ system }: { system: CageHcmSystem }) => (
  <div className="grid gap-4 lg:grid-cols-[1fr_280px]">
    <div>
      <div className="flex flex-wrap items-center gap-2">
        <h3 className="text-base font-semibold text-slate-900">{system.system}</h3>
        {system.sourceAppCodes.map((source) => (
          <Badge key={source} variant="outline">{source}</Badge>
        ))}
      </div>
      <p className="mt-1 text-sm text-slate-600">{system.experiment.name}</p>
      {system.experiment.project ? (
        <p className="text-xs text-slate-500">{system.experiment.project.name}</p>
      ) : null}
      {system.experiment.protocol ? (
        <p className="mt-3 max-w-3xl text-sm text-slate-600">{system.experiment.protocol}</p>
      ) : null}
    </div>
    <div className="grid grid-cols-2 gap-2">
      <Metric label="Lights on" value={system.lightsOnUtcHour ?? 'N/A'} />
      <Metric label="Light phase" value={system.lightDurationHours ? `${system.lightDurationHours}h` : 'N/A'} />
    </div>
  </div>
)

const VariablesPanel = ({ system }: { system: CageHcmSystem }) => (
  <Card>
    <CardHeader>
      <CardTitle className="flex items-center gap-2 text-base">
        <Database className="h-4 w-4" />
        Variables + modalities
      </CardTitle>
      <CardDescription>Measured variables linked to this cage and HCM system.</CardDescription>
    </CardHeader>
    <CardContent className="space-y-4">
      <div className="flex flex-wrap gap-2">
        {system.modalities.length ? system.modalities.map((modality) => (
          <Badge key={modality} variant="default">{modality}</Badge>
        )) : <span className="text-sm text-slate-500">No modalities recorded.</span>}
      </div>
      <div className="overflow-x-auto rounded-lg border border-line">
        <table className="w-full text-sm">
          <thead className="bg-surface-2 text-xs uppercase tracking-[0.14em] text-slate-500">
            <tr>
              <th className="px-3 py-2 text-left font-semibold">Variable</th>
              <th className="px-3 py-2 text-left font-semibold">Ontology ID</th>
              <th className="px-3 py-2 text-left font-semibold">Tecniplast ID</th>
            </tr>
          </thead>
          <tbody>
            {system.variables.map((variable) => (
              <tr key={variable.id} className="border-t border-line">
                <td className="px-3 py-2 font-medium text-slate-800">{variable.name}</td>
                <td className="px-3 py-2 text-slate-600">{variable.externalId ?? 'N/A'}</td>
                <td className="px-3 py-2 text-slate-600">{variable.tecniplastExternalId ?? 'N/A'}</td>
              </tr>
            ))}
            {system.variables.length === 0 ? (
              <tr>
                <td className="px-3 py-4 text-sm text-slate-500" colSpan={3}>No variables linked.</td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </CardContent>
  </Card>
)

const EnvironmentPanel = ({ system }: { system: CageHcmSystem }) => (
  <Card>
    <CardHeader>
      <CardTitle className="text-base">Environmental parameters</CardTitle>
      <CardDescription>Cage environment linked to HCM extraction and working-day activity.</CardDescription>
    </CardHeader>
    <CardContent className="grid grid-cols-2 gap-3 md:grid-cols-3">
      <Metric label="Temp" value={system.environment.temperature != null ? `${formatNumber(system.environment.temperature)} C` : 'N/A'} />
      <Metric label="Humidity" value={system.environment.humidity != null ? `${formatNumber(system.environment.humidity)}%` : 'N/A'} />
      <Metric label="Light" value={system.environment.lightIntensity != null ? `${formatNumber(system.environment.lightIntensity)} lux` : 'N/A'} />
      <Metric label="Noise" value={system.environment.noise != null ? `${formatNumber(system.environment.noise)} dB` : 'N/A'} />
      <Metric label="Light cycle" value={system.environment.lightCycle ?? 'N/A'} />
      <Metric label="Human move" value={system.environment.humanMovementWorkday != null ? formatNumber(system.environment.humanMovementWorkday) : 'N/A'} />
    </CardContent>
  </Card>
)

const SinusoidTrace = ({ system }: { system: CageHcmSystem }) => {
  const rows = system.sinusoidMetadata.slice(0, traceColors.length)
  const data = Array.from({ length: 24 }, (_, hour) => {
    const point: Record<string, number> = { hour }
    rows.forEach((row, index) => {
      point[`trace${index}`] = Number(buildSinusoidCurve({
        amplitude: row.amplitude,
        baseline: row.mesor,
        phase: row.phase,
        noise: 0,
      })[hour].value.toFixed(2))
    })
    return point
  })

  if (!rows.length) {
    return (
      <div className="flex h-64 items-center justify-center rounded-lg border border-line bg-surface-2 text-sm text-slate-500">
        No fitted sinusoid trace available.
      </div>
    )
  }

  return (
    <div className="h-64 rounded-lg border border-line bg-surface-2 p-3">
      <ResponsiveContainer width="100%" height="100%">
        <RechartsLineChart data={data} margin={{ top: 8, right: 16, bottom: 4, left: 0 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="rgba(15, 23, 42, 0.12)" />
          <XAxis dataKey="hour" tickFormatter={(hour: number) => `${hour}h`} tick={{ fontSize: 11 }} interval={3} />
          <YAxis tick={{ fontSize: 11 }} width={34} />
          <Tooltip
            labelFormatter={(label: unknown) => `Hour ${label}:00`}
            formatter={(value: unknown, name: unknown) => [
              typeof value === 'number' ? formatNumber(value) : String(value),
              rows[Number(String(name).replace('trace', ''))]?.date ?? String(name),
            ]}
          />
          {rows.map((row, index) => (
            <Line
              key={row.id}
              type="monotone"
              dataKey={`trace${index}`}
              name={row.date}
              stroke={traceColors[index]}
              strokeWidth={2.5}
              dot={false}
              isAnimationActive={false}
            />
          ))}
        </RechartsLineChart>
      </ResponsiveContainer>
    </div>
  )
}

const SinusoidPanel = ({ system }: { system: CageHcmSystem }) => (
  <Card>
    <CardHeader>
      <CardTitle className="flex items-center gap-2 text-base">
        <LineChart className="h-4 w-4" />
        Sinusoid metadata
      </CardTitle>
      <CardDescription>MIROsine fit parameters attached to this cage.</CardDescription>
    </CardHeader>
    <CardContent>
      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(360px,1fr)]">
        <SinusoidTrace system={system} />
        <div className="overflow-x-auto rounded-lg border border-line">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 text-xs uppercase tracking-[0.14em] text-slate-500">
              <tr>
                {['Date', 'Amplitude', 'Mesor', 'Phase', 'Peak hour', 'Source task'].map((heading) => (
                  <th key={heading} className="px-3 py-2 text-left font-semibold">{heading}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {system.sinusoidMetadata.map((row) => (
                <tr key={row.id} className="border-t border-line">
                  <td className="px-3 py-2 text-slate-700">{row.date}</td>
                  <td className="px-3 py-2 text-slate-700">{formatNumber(row.amplitude)}</td>
                  <td className="px-3 py-2 text-slate-700">{formatNumber(row.mesor)}</td>
                  <td className="px-3 py-2 text-slate-700">{formatNumber(row.phase)}</td>
                  <td className="px-3 py-2 text-slate-700">{formatNumber(row.peakHour)}</td>
                  <td className="px-3 py-2 font-mono text-xs text-slate-600">{row.sourceTaskId ?? 'N/A'}</td>
                </tr>
              ))}
              {system.sinusoidMetadata.length === 0 ? (
                <tr>
                  <td className="px-3 py-4 text-sm text-slate-500" colSpan={6}>No sinusoid metadata linked.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </div>
    </CardContent>
  </Card>
)

const MeasuresPanel = ({ system }: { system: CageHcmSystem }) => (
  <Card>
    <CardHeader>
      <CardTitle className="flex items-center gap-2 text-base">
        <Activity className="h-4 w-4" />
        HCM measures
      </CardTitle>
      <CardDescription>Cage-level measurements linked through variables and source tasks.</CardDescription>
    </CardHeader>
    <CardContent>
      <div className="overflow-x-auto rounded-lg border border-line">
        <table className="w-full text-sm">
          <thead className="bg-surface-2 text-xs uppercase tracking-[0.14em] text-slate-500">
            <tr>
              {['Recorded', 'Variable', 'Type', 'Value', 'Source task'].map((heading) => (
                <th key={heading} className="px-3 py-2 text-left font-semibold">{heading}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {system.measures.map((measure) => (
              <tr key={measure.id} className="border-t border-line">
                <td className="px-3 py-2 text-slate-700">{formatDateTime(measure.recordedAt)}</td>
                <td className="px-3 py-2 font-medium text-slate-800">{measure.variable.name}</td>
                <td className="px-3 py-2 text-slate-700">{measure.dataType}</td>
                <td className="px-3 py-2 text-slate-700">{formatNumber(measure.value)}</td>
                <td className="px-3 py-2 font-mono text-xs text-slate-600">{measure.sourceTaskId ?? 'N/A'}</td>
              </tr>
            ))}
            {system.measures.length === 0 ? (
              <tr>
                <td className="px-3 py-4 text-sm text-slate-500" colSpan={5}>No HCM measures linked.</td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </CardContent>
  </Card>
)

const ProvenancePanel = ({ system }: { system: CageHcmSystem }) => (
  <Card>
    <CardHeader>
      <CardTitle className="flex items-center gap-2 text-base">
        <Workflow className="h-4 w-4" />
        Provenance
      </CardTitle>
      <CardDescription>Source app and extraction task links for attached metadata.</CardDescription>
    </CardHeader>
    <CardContent className="space-y-2">
      {system.provenance.map((item, index) => (
        <div key={`${item.type}-${item.sourceTaskId ?? index}`} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-surface-2 p-3 text-sm">
          <div>
            <p className="font-medium text-slate-800">{item.label}</p>
            <p className="text-xs text-slate-500">{formatProvenanceAt(item.at)}</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="outline">{item.type}</Badge>
            {item.sourceAppCode ? <Badge variant="default">{item.sourceAppCode}</Badge> : null}
            <span className="font-mono text-xs text-slate-500">{item.sourceTaskId ?? 'no task id'}</span>
          </div>
        </div>
      ))}
      {system.provenance.length === 0 ? (
        <p className="text-sm text-slate-500">No provenance records linked.</p>
      ) : null}
    </CardContent>
  </Card>
)

export function CageHcmTab({ hcm }: { hcm: CageHcmResponse }) {
  if (hcm.systems.length === 0) {
    return (
      <EmptyState
        title="No HCM metadata linked"
        description="This cage has no Home Cage Monitoring system, measures, or sinusoid metadata attached."
      />
    )
  }

  return (
    <div className="space-y-6">
      {hcm.systems.map((system) => (
        <section key={system.id} className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Home Cage Monitoring</CardTitle>
              <CardDescription>Linked system, study context, and light-cycle configuration.</CardDescription>
            </CardHeader>
            <CardContent>
              <SystemHeader system={system} />
            </CardContent>
          </Card>
          <VariablesPanel system={system} />
          <SinusoidPanel system={system} />
          <EnvironmentPanel system={system} />
          <MeasuresPanel system={system} />
          <ProvenancePanel system={system} />
        </section>
      ))}
    </div>
  )
}
