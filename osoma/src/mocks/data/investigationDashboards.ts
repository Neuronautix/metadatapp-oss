import type { InvestigationDashboard } from '@/features/core/investigations/investigation.types.ts'

const daysAgo = (days: number, hours = 9) =>
  new Date(Date.now() - days * 86400000 + hours * 3600000).toISOString()

export const investigationDashboards: Record<string, InvestigationDashboard> = {
  'PRJ-0007': {
    investigationId: 'PRJ-0007',
    animalsAtRisk: [
      {
        id: 'ANM-1002',
        tagId: 'TL-5529',
        nickname: 'Quartz',
        status: 'critical',
        lastWeight: 26.8,
        lastWeighedAt: daysAgo(0, 6),
        reason: 'Weight above max threshold',
      },
      {
        id: 'ANM-1004',
        tagId: 'TL-5699',
        nickname: 'Dove',
        status: 'monitor',
        lastWeight: 20.5,
        lastWeighedAt: daysAgo(0, 10),
        reason: 'Quarantine clearance pending',
      },
    ],
    groupWeights: [
      { at: daysAgo(12), average: 20.8, min: 19.6, max: 22.1 },
      { at: daysAgo(10), average: 21.2, min: 20.1, max: 22.9 },
      { at: daysAgo(8), average: 21.5, min: 20.5, max: 24.0 },
      { at: daysAgo(6), average: 21.9, min: 20.7, max: 25.2 },
      { at: daysAgo(4), average: 22.1, min: 20.6, max: 26.1 },
      { at: daysAgo(2), average: 22.3, min: 20.5, max: 26.8 },
    ],
    markerTrends: [
      { id: 'MK-1', label: 'CD8 expansion', value: 35.2, delta: 4.1, status: 'up', history: [28, 30, 32, 34, 35] },
      { id: 'MK-2', label: 'IL6 deltaCt', value: 0.8, delta: -0.3, status: 'down', history: [1.4, 1.2, 1.0, 0.9, 0.8] },
      { id: 'MK-3', label: 'Neutralizing IgG', value: 1820, delta: 120, status: 'up', history: [1500, 1600, 1680, 1750, 1820] },
    ],
    recentActions: [
      {
        id: 'ACT-1001',
        at: daysAgo(0, 4),
        title: 'Weight alert triggered',
        detail: 'Quartz exceeded max weight threshold (26.8 g).',
        actor: 'Auto monitor',
      },
      {
        id: 'ACT-1002',
        at: daysAgo(1, 9),
        title: 'Blood sample collected',
        detail: 'BS-PRJ-0007-0142 routed to flow cytometry.',
        actor: 'Noah Park',
      },
      {
        id: 'ACT-1003',
        at: daysAgo(2, 11),
        title: 'Treatment dose administered',
        detail: 'Adjuvant A 5 mg/kg delivered.',
        actor: 'Avery Cole',
      },
    ],
  },
  'PRJ-0012': {
    investigationId: 'PRJ-0012',
    animalsAtRisk: [
      {
        id: 'ANM-2002',
        tagId: 'TL-6194',
        nickname: 'Jet',
        status: 'critical',
        lastWeight: 17.4,
        lastWeighedAt: daysAgo(0, 3),
        reason: 'Rapid weight loss over 5 days',
      },
    ],
    groupWeights: [
      { at: daysAgo(8), average: 19.8, min: 19.2, max: 20.3 },
      { at: daysAgo(6), average: 19.4, min: 19.2, max: 20.0 },
      { at: daysAgo(4), average: 18.9, min: 18.3, max: 19.8 },
      { at: daysAgo(2), average: 18.3, min: 17.4, max: 20.1 },
    ],
    markerTrends: [
      { id: 'MK-4', label: 'TNFa deltaCt', value: 1.9, delta: 0.5, status: 'up', history: [1.2, 1.4, 1.6, 1.8, 1.9] },
      { id: 'MK-5', label: 'IL10 deltaCt', value: -0.6, delta: -0.2, status: 'down', history: [-0.2, -0.3, -0.4, -0.5, -0.6] },
    ],
    recentActions: [
      {
        id: 'ACT-2001',
        at: daysAgo(0, 5),
        title: 'PCR panel completed',
        detail: 'TNFa signal elevated; review suggested.',
        actor: 'Assay pipeline',
      },
      {
        id: 'ACT-2002',
        at: daysAgo(1, 8),
        title: 'Dose escalation logged',
        detail: 'Compound Z increased to 10 mg/kg.',
        actor: 'Samir Haddad',
      },
    ],
  },
}
