import { MappingDefinition } from '../curation/curation.types'
import { KnowledgeGraph, GraphNode, GraphRelationship } from './graph.types'
import { METADATAPP_GRAPH_SCHEMA } from './graph.schema'

export const generateKnowledgeGraph = (
  data: Record<string, any>[],
  mappings: MappingDefinition[]
): KnowledgeGraph => {
  const nodes: GraphNode[] = []
  const relationships: GraphRelationship[] = []
  const nodeRegistry = new Map<string, string>() // entityType:uniqueId -> nodeArrayId

  data.forEach((row, rowIndex) => {
    // 1. Group mappings by entity to create nodes
    const entitiesAtRow = Array.from(new Set(mappings.map(m => m.targetEntity)))

    entitiesAtRow.forEach(entityName => {
      const entityMappings = mappings.filter(m => m.targetEntity === entityName)
      
      // Attempt to find a unique ID for this entity in this row
      // If not mapped, we might need a composite key or just skip if no properties mapped
      const idMapping = entityMappings.find(m => m.targetProperty === 'id')
      const entityId = idMapping ? String(row[idMapping.sourceColumn]) : `node-${entityName}-${rowIndex}`

      const registryKey = `${entityName}:${entityId}`
      
      if (!nodeRegistry.has(registryKey)) {
        const node: GraphNode = {
          id: entityId,
          type: entityName,
          properties: {}
        }

        entityMappings.forEach(mapping => {
          node.properties[mapping.targetProperty] = row[mapping.sourceColumn]
        })

        nodes.push(node)
        nodeRegistry.set(registryKey, entityId)
      }
    })

    // 2. Generate implicit relationships from schema
    entitiesAtRow.forEach(entityName => {
        const schemaEntity = METADATAPP_GRAPH_SCHEMA.entities.find(e => e.name === entityName)
        schemaEntity?.properties.filter(p => p.type === 'relation').forEach(relProp => {
            const relMapping = mappings.find(m => m.targetEntity === entityName && m.targetProperty === relProp.name)
            if (relMapping && row[relMapping.sourceColumn]) {
                const targetId = String(row[relMapping.sourceColumn])
                const sourceIdMapping = mappings.find(m => m.targetEntity === entityName && m.targetProperty === 'id')
                const sourceId = sourceIdMapping ? String(row[sourceIdMapping.sourceColumn]) : `node-${entityName}-${rowIndex}`

                relationships.push({
                    id: `rel-${sourceId}-${targetId}`,
                    source: sourceId,
                    target: targetId,
                    type: relProp.name
                })
            }
        })
    })
  })

  return { nodes, relationships }
}
