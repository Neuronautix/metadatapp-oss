import type { Organization } from '@/domain/resources.ts'

export const organizations: Organization[] = [
  {
    id: 'ORG-2001',
    name: 'Helix Institute',
    type: 'university',
    location: 'Boston, MA',
    labs: 4,
    activeInvestigations: 3,
    createdAt: '2018-04-10T09:00:00Z',
    updatedAt: '2024-11-02T09:00:00Z',
  },
  {
    id: 'ORG-2002',
    name: 'Atlas BioLab',
    type: 'biotech',
    location: 'San Diego, CA',
    labs: 3,
    activeInvestigations: 4,
    createdAt: '2019-06-22T09:00:00Z',
    updatedAt: '2024-10-30T16:45:00Z',
  },
  {
    id: 'ORG-2003',
    name: 'Northern Genomics',
    type: 'hospital',
    location: 'Toronto, ON',
    labs: 5,
    activeInvestigations: 5,
    createdAt: '2017-01-08T09:00:00Z',
    updatedAt: '2024-11-12T09:30:00Z',
  },
  {
    id: 'ORG-2004',
    name: 'Open Variant Consortium',
    type: 'consortium',
    location: 'Distributed',
    labs: 9,
    activeInvestigations: 2,
    createdAt: '2020-09-02T09:00:00Z',
    updatedAt: '2024-10-19T17:20:00Z',
  },
]
