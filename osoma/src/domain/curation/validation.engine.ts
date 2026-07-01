import { METADATAPP_GRAPH_SCHEMA } from '../graph/graph.schema'
import { MappingDefinition, ValidationIssue, ValidationReport } from './curation.types'

export const validateImportData = (
  data: Record<string, any>[],
  mappings: MappingDefinition[]
): ValidationReport => {
  const issues: ValidationIssue[] = []

  // 1. Schema Check: Are required properties mapped?
  METADATAPP_GRAPH_SCHEMA.entities.forEach(entity => {
    const entityMappings = mappings.filter(m => m.targetEntity === entity.name)
    if (entityMappings.length > 0) {
      entity.properties.filter(p => p.required && p.name !== 'id').forEach(prop => {
        const isMapped = entityMappings.some(m => m.targetProperty === prop.name)
        if (!isMapped) {
          issues.push({
            level: 'error',
            message: `Required property ${entity.name}.${prop.name} is not mapped.`
          })
        }
      })
    }
  })

  // 2. Data Integrity: Process rows
  data.forEach((row, rowIndex) => {
    mappings.forEach(mapping => {
      const value = row[mapping.sourceColumn]
      const entity = METADATAPP_GRAPH_SCHEMA.entities.find(e => e.name === mapping.targetEntity)
      const prop = entity?.properties.find(p => p.name === mapping.targetProperty)

      // Empty Check for required
      if (prop?.required && (value === undefined || value === null || value === '')) {
         issues.push({
           level: 'error',
           column: mapping.sourceColumn,
           row: rowIndex + 1,
           message: `Required value missing in row ${rowIndex + 1} for ${mapping.targetProperty}`
         })
      }

      // Type Check
      if (prop && value) {
        if (prop.type === 'number' && isNaN(Number(value))) {
            issues.push({
                level: 'error',
                column: mapping.sourceColumn,
                row: rowIndex + 1,
                message: `Type mismatch in row ${rowIndex + 1}: expected number, got "${value}"`
            })
        }
        if (prop.type === 'date' && isNaN(Date.parse(value))) {
            issues.push({
                level: 'warning',
                column: mapping.sourceColumn,
                row: rowIndex + 1,
                message: `Potential invalid date in row ${rowIndex + 1}: "${value}"`
            })
        }
      }
    })
  })

  return {
    isValid: issues.filter(i => i.level === 'error').length === 0,
    issues: issues.slice(0, 100), // Cap issues for performance
    counts: {
      errors: issues.filter(i => i.level === 'error').length,
      warnings: issues.filter(i => i.level === 'warning').length
    }
  }
}
