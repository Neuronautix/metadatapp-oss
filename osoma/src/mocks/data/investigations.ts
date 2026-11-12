import type { Investigation } from '@/features/core/investigations/investigation.types.ts'
import { investigationOperators } from './investigationOperators.ts'

export const investigations: Investigation[] = [
  {
    id: 'PRJ-0007',
    name: 'Immuno Challenge Cohort',
    description: 'Baseline immune profiling with staged antigen exposure.',
    species: 'Mouse',
    startAt: '2024-10-02',
    endAt: '2025-02-18',
    fairScore: { total: 78, findable: 80, accessible: 74, interoperable: 76, reusable: 82 },
    assignedOperators: [investigationOperators[0], investigationOperators[1], investigationOperators[2]],
    assignedAnimalIds: ['ANM-1001', 'ANM-1002', 'ANM-1003', 'ANM-1004'],
    createdAt: '2024-09-20T08:30:00Z',
    updatedAt: '2024-10-10T13:20:00Z',
    tags: ['immunology', 'baseline', 'cohort-a'],
  },
  {
    id: 'PRJ-0012',
    name: 'Pathogen Neutralization',
    description: 'Dose escalation with longitudinal blood draws.',
    species: 'Mouse',
    startAt: '2024-11-05',
    endAt: '2025-03-10',
    fairScore: { total: 84, findable: 86, accessible: 80, interoperable: 82, reusable: 88 },
    assignedOperators: [investigationOperators[3], investigationOperators[4]],
    assignedAnimalIds: ['ANM-2001', 'ANM-2002'],
    createdAt: '2024-10-05T09:00:00Z',
    updatedAt: '2024-11-02T15:45:00Z',
    tags: ['pathogen', 'dose-escalation'],
  },
]
