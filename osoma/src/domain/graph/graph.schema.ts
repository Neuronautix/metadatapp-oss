import { GraphSchema } from './graph.types'

export const METADATAPP_GRAPH_SCHEMA: GraphSchema = {
  entities: [
    {
      name: 'Subject',
      description: 'An individual animal or donor in an investigation.',
      properties: [
        { name: 'id', type: 'string', required: true },
        { name: 'label', type: 'string', required: true },
        { name: 'startAt', type: 'date', required: false },
        { name: 'sex', type: 'string', required: false },
        { name: 'strain', type: 'string', required: false },
        { name: 'species', type: 'string', required: false },
        { name: 'birthAt', type: 'date', required: false },
        { name: 'locatedIn', type: 'relation', required: false, targetEntity: 'Cage' },
      ],
    },
    {
      name: 'Sample',
      description: 'A biological specimen collected from a subject.',
      properties: [
        { name: 'id', type: 'string', required: true },
        { name: 'label', type: 'string', required: true },
        { name: 'type', type: 'string', required: true },
        { name: 'collectedAt', type: 'date', required: true },
        { name: 'derivedFrom', type: 'relation', required: false, targetEntity: 'Subject' },
      ],
    },
    {
      name: 'Cage',
      description: 'A housing unit for subjects.',
      properties: [
        { name: 'id', type: 'string', required: true },
        { name: 'label', type: 'string', required: true },
        { name: 'room', type: 'string', required: false },
        { name: 'rack', type: 'string', required: false },
      ],
    },
  ],
}
