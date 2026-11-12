export type DataImportReport = {
  rowCount: number
  columnCount: number
  headers: string[]
  detectedTypes: Record<string, string>
  missingValueStats: Record<string, number>
  previewRows: Record<string, any>[]
}

export type MappingDefinition = {
  sourceColumn: string
  targetEntity: string
  targetProperty: string
  transformFunction?: string
}

export type ImportProfile = {
  id: string
  name: string
  fileSignature: string
  mappingRules: MappingDefinition[]
  schemaExtensions: SchemaExtension[]
  validationRules: Record<string, any>
  createdAt: string
}

export type SchemaExtension = {
  entity: string
  property: string
  type: 'string' | 'number' | 'boolean' | 'date' | 'float'
}

export type ValidationIssue = {
  level: 'error' | 'warning'
  column?: string
  row?: number
  message: string
}

export type ValidationReport = {
  isValid: boolean
  issues: ValidationIssue[]
  counts: {
    errors: number
    warnings: number
  }
}

export type CurationWorkflowStep = 'import' | 'mapping' | 'validation' | 'graph' | 'ingestion'

export type CurationState = {
  step: CurationWorkflowStep
  file?: File
  report?: DataImportReport
  mapping: MappingDefinition[]
  extensions: SchemaExtension[]
  validation?: ValidationReport
  profileId?: string
}
