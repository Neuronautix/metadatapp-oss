import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Plus, Trash2 } from 'lucide-react'
import { createAtmpStudy, getAtmpStudy, updateAtmpStudy } from './atmp.api.ts'
import type { EndpointMatrixEntry, StudyModel, CoiNode } from '@/domain/atmp/atmp.types.ts'
import {
  administrationRoutes,
  atmpCategories,
  endpointStatuses,
  regulatoryStatuses,
} from '@/domain/atmp/atmp.enums.ts'
import { atmpStudyInputSchema, type AtmpStudyInput } from '@/domain/atmp/atmp.validation.ts'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'

const splitLines = (value: string) => value.split('\n').map((line) => line.trim()).filter(Boolean)

const createStudyModel = (): StudyModel => ({
  species: '',
  strain: '',
  modelType: '',
  groupSize: 1,
  notes: '',
})

const createCoiNode = (stage: CoiNode['stage']): CoiNode => ({
  stage,
  label: '',
  batchId: '',
  site: '',
  date: '',
  material: '',
  labTrail: [''],
  animalGroups: [''],
})

const createEndpointEntry = (): EndpointMatrixEntry => ({
  id: `END-${Math.floor(Math.random() * 100000)}`,
  name: '',
  category: '',
  timepoint: '',
  status: 'Pending',
  owner: '',
  summary: '',
  details: [''],
})

const createDefaultStudy = (): AtmpStudyInput => ({
  investigationID: '',
  chainOfIdentityID: '',
  title: '',
  atmpCategory: atmpCategories[0],
  regulatoryStatus: regulatoryStatuses[0],
  administrationRoute: administrationRoutes[0],
  dosage: { value: 0, unit: '', frequency: '' },
  species: '',
  modelSummary: '',
  productCharacterization: {
    modality: '',
    vectorType: '',
    vectorCopyNumber: 0,
    viabilityPercent: 0,
    donorType: '',
    scaffoldMaterials: [''],
    criticalQualityAttributes: [''],
  },
  studyModels: [createStudyModel()],
  coiTimeline: [createCoiNode('startingMaterial'), createCoiNode('activeSubstance'), createCoiNode('preclinicalModel')],
  endpointMatrix: [createEndpointEntry()],
})

