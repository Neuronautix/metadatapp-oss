import { z } from 'zod'
import {
  administrationRoutes,
  atmpCategories,
  coiStages,
  endpointStatuses,
  regulatoryStatuses,
} from './atmp.enums.ts'

export const dosageSchema = z.object({
  value: z.number().positive('Dosage must be greater than 0.'),
  unit: z.string().min(1, 'Dosage unit required.'),
  frequency: z.string().optional(),
})

export const productCharacterizationSchema = z.object({
  modality: z.string().min(1, 'Modality required.'),
  vectorType: z.string().min(1, 'Vector type required.'),
  vectorCopyNumber: z.number().min(0, 'Vector copy number must be positive.'),
  viabilityPercent: z.number().min(0).max(100, 'Viability must be 0-100.'),
  donorType: z.string().min(1, 'Donor type required.'),
  scaffoldMaterials: z.array(z.string().min(1)).min(1, 'Add at least one scaffold material.'),
  criticalQualityAttributes: z
    .array(z.string().min(1))
    .min(1, 'Add at least one critical quality attribute.'),
})

export const studyModelSchema = z.object({
  species: z.string().min(1, 'Species required.'),
  strain: z.string().min(1, 'Strain required.'),
  modelType: z.string().min(1, 'Model type required.'),
  groupSize: z.number().min(1, 'Group size must be at least 1.'),
  notes: z.string().optional(),
})

export const coiNodeSchema = z.object({
  stage: z.enum(coiStages),
  label: z.string().min(1, 'COI label required.'),
  batchId: z.string().min(1, 'COI batch required.'),
  site: z.string().min(1, 'Site required.'),
  date: z.string().min(1, 'Date required.'),
  material: z.string().min(1, 'Material required.'),
  labTrail: z.array(z.string().min(1)).min(1, 'Add at least one lab trail entry.'),
  animalGroups: z.array(z.string().min(1)).min(1, 'Add at least one animal group.'),
})

export const endpointMatrixSchema = z.object({
  id: z.string().min(1),
  name: z.string().min(1, 'Endpoint name required.'),
  category: z.string().min(1, 'Endpoint category required.'),
  timepoint: z.string().min(1, 'Timepoint required.'),
  status: z.enum(endpointStatuses),
  owner: z.string().min(1, 'Owner required.'),
  summary: z.string().min(1, 'Summary required.'),
  details: z.array(z.string().min(1)).min(1, 'Add at least one detail.'),
})

export const atmpStudyInputSchema = z.object({
  investigationID: z.string().min(1, 'Investigation ID required.'),
  chainOfIdentityID: z.string().min(1, 'COI required.'),
  title: z.string().min(1, 'Study title required.'),
  atmpCategory: z.enum(atmpCategories),
  regulatoryStatus: z.enum(regulatoryStatuses),
  administrationRoute: z.enum(administrationRoutes),
  dosage: dosageSchema,
  species: z.string().min(1, 'Species required.'),
  modelSummary: z.string().min(1, 'Model summary required.'),
  productCharacterization: productCharacterizationSchema,
  studyModels: z.array(studyModelSchema).min(1, 'Add at least one study model.'),
  coiTimeline: z.array(coiNodeSchema).min(1, 'Add at least one COI entry.'),
  endpointMatrix: z.array(endpointMatrixSchema).min(1, 'Add at least one endpoint.'),
  createdAt: z.string().optional(),
  updatedAt: z.string().optional(),
})

export type AtmpStudyInput = z.infer<typeof atmpStudyInputSchema>
