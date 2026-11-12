import type {
  AdministrationRoute,
  AtmpCategory,
  CoiStage,
  EndpointStatus,
  RegulatoryStatus,
} from './atmp.enums.ts'

export interface Dosage {
  value: number
  unit: string
  frequency?: string
}

export interface ProductCharacterization {
  modality: string
  vectorType: string
  vectorCopyNumber: number
  viabilityPercent: number
  donorType: string
  scaffoldMaterials: string[]
  criticalQualityAttributes: string[]
}

export interface StudyModel {
  species: string
  strain: string
  modelType: string
  groupSize: number
  notes?: string
}

export interface CoiNode {
  stage: CoiStage
  label: string
  batchId: string
  site: string
  date: string
  material: string
  labTrail: string[]
  animalGroups: string[]
}

export interface EndpointMatrixEntry {
  id: string
  name: string
  category: string
  timepoint: string
  status: EndpointStatus
  owner: string
  summary: string
  details: string[]
}

export interface AtmpStudy {
  investigationID: string
  chainOfIdentityID: string
  title: string
  atmpCategory: AtmpCategory
  regulatoryStatus: RegulatoryStatus
  administrationRoute: AdministrationRoute
  dosage: Dosage
  species: string
  modelSummary: string
  productCharacterization: ProductCharacterization
  studyModels: StudyModel[]
  coiTimeline: CoiNode[]
  endpointMatrix: EndpointMatrixEntry[]
  updatedAt: string
  createdAt: string
}

export interface AtmpStudyJsonLd {
  '@context': string
  '@type': string
  identifier: string
  name: string
  status: RegulatoryStatus
  category: AtmpCategory
  subjectOf: {
    '@type': string
    identifier: string
    name: string
  }
  dosageForm: string
  additionalProperty: Array<{
    '@type': string
    name: string
    value: string
  }>
}

export const toAtmpJsonLd = (study: AtmpStudy): AtmpStudyJsonLd => {
  return {
    '@context': 'https://schema.org',
    '@type': 'MedicalStudy',
    identifier: study.investigationID,
    name: study.title,
    status: study.regulatoryStatus,
    category: study.atmpCategory,
    subjectOf: {
      '@type': 'CreativeWork',
      identifier: study.chainOfIdentityID,
      name: 'Chain of Identity',
    },
    dosageForm: `${study.administrationRoute} ${study.dosage.value}${study.dosage.unit}`,
    additionalProperty: [
      {
        '@type': 'PropertyValue',
        name: 'Species',
        value: study.species,
      },
      {
        '@type': 'PropertyValue',
        name: 'Model summary',
        value: study.modelSummary,
      },
      {
        '@type': 'PropertyValue',
        name: 'Vector copy number',
        value: `${study.productCharacterization.vectorCopyNumber}`,
      },
      {
        '@type': 'PropertyValue',
        name: 'Viability',
        value: `${study.productCharacterization.viabilityPercent}%`,
      },
    ],
  }
}