export function AtmpStudyFormPage({ mode }: { mode: 'create' | 'edit' }) {
  const { investigationID } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const isEditMode = mode === 'edit'

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['atmp-study', investigationID],
    queryFn: () => getAtmpStudy(investigationID ?? ''),
    enabled: isEditMode && Boolean(investigationID),
  })

  const [formState, setFormState] = useState<AtmpStudyInput>(() => createDefaultStudy())

  useEffect(() => {
    if (!data) return
    setFormState({
      investigationID: data.investigationID,
      chainOfIdentityID: data.chainOfIdentityID,
      title: data.title,
      atmpCategory: data.atmpCategory,
      regulatoryStatus: data.regulatoryStatus,
      administrationRoute: data.administrationRoute,
      dosage: {
        value: data.dosage.value,
        unit: data.dosage.unit,
        frequency: data.dosage.frequency ?? '',
      },
      species: data.species,
      modelSummary: data.modelSummary,
      productCharacterization: {
        ...data.productCharacterization,
        scaffoldMaterials: data.productCharacterization.scaffoldMaterials.length
          ? data.productCharacterization.scaffoldMaterials
          : [''],
        criticalQualityAttributes: data.productCharacterization.criticalQualityAttributes.length
          ? data.productCharacterization.criticalQualityAttributes
          : [''],
      },
      studyModels: data.studyModels.length ? data.studyModels : [createStudyModel()],
      coiTimeline: data.coiTimeline.length
        ? data.coiTimeline
        : [createCoiNode('startingMaterial'), createCoiNode('activeSubstance'), createCoiNode('preclinicalModel')],
      endpointMatrix: data.endpointMatrix.length ? data.endpointMatrix : [createEndpointEntry()],
    })
  }, [data])

  const normalizedPayload = useMemo<AtmpStudyInput>(() => {
    return {
      ...formState,
      investigationID: formState.investigationID.trim(),
      chainOfIdentityID: formState.chainOfIdentityID.trim(),
      title: formState.title.trim(),
      species: formState.species.trim(),
      modelSummary: formState.modelSummary.trim(),
      productCharacterization: {
        ...formState.productCharacterization,
        modality: formState.productCharacterization.modality.trim(),
        vectorType: formState.productCharacterization.vectorType.trim(),
        donorType: formState.productCharacterization.donorType.trim(),
        scaffoldMaterials: splitLines(formState.productCharacterization.scaffoldMaterials.join('\n')),
        criticalQualityAttributes: splitLines(formState.productCharacterization.criticalQualityAttributes.join('\n')),
      },
      studyModels: formState.studyModels.map((model) => ({
        ...model,
        species: model.species.trim(),
        strain: model.strain.trim(),
        modelType: model.modelType.trim(),
        notes: model.notes?.trim() || undefined,
      })),
      coiTimeline: formState.coiTimeline.map((entry) => ({
        ...entry,
        label: entry.label.trim(),
        batchId: entry.batchId.trim(),
        site: entry.site.trim(),
        date: entry.date,
        material: entry.material.trim(),
        labTrail: splitLines(entry.labTrail.join('\n')),
        animalGroups: splitLines(entry.animalGroups.join('\n')),
      })),
      endpointMatrix: formState.endpointMatrix.map((entry) => ({
        ...entry,
        name: entry.name.trim(),
        category: entry.category.trim(),
        timepoint: entry.timepoint.trim(),
        owner: entry.owner.trim(),
        summary: entry.summary.trim(),
        details: splitLines(entry.details.join('\n')),
      })),
    }
  }, [formState])

  const createMutation = useMutation({
    mutationFn: async () => {
      const timestamp = new Date().toISOString()
      return createAtmpStudy({
        ...normalizedPayload,
        createdAt: timestamp,
        updatedAt: timestamp,
      })
    },
    onSuccess: (study) => {
      queryClient.invalidateQueries({ queryKey: ['atmp-studies'] })
      queryClient.invalidateQueries({ queryKey: ['atmp-study', study.investigationID] })
      toast({ title: 'ATMP study created', description: 'Metadata saved to the registry.' })
      navigate(`/atmp/${study.investigationID}`)
    },
    onError: (err) => {
      toast({ title: 'Create failed', description: (err as Error).message })
    },
  })

  const updateMutation = useMutation({
    mutationFn: async () => {
      const timestamp = new Date().toISOString()
      return updateAtmpStudy(investigationID ?? '', {
        ...normalizedPayload,
        updatedAt: timestamp,
      })
    },
    onSuccess: (study) => {
      queryClient.invalidateQueries({ queryKey: ['atmp-studies'] })
      queryClient.invalidateQueries({ queryKey: ['atmp-study', study.investigationID] })
      toast({ title: 'ATMP study updated', description: 'Metadata synced to the dashboard.' })
      navigate(`/atmp/${study.investigationID}`)
    },
    onError: (err) => {
      toast({ title: 'Update failed', description: (err as Error).message })
    },
  })

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const validation = atmpStudyInputSchema.safeParse(normalizedPayload)
    if (!validation.success) {
      const issue = validation.error.issues[0]
      toast({ title: 'Validation required', description: issue?.message ?? 'Check required fields.' })
      return
    }

    if (isEditMode) {
      updateMutation.mutate()
    } else {
      createMutation.mutate()
    }
  }

  if (isEditMode && isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (isEditMode && (isError || !data)) {
    return (
      <EmptyState
        title="ATMP study unavailable"
        description={(error as Error)?.message ?? 'ATMP study record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['atmp-study', investigationID] })}
      />
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to={isEditMode && investigationID ? `/atmp/${investigationID}` : '/atmp'}>
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">
              {isEditMode ? 'Edit ATMP study' : 'New ATMP study'}
            </p>
            <h2 className="font-display text-2xl">ATMP Metadata</h2>
          </div>
        </div>
      </div>

      <form className="space-y-6" onSubmit={handleSubmit}>
        <Card>
          <CardHeader>
            <CardTitle>Study basics</CardTitle>
            <CardDescription>Core identifiers and regulatory posture.</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 md:grid-cols-2">
            <div>
              <Label htmlFor="investigation-id">Investigation ID</Label>
              <Input
                id="investigation-id"
                value={formState.investigationID}
                onChange={(event) =>
                  setFormState((prev) => ({ ...prev, investigationID: event.target.value }))
                }
                disabled={isEditMode}
              />
            </div>
            <div>
              <Label htmlFor="coi-id">Chain of Identity ID</Label>
              <Input
                id="coi-id"
                value={formState.chainOfIdentityID}
                onChange={(event) =>
                  setFormState((prev) => ({ ...prev, chainOfIdentityID: event.target.value }))
                }
              />
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="title">Study title</Label>
              <Input
                id="title"
                value={formState.title}
                onChange={(event) => setFormState((prev) => ({ ...prev, title: event.target.value }))}
              />
            </div>
            <div>
              <Label htmlFor="category">ATMP category</Label>
              <select
                id="category"
                className="h-10 w-full rounded-xl border border-line bg-white px-3 text-sm text-slate-600"
                value={formState.atmpCategory}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    atmpCategory: event.target.value as AtmpStudyInput['atmpCategory'],
                  }))
                }
              >
                {atmpCategories.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label htmlFor="regulatory">Regulatory status</Label>
              <select
                id="regulatory"
                className="h-10 w-full rounded-xl border border-line bg-white px-3 text-sm text-slate-600"
                value={formState.regulatoryStatus}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    regulatoryStatus: event.target.value as AtmpStudyInput['regulatoryStatus'],
                  }))
                }
              >
                {regulatoryStatuses.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label htmlFor="route">Administration route</Label>
              <select
                id="route"
                className="h-10 w-full rounded-xl border border-line bg-white px-3 text-sm text-slate-600"
                value={formState.administrationRoute}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    administrationRoute: event.target.value as AtmpStudyInput['administrationRoute'],
                  }))
                }
              >
                {administrationRoutes.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label htmlFor="species">Species</Label>
              <Input
                id="species"
                value={formState.species}
                onChange={(event) => setFormState((prev) => ({ ...prev, species: event.target.value }))}
              />
            </div>
            <div>
              <Label htmlFor="dosage-value">Dosage value</Label>
              <Input
                id="dosage-value"
                type="number"
                value={formState.dosage.value}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    dosage: { ...prev.dosage, value: event.target.valueAsNumber || 0 },
                  }))
                }
              />
            </div>
            <div>
              <Label htmlFor="dosage-unit">Dosage unit</Label>
              <Input
                id="dosage-unit"
                value={formState.dosage.unit}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    dosage: { ...prev.dosage, unit: event.target.value },
                  }))
                }
              />
            </div>
            <div>
              <Label htmlFor="dosage-frequency">Dosage frequency</Label>
              <Input
                id="dosage-frequency"
                value={formState.dosage.frequency}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    dosage: { ...prev.dosage, frequency: event.target.value },
                  }))
                }
              />
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="model-summary">Model summary</Label>
              <Textarea
                id="model-summary"
                value={formState.modelSummary}
                onChange={(event) =>
                  setFormState((prev) => ({ ...prev, modelSummary: event.target.value }))
                }
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Product characterization</CardTitle>
            <CardDescription>Critical quality attributes and product readiness.</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 md:grid-cols-2">
            <div>
              <Label htmlFor="modality">Modality</Label>
              <Input
                id="modality"
                value={formState.productCharacterization.modality}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    productCharacterization: {
                      ...prev.productCharacterization,
                      modality: event.target.value,
                    },
                  }))
                }
              />
            </div>
            <div>
              <Label htmlFor="vector-type">Vector type</Label>
              <Input
                id="vector-type"
                value={formState.productCharacterization.vectorType}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    productCharacterization: {
                      ...prev.productCharacterization,
                      vectorType: event.target.value,
                    },
                  }))
                }
              />
            </div>
            <div>
              <Label htmlFor="vector-copy">Vector copy number</Label>
              <Input
                id="vector-copy"
                type="number"
                value={formState.productCharacterization.vectorCopyNumber}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    productCharacterization: {
                      ...prev.productCharacterization,
                      vectorCopyNumber: event.target.valueAsNumber || 0,
                    },
                  }))
                }
              />
            </div>
            <div>
              <Label htmlFor="viability">Viability %</Label>
              <Input
                id="viability"
                type="number"
                value={formState.productCharacterization.viabilityPercent}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    productCharacterization: {
                      ...prev.productCharacterization,
                      viabilityPercent: event.target.valueAsNumber || 0,
                    },
                  }))
                }
              />
            </div>
            <div>
              <Label htmlFor="donor-type">Donor type</Label>
              <Input
                id="donor-type"
                value={formState.productCharacterization.donorType}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    productCharacterization: {
                      ...prev.productCharacterization,
                      donorType: event.target.value,
                    },
                  }))
                }
              />
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="scaffold-materials">Scaffold materials (one per line)</Label>
              <Textarea
                id="scaffold-materials"
                value={formState.productCharacterization.scaffoldMaterials.join('\n')}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    productCharacterization: {
                      ...prev.productCharacterization,
                      scaffoldMaterials: splitLines(event.target.value),
                    },
                  }))
                }
              />
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="cqa">Critical quality attributes (one per line)</Label>
              <Textarea
                id="cqa"
                value={formState.productCharacterization.criticalQualityAttributes.join('\n')}
                onChange={(event) =>
                  setFormState((prev) => ({
                    ...prev,
                    productCharacterization: {
                      ...prev.productCharacterization,
                      criticalQualityAttributes: splitLines(event.target.value),
                    },
                  }))
                }
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Study models</CardTitle>
            <CardDescription>Species, strain, and cohorts.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {formState.studyModels.map((model, index) => (
              <div key={`model-${index}`} className="rounded-2xl border border-line bg-surface-2 p-4">
                <div className="mb-3 flex items-center justify-between">
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Model {index + 1}</p>
                  {formState.studyModels.length > 1 ? (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() =>
                        setFormState((prev) => ({
                          ...prev,
                          studyModels: prev.studyModels.filter((_, idx) => idx !== index),
                        }))
                      }
                    >
                      <Trash2 className="h-4 w-4" />
                      Remove
                    </Button>
                  ) : null}
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                  <div>
                    <Label>Species</Label>
                    <Input
                      value={model.species}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          studyModels: prev.studyModels.map((entry, idx) =>
                            idx === index ? { ...entry, species: event.target.value } : entry
                          ),
                        }))
                      }
                    />
                  </div>
                  <div>
                    <Label>Strain</Label>
                    <Input
                      value={model.strain}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          studyModels: prev.studyModels.map((entry, idx) =>
                            idx === index ? { ...entry, strain: event.target.value } : entry
                          ),
                        }))
                      }
                    />
                  </div>
                  <div>
                    <Label>Model type</Label>
                    <Input
                      value={model.modelType}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          studyModels: prev.studyModels.map((entry, idx) =>
                            idx === index ? { ...entry, modelType: event.target.value } : entry
                          ),
                        }))
                      }
                    />
                  </div>
                  <div>
                    <Label>Group size</Label>
                    <Input
                      type="number"
                      value={model.groupSize}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          studyModels: prev.studyModels.map((entry, idx) =>
                            idx === index
                              ? { ...entry, groupSize: event.target.valueAsNumber || 0 }
                              : entry
                          ),
                        }))
                      }
                    />
                  </div>
                  <div className="md:col-span-2">
                    <Label>Notes</Label>
                    <Textarea
                      value={model.notes ?? ''}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          studyModels: prev.studyModels.map((entry, idx) =>
                            idx === index ? { ...entry, notes: event.target.value } : entry
                          ),
                        }))
                      }
                    />
                  </div>
                </div>
              </div>
            ))}
            <Button
              type="button"
              variant="outline"
              onClick={() => setFormState((prev) => ({ ...prev, studyModels: [...prev.studyModels, createStudyModel()] }))}
            >
              <Plus className="h-4 w-4" />
              Add model
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Traceability / COI</CardTitle>
            <CardDescription>Chain of identity transitions across labs.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {formState.coiTimeline.map((entry, index) => (
              <div key={`${entry.stage}-${index}`} className="rounded-2xl border border-line bg-surface-2 p-4">
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">{entry.stage}</p>
                <div className="mt-3 grid gap-4 md:grid-cols-2">
                  <div>
                    <Label>Label</Label>
                    <Input
                      value={entry.label}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          coiTimeline: prev.coiTimeline.map((node, idx) =>
                            idx === index ? { ...node, label: event.target.value } : node
                          ),
                        }))
                      }
                    />
                  </div>
                  <div>
                    <Label>Batch ID</Label>
                    <Input
                      value={entry.batchId}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          coiTimeline: prev.coiTimeline.map((node, idx) =>
                            idx === index ? { ...node, batchId: event.target.value } : node
                          ),
                        }))
                      }
                    />
                  </div>
                  <div>
                    <Label>Site</Label>
                    <Input
                      value={entry.site}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          coiTimeline: prev.coiTimeline.map((node, idx) =>
                            idx === index ? { ...node, site: event.target.value } : node
                          ),
                        }))
                      }
                    />
                  </div>
                  <div>
                    <Label>Date</Label>
                    <Input
                      type="date"
                      value={entry.date}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          coiTimeline: prev.coiTimeline.map((node, idx) =>
                            idx === index ? { ...node, date: event.target.value } : node
                          ),
                        }))
                      }
                    />
                  </div>
                  <div className="md:col-span-2">
                    <Label>Material</Label>
                    <Input
                      value={entry.material}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          coiTimeline: prev.coiTimeline.map((node, idx) =>
                            idx === index ? { ...node, material: event.target.value } : node
                          ),
                        }))
                      }
                    />
                  </div>
                  <div className="md:col-span-2">
                    <Label>Lab trail (one per line)</Label>
                    <Textarea
                      value={entry.labTrail.join('\n')}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          coiTimeline: prev.coiTimeline.map((node, idx) =>
                            idx === index ? { ...node, labTrail: splitLines(event.target.value) } : node
                          ),
                        }))
                      }
                    />
                  </div>
                  <div className="md:col-span-2">
                    <Label>Animal groups (one per line)</Label>
                    <Textarea
                      value={entry.animalGroups.join('\n')}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          coiTimeline: prev.coiTimeline.map((node, idx) =>
                            idx === index ? { ...node, animalGroups: splitLines(event.target.value) } : node
                          ),
                        }))
                      }
                    />
                  </div>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Endpoint matrix</CardTitle>
            <CardDescription>Plan endpoints and track status.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {formState.endpointMatrix.map((entry, index) => (
              <div key={entry.id} className="rounded-2xl border border-line bg-surface-2 p-4">
                <div className="mb-3 flex items-center justify-between">
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Endpoint {index + 1}</p>
                  {formState.endpointMatrix.length > 1 ? (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() =>
                        setFormState((prev) => ({
                          ...prev,
                          endpointMatrix: prev.endpointMatrix.filter((_, idx) => idx !== index),
                        }))
                      }
                    >
                      <Trash2 className="h-4 w-4" />
                      Remove
                    </Button>
                  ) : null}
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                  <div>
                    <Label>Name</Label>
                    <Input
                      value={entry.name}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          endpointMatrix: prev.endpointMatrix.map((item, idx) =>
                            idx === index ? { ...item, name: event.target.value } : item
                          ),
                        }))
                      }
                    />
                  </div>
                  <div>
                    <Label>Category</Label>
                    <Input
                      value={entry.category}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          endpointMatrix: prev.endpointMatrix.map((item, idx) =>
                            idx === index ? { ...item, category: event.target.value } : item
                          ),
                        }))
                      }
                    />
                  </div>
                  <div>
                    <Label>Timepoint</Label>
                    <Input
                      value={entry.timepoint}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          endpointMatrix: prev.endpointMatrix.map((item, idx) =>
                            idx === index ? { ...item, timepoint: event.target.value } : item
                          ),
                        }))
                      }
                    />
                  </div>
                  <div>
                    <Label>Status</Label>
                    <select
                      className="h-10 w-full rounded-xl border border-line bg-white px-3 text-sm text-slate-600"
                      value={entry.status}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          endpointMatrix: prev.endpointMatrix.map((item, idx) =>
                            idx === index
                              ? { ...item, status: event.target.value as EndpointMatrixEntry['status'] }
                              : item
                          ),
                        }))
                      }
                    >
                      {endpointStatuses.map((option) => (
                        <option key={option} value={option}>
                          {option}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <Label>Owner</Label>
                    <Input
                      value={entry.owner}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          endpointMatrix: prev.endpointMatrix.map((item, idx) =>
                            idx === index ? { ...item, owner: event.target.value } : item
                          ),
                        }))
                      }
                    />
                  </div>
                  <div>
                    <Label>Summary</Label>
                    <Input
                      value={entry.summary}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          endpointMatrix: prev.endpointMatrix.map((item, idx) =>
                            idx === index ? { ...item, summary: event.target.value } : item
                          ),
                        }))
                      }
                    />
                  </div>
                  <div className="md:col-span-2">
                    <Label>Details (one per line)</Label>
                    <Textarea
                      value={entry.details.join('\n')}
                      onChange={(event) =>
                        setFormState((prev) => ({
                          ...prev,
                          endpointMatrix: prev.endpointMatrix.map((item, idx) =>
                            idx === index ? { ...item, details: splitLines(event.target.value) } : item
                          ),
                        }))
                      }
                    />
                  </div>
                </div>
              </div>
            ))}
            <Button
              type="button"
              variant="outline"
              onClick={() =>
                setFormState((prev) => ({ ...prev, endpointMatrix: [...prev.endpointMatrix, createEndpointEntry()] }))
              }
            >
              <Plus className="h-4 w-4" />
              Add endpoint
            </Button>
          </CardContent>
        </Card>

        <div className="flex flex-wrap items-center gap-3">
          <Button type="submit" disabled={createMutation.isPending || updateMutation.isPending}>
            {isEditMode ? 'Save changes' : 'Create study'}
          </Button>
          <Button type="button" variant="outline" onClick={() => navigate('/atmp')}>
            Cancel
          </Button>
        </div>
      </form>
    </div>
  )
}
