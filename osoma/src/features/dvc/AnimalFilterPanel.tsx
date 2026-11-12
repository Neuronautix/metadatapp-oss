import { useRef, useState } from 'react'
import { Filter, RotateCcw } from 'lucide-react'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import type { FilterCriteria } from '@/domain/dvc/metadataMatcher.ts'

const STRAINS = ['C57BL/6J', 'BALB/c', 'C57BL/6N', '129/SvEv', 'FVB/N', 'DBA/2J', 'A/J', 'CBA/J', 'NZB', 'SJL/J']
const TREATMENTS = ['Vehicle', 'Drug-A 10mg/kg', 'Drug-A 30mg/kg', 'Drug-B 5mg/kg', 'Drug-B 15mg/kg', 'Placebo']
const SANITARY_STATUSES = ['SPF', 'CV', 'GF', 'Ex-GF', 'Axenic']
const INVESTIGATIONS = ['PRJ-ONCOLOGY-01', 'PRJ-NEURO-02', 'PRJ-CARDIO-03', 'PRJ-IMMUNO-04', 'PRJ-METABOLIC-05']
const FACILITIES = ['NRX', 'BIO', 'PHX', 'ARC', 'SCI']
const LIGHT_CYCLES = ['12:12', '14:10', '16:8']

type Props = {
  criteria: FilterCriteria
  onChange: (criteria: FilterCriteria) => void
  systems?: string[]
}

const SelectField = ({
  label,
  value,
  options,
  onChange,
}: {
  label: string
  value: string | undefined
  options: string[]
  onChange: (v: string | undefined) => void
}) => (
  <div className="flex flex-col gap-1">
    <label className="text-xs font-medium text-muted-foreground">{label}</label>
    <select
      className="rounded-md border border-input bg-background px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
      value={value ?? ''}
      onChange={(e) => onChange(e.target.value || undefined)}
    >
      <option value="">All</option>
      {options.map((opt) => (
        <option key={opt} value={opt}>
          {opt}
        </option>
      ))}
    </select>
  </div>
)

const RangeField = ({
  label,
  step = '0.1',
  onCommit,
}: {
  label: string
  step?: string
  onCommit: (min: number | undefined, max: number | undefined) => void
}) => {
  const minRef = useRef('')
  const maxRef = useRef('')
  const [minVal, setMinVal] = useState('')
  const [maxVal, setMaxVal] = useState('')

  const commit = () =>
    onCommit(
      minRef.current ? parseFloat(minRef.current) : undefined,
      maxRef.current ? parseFloat(maxRef.current) : undefined,
    )

  return (
    <div className="flex flex-col gap-1">
      <label className="text-xs font-medium text-muted-foreground">{label}</label>
      <div className="flex gap-1">
        <Input
          type="number"
          step={step}
          placeholder="Min"
          className="h-8 text-xs"
          value={minVal}
          onChange={(e) => { minRef.current = e.target.value; setMinVal(e.target.value) }}
          onBlur={commit}
        />
        <Input
          type="number"
          step={step}
          placeholder="Max"
          className="h-8 text-xs"
          value={maxVal}
          onChange={(e) => { maxRef.current = e.target.value; setMaxVal(e.target.value) }}
          onBlur={commit}
        />
      </div>
    </div>
  )
}

