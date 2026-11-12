import type { InvestigationActivityItem } from '@/features/core/investigations/investigation.types.ts'

const hoursAgo = (hours: number) => new Date(Date.now() - hours * 3600000).toISOString()

export const investigationActivity: InvestigationActivityItem[] = [
  {
    id: 'PA-1001',
    investigationId: 'PRJ-0007',
    investigationName: 'Immuno Challenge Cohort',
    at: hoursAgo(2),
    title: 'Weight delta flagged',
    detail: 'Quartz +1.6 g in last 48h.',
    actor: 'Auto monitor',
  },
  {
    id: 'PA-1002',
    investigationId: 'PRJ-0012',
    investigationName: 'Pathogen Neutralization',
    at: hoursAgo(3),
    title: 'PCR panel completed',
    detail: 'TNFa deltaCt elevated; review suggested.',
    actor: 'Assay pipeline',
  },
  {
    id: 'PA-1003',
    investigationId: 'PRJ-0007',
    investigationName: 'Immuno Challenge Cohort',
    at: hoursAgo(6),
    title: 'Treatment logged',
    detail: 'Adjuvant A 5 mg/kg delivered.',
    actor: 'Avery Cole',
  },
  {
    id: 'PA-1004',
    investigationId: 'PRJ-0012',
    investigationName: 'Pathogen Neutralization',
    at: hoursAgo(9),
    title: 'Blood sample routed',
    detail: 'Sample BS-PRJ-0012-1103 queued for flow cytometry.',
    actor: 'Noah Park',
  },
]
