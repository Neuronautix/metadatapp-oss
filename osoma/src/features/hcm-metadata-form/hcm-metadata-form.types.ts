export type CanonicalFormSectionRef = {
  id: string
  title: string
  description: string | null
  orderIndex: number
}

export type CanonicalFormAllowedValue = {
  value: string
  code?: string
  ontologyTerm?: string
}

export type CanonicalFormOntologyMapping = {
  iri?: string
  label?: string
  source?: string
}

export type CanonicalFormField = {
  id: string
  section: CanonicalFormSectionRef | null
  localKey: string
  label: string
  description: string | null
  datatype: string | null
  required: boolean
  unitConstraint: string | null
  allowedValues: CanonicalFormAllowedValue[]
  ontologyMappings: CanonicalFormOntologyMapping[]
  jsonldPath: string | null
  sourceFieldReference: string | null
  orderIndex: number
}

export type CanonicalForm = {
  '@context'?: string
  '@id'?: string
  '@type'?: string
  id: string
  title: string
  description: string | null
  domain: string
  version: string
  status: string
  sourceLinks: Record<string, unknown> | null
  createdAt: string
  updatedAt: string
  sections: CanonicalFormSectionRef[]
  fields: CanonicalFormField[]
}

/**
 * A section paired with its ordered fields, ready for rendering.
 */
export type CanonicalFormSectionGroup = {
  section: CanonicalFormSectionRef
  fields: CanonicalFormField[]
  /** ISA hierarchy annotation, when resolvable from sourceLinks.isaMapping. */
  isaAnnotation: string | null
}