export function AnimalFilterPanel({ criteria, onChange, systems = [] }: Props) {
  const [resetKey, setResetKey] = useState(0)

  const reset = () => {
    setResetKey((k) => k + 1)
    onChange({})
  }

  const selectedSystems = criteria.dvcSystems ?? []
  const toggleSystem = (system: string) => {
    const next = selectedSystems.includes(system)
      ? selectedSystems.filter((item) => item !== system)
      : [...selectedSystems, system]
    onChange({ ...criteria, dvcSystems: next.length ? next : undefined })
  }

  return (
    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="mb-3 flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm font-semibold">
          <Filter className="h-4 w-4 text-primary" />
          Filters
        </div>
        <Button variant="ghost" size="sm" onClick={reset} className="h-7 px-2 text-xs">
          <RotateCcw className="mr-1 h-3 w-3" />
          Reset
        </Button>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3">
        {systems.length ? (
          <div className="col-span-2 flex flex-col gap-2 sm:col-span-3 lg:col-span-2 xl:col-span-3">
            <div className="flex items-center justify-between gap-2">
              <label className="text-xs font-medium text-muted-foreground">HCM systems</label>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => onChange({ ...criteria, dvcSystems: undefined })}
                className="h-7 px-2 text-xs"
              >
                All
              </Button>
            </div>
            <div className="flex flex-wrap gap-2">
              {systems.map((system) => (
                <label key={system} className="flex items-center gap-2 rounded-md border border-input px-2 py-1 text-xs">
                  <input
                    type="checkbox"
                    checked={selectedSystems.includes(system)}
                    onChange={() => toggleSystem(system)}
                  />
                  {system}
                </label>
              ))}
            </div>
          </div>
        ) : null}

        <SelectField
          label="Strain"
          value={criteria.strain}
          options={STRAINS}
          onChange={(v) => onChange({ ...criteria, strain: v })}
        />
        <SelectField
          label="Sex"
          value={criteria.sex}
          options={['male', 'female']}
          onChange={(v) => onChange({ ...criteria, sex: v })}
        />
        <SelectField
          label="Treatment"
          value={criteria.treatment}
          options={TREATMENTS}
          onChange={(v) => onChange({ ...criteria, treatment: v })}
        />
        <SelectField
          label="Sanitary Status"
          value={criteria.sanitaryStatus}
          options={SANITARY_STATUSES}
          onChange={(v) => onChange({ ...criteria, sanitaryStatus: v })}
        />
        <SelectField
          label="Investigation"
          value={criteria.project}
          options={INVESTIGATIONS}
          onChange={(v) => onChange({ ...criteria, project: v })}
        />
        <SelectField
          label="Facility"
          value={criteria.facility}
          options={FACILITIES}
          onChange={(v) => onChange({ ...criteria, facility: v })}
        />
        <SelectField
          label="Light Cycle"
          value={criteria.lightCycle}
          options={LIGHT_CYCLES}
          onChange={(v) => onChange({ ...criteria, lightCycle: v })}
        />

      </div>

      <div className="mt-3 grid grid-cols-2 gap-3">
        <RangeField key={`age-${resetKey}`} label="Age (weeks)" step="1"
          onCommit={(min, max) => onChange({ ...criteria, ageMin: min, ageMax: max })}
        />
        <RangeField key={`temp-${resetKey}`} label="Temp (°C)"
          onCommit={(min, max) => onChange({ ...criteria, tempMin: min, tempMax: max })}
        />
        <RangeField key={`humidity-${resetKey}`} label="Humidity (%)"
          onCommit={(min, max) => onChange({ ...criteria, humidityMin: min, humidityMax: max })}
        />
        <RangeField key={`light-${resetKey}`} label="Light (lux)" step="1"
          onCommit={(min, max) => onChange({ ...criteria, lightMin: min, lightMax: max })}
        />
        <RangeField key={`noise-${resetKey}`} label="Noise (dB)"
          onCommit={(min, max) => onChange({ ...criteria, noiseMin: min, noiseMax: max })}
        />
        <RangeField key={`humanmove-${resetKey}`} label="Human move"
          onCommit={(min, max) => onChange({ ...criteria, humanMoveMin: min, humanMoveMax: max })}
        />
        <RangeField key={`amplitude-${resetKey}`} label="Amplitude"
          onCommit={(min, max) => onChange({ ...criteria, amplitudeMin: min, amplitudeMax: max })}
        />
        <RangeField key={`phase-${resetKey}`} label="Phase (rad)" step="0.01"
          onCommit={(min, max) => onChange({ ...criteria, phaseMin: min, phaseMax: max })}
        />
      </div>
    </div>
  )
}
