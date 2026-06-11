import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { createScenario } from './scenario.api.ts'
import type { Scenario } from '@/domain/scenario/scenario.types.ts'
import type { ScopeType } from '@/domain/scope.ts'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Label } from '@/components/ui/label.tsx'

const toLocalInput = (iso: string) => iso.slice(0, 16)

export function ScenarioBuilder({
  scopeType,
  scopeId,
  scopeLabel,
  baseTimestamp,
  onScenarioCreated,
}: {
  scopeType: ScopeType
  scopeId: string
  scopeLabel?: string
  baseTimestamp: string
  onScenarioCreated: (scenario: Scenario) => void
}) {
  const [label, setLabel] = useState('HVAC pre-cool')
  const [timepoint, setTimepoint] = useState(toLocalInput(baseTimestamp))
  const [tempDelta, setTempDelta] = useState(-2)
  const [humidityDelta, setHumidityDelta] = useState(3)
  const [tempMax, setTempMax] = useState(27)

  const mutation = useMutation({
    mutationFn: () =>
      createScenario({
        label,
        baseTimestamp: new Date(timepoint).toISOString(),
        scope: { type: scopeType, id: scopeId, label: scopeLabel },
        overrides: {
          metricAdjustments: { temperature: tempDelta, humidity: humidityDelta },
          thresholds: { temperature: { max: tempMax } },
          note: 'Shift HVAC cycle and widen humidity guardrail',
        },
      }),
    onSuccess: onScenarioCreated,
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle>Scenario builder</CardTitle>
        <CardDescription>Fork from a past moment and tweak rules safely.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-3 md:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="scenario-label">Label</Label>
            <Input
              id="scenario-label"
              value={label}
              onChange={(event) => setLabel(event.target.value)}
              placeholder="Ventilation change, new threshold, etc."
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="scenario-time">Base timestamp</Label>
            <Input
              id="scenario-time"
              type="datetime-local"
              value={timepoint}
              onChange={(event) => setTimepoint(event.target.value)}
            />
          </div>
        </div>

        <div className="grid gap-3 md:grid-cols-3">
          <NumberField
            label="Temperature delta (°C)"
            value={tempDelta}
            onChange={setTempDelta}
            helper="Negative cools the room."
          />
          <NumberField
            label="Humidity delta (%)"
            value={humidityDelta}
            onChange={setHumidityDelta}
            helper="Positive adds moisture."
          />
          <NumberField
            label="Max temperature (°C)"
            value={tempMax}
            onChange={setTempMax}
            helper="Upper bound for alerts."
          />
        </div>

        <Button onClick={() => mutation.mutate()} disabled={mutation.isPending} size="sm">
          {mutation.isPending ? 'Saving...' : 'Create scenario'}
        </Button>
        {mutation.isError ? (
          <p className="text-sm text-amber-600">Unable to create scenario. Please retry.</p>
        ) : null}
      </CardContent>
    </Card>
  )
}

function NumberField({
  label,
  helper,
  value,
  onChange,
}: {
  label: string
  helper?: string
  value: number
  onChange: (value: number) => void
}) {
  return (
    <div className="space-y-1.5">
      <Label>{label}</Label>
      <Input
        type="number"
        value={value}
        onChange={(event) => onChange(Number(event.target.value))}
        className="h-9"
      />
      {helper ? <p className="text-xs text-slate-500">{helper}</p> : null}
    </div>
  )
}
