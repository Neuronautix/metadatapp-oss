export const atmpCategories = ['GTMP', 'sCTMP', 'TEP', 'Combined'] as const
export type AtmpCategory = (typeof atmpCategories)[number]

export const administrationRoutes = [
  'Intravenous',
  'Intraspinal',
  'Subretinal',
  'Intramuscular',
  'Intrathecal',
  'Intrahepatic',
  'Intracerebral',
  'Subcutaneous',
  'Topical',
] as const
export type AdministrationRoute = (typeof administrationRoutes)[number]

export const regulatoryStatuses = ['Non-GLP', 'GLP-Pivotal', 'Pre-IND'] as const
export type RegulatoryStatus = (typeof regulatoryStatuses)[number]

export const coiStages = ['startingMaterial', 'activeSubstance', 'preclinicalModel'] as const
export type CoiStage = (typeof coiStages)[number]

export const endpointStatuses = ['Pending', 'In Progress', 'Complete', 'Blocked'] as const
export type EndpointStatus = (typeof endpointStatuses)[number]
