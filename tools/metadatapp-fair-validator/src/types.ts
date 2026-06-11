/** Compact JSON-LD shape used by Metadatapp exports. */
export type JsonLdScalar = string | number | boolean | null

export type JsonLdValue =
  | JsonLdScalar
  | JsonLdValue[]
  | { [k: string]: JsonLdValue }

export interface JsonLdContextEntry {
  [term: string]: string | { '@id'?: string; '@type'?: string }
}

export interface JsonLdDocument {
  '@context'?: string | JsonLdContextEntry | Array<string | JsonLdContextEntry>
  '@graph'?: JsonLdNode[]
  [k: string]: JsonLdValue | JsonLdNode[] | undefined
}

export interface JsonLdNode {
  '@id'?: string
  '@type'?: string | string[]
  [k: string]: JsonLdValue | undefined
}

export interface ValidationIssue {
  level: 'error' | 'warning' | 'info'
  code: string
  message: string
  path?: string
}

export interface ValidationSummary {
  declaredVocabs: string[]
  usedVocabs: string[]
  unusedVocabs: string[]
  /** Ratio of declared vocabs that appear in the payload. 0..1. */
  coverageRatio: number
}

export interface ValidationResult {
  valid: boolean
  issues: ValidationIssue[]
  summary: ValidationSummary
}

export interface OntologyDepthExpectations {
  species?: boolean
  units?: boolean
  procedureTyping?: boolean
  genotype?: boolean
}

export interface OntologyVerdict {
  check: string
  passed: boolean
  evidence?: string
  details?: string
}

export interface OntologyDepthResult {
  verdicts: OntologyVerdict[]
  passed: number
  failed: number
}
