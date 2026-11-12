export type GraphNodeId = string

export type GraphNode = {
  id: GraphNodeId
  type: string // Corresponding to Metadatapp Entities (Subject, Sample, etc.)
  properties: Record<string, any>
}

export type GraphRelationship = {
  id: string
  source: GraphNodeId
  target: GraphNodeId
  type: string // e.g., 'locatedIn', 'derivedFrom'
  properties?: Record<string, any>
}

export type KnowledgeGraph = {
  nodes: GraphNode[]
  relationships: GraphRelationship[]
}

export type GraphSchemaProperty = {
  name: string
  type: 'string' | 'number' | 'boolean' | 'date' | 'relation'
  required: boolean
  description?: string
  targetEntity?: string // For relations
}

export type GraphSchemaEntity = {
  name: string
  properties: GraphSchemaProperty[]
  description?: string
}

export type GraphSchema = {
  entities: GraphSchemaEntity[]
}
