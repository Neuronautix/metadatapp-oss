import { type FormEvent, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, CheckCircle2 } from 'lucide-react'
import { createInvestigation, getInvestigationOperators } from './investigation.api.ts'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useToast } from '@/components/ui/toast.tsx'

const steps = [
  { id: 'details', label: 'Study details' },
  { id: 'operators', label: 'Assign operators' },
]

export function NewInvestigationPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { toast } = useToast()

  const [stepIndex, setStepIndex] = useState(0)
  const [formState, setFormState] = useState({
    name: '',
    description: '',
    startDate: new Date().toISOString().slice(0, 10),
    endDate: '',
    assignedOperatorIds: [] as string[],
  })

  const operatorsQuery = useQuery({
    queryKey: ['investigation-operators'],
    queryFn: getInvestigationOperators,
  })

  const mutation = useMutation({
    mutationFn: () =>
      createInvestigation({
        name: formState.name.trim(),
        description: formState.description.trim(),
        species: 'Mouse',
        startDate: formState.startDate,
        endDate: formState.endDate || undefined,
        assignedOperatorIds: formState.assignedOperatorIds,
      }),
    onSuccess: (investigation) => {
      queryClient.invalidateQueries({ queryKey: ['investigations'] })
      toast({ title: 'Investigation created', description: 'Mouse study ready for animal assignment.' })
      navigate(`/investigations/${investigation.id}`)
    },
  })

  const toggleOperator = (operatorId: string) => {
    setFormState((prev) => {
      const selected = prev.assignedOperatorIds.includes(operatorId)
      return {
        ...prev,
        assignedOperatorIds: selected
          ? prev.assignedOperatorIds.filter((id) => id !== operatorId)
          : [...prev.assignedOperatorIds, operatorId],
      }
    })
  }

  const selectedOperators = useMemo(
    () => operatorsQuery.data?.data.filter((operator) => formState.assignedOperatorIds.includes(operator.id)) ?? [],
    [operatorsQuery.data, formState.assignedOperatorIds]
  )

  const validateDetails = () => {
    if (!formState.name.trim()) {
      toast({ title: 'Investigation name required', description: 'Add a investigation name to continue.' })
      return false
    }

    if (!formState.description.trim()) {
      toast({ title: 'Description required', description: 'Add a short investigation description.' })
      return false
    }

    if (!formState.startDate) {
      toast({ title: 'Start date required', description: 'Pick a investigation start date.' })
      return false
    }

    return true
  }

  const validateOperators = () => {
    if (formState.assignedOperatorIds.length === 0) {
      toast({ title: 'Assign operators', description: 'Select at least one operator.' })
      return false
    }

    return true
  }

  const handleNext = () => {
    if (!validateDetails()) {
      return
    }
    setStepIndex(1)
  }

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (!validateOperators()) {
      return
    }

    mutation.mutate()
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" asChild>
          <Link to="/investigations">
            <ArrowLeft className="h-4 w-4" />
            Back
          </Link>
        </Button>
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Investigation wizard</p>
          <h2 className="font-display text-2xl">New Mouse Investigation</h2>
        </div>
      </div>

      <div className="rounded-2xl border border-line bg-white p-4">
        <div className="flex flex-wrap items-center gap-6">
          {steps.map((step, index) => (
            <div key={step.id} className="flex items-center gap-3">
              <div
                className={`flex h-9 w-9 items-center justify-center rounded-full border text-sm font-semibold ${
                  index <= stepIndex ? 'border-slate-900 bg-slate-900 text-white' : 'border-line text-slate-400'
                }`}
              >
                {index < stepIndex ? <CheckCircle2 className="h-4 w-4" /> : index + 1}
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Step {index + 1}</p>
                <p className="text-sm font-semibold text-slate-700">{step.label}</p>
              </div>
            </div>
          ))}
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{stepIndex === 0 ? 'Study details' : 'Assign operators'}</CardTitle>
          <CardDescription>
            {stepIndex === 0
              ? 'Define the mouse investigation scope before assigning the team.'
              : 'Pick the operators covering this investigation.'}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form className="space-y-6" onSubmit={handleSubmit}>
            {stepIndex === 0 ? (
              <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-2">
                  <div>
                    <Label htmlFor="investigation-name">Investigation name</Label>
                    <Input
                      id="investigation-name"
                      value={formState.name}
                      onChange={(event) => setFormState((prev) => ({ ...prev, name: event.target.value }))}
                      placeholder="T-cell priming study"
                    />
                  </div>
                  <div>
                    <Label htmlFor="investigation-species">Species</Label>
                    <Input id="investigation-species" value="Mouse" disabled />
                  </div>
                  <div>
                    <Label htmlFor="investigation-start">Start date</Label>
                    <Input
                      id="investigation-start"
                      type="date"
                      value={formState.startDate}
                      onChange={(event) => setFormState((prev) => ({ ...prev, startDate: event.target.value }))}
                    />
                  </div>
                  <div>
                    <Label htmlFor="investigation-end">End date</Label>
                    <Input
                      id="investigation-end"
                      type="date"
                      value={formState.endDate}
                      onChange={(event) => setFormState((prev) => ({ ...prev, endDate: event.target.value }))}
                    />
                  </div>
                </div>

                <div>
                  <Label htmlFor="investigation-description">Description</Label>
                  <Textarea
                    id="investigation-description"
                    value={formState.description}
                    onChange={(event) => setFormState((prev) => ({ ...prev, description: event.target.value }))}
                    placeholder="Briefly describe the study goals."
                  />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                  <Button type="button" onClick={handleNext}>
                    Next: Assign operators
                  </Button>
                  <Button variant="outline" asChild>
                    <Link to="/investigations">Cancel</Link>
                  </Button>
                </div>
              </div>
            ) : (
              <div className="space-y-6">
                <div className="grid gap-4 rounded-2xl border border-line bg-slate-50 p-4 text-sm text-slate-600 md:grid-cols-3">
                  <div>
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Study</p>
                    <p className="font-semibold text-slate-700">{formState.name || 'Untitled investigation'}</p>
                    <p className="text-xs text-slate-400">{formState.startDate}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Operators</p>
                    <p className="font-semibold text-slate-700">{formState.assignedOperatorIds.length} selected</p>
                    <p className="text-xs text-slate-400">{selectedOperators.map((operator) => operator.name).join(', ')}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Species</p>
                    <p className="font-semibold text-slate-700">Mouse</p>
                    <p className="text-xs text-slate-400">Add animals after creation</p>
                  </div>
                </div>

                <div className="space-y-3">
                  <Label>Assigned operators</Label>
                  {operatorsQuery.isLoading ? (
                    <div className="grid gap-2 md:grid-cols-2">
                      {Array.from({ length: 4 }).map((_, index) => (
                        <Skeleton key={index} className="h-14 w-full" />
                      ))}
                    </div>
                  ) : operatorsQuery.isError ? (
                    <p className="text-sm text-rose-500">Unable to load operator list.</p>
                  ) : (
                    <div className="grid gap-2 md:grid-cols-2">
                      {operatorsQuery.data?.data.map((operator) => (
                        <label
                          key={operator.id}
                          className="flex items-center justify-between rounded-xl border border-line bg-white px-3 py-2 text-sm"
                        >
                          <span>
                            <span className="font-medium text-slate-700">{operator.name}</span>
                            <span className="block text-xs text-slate-400">{operator.role}</span>
                          </span>
                          <input
                            type="checkbox"
                            className="h-4 w-4 accent-slate-900"
                            checked={formState.assignedOperatorIds.includes(operator.id)}
                            onChange={() => toggleOperator(operator.id)}
                          />
                        </label>
                      ))}
                    </div>
                  )}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                  <Button type="button" variant="outline" onClick={() => setStepIndex(0)}>
                    Back
                  </Button>
                  <Button type="submit" disabled={mutation.isPending}>
                    Create investigation
                  </Button>
                </div>
              </div>
            )}
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
