import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ReferenceCompareView } from './ReferenceComparePage.tsx'
import type { ReferenceResult, StandardizationResult } from './reference-hub.api.ts'

const ITEMS: ReferenceResult[] = [
  { id: 'jax_phenome:measure:1', source: 'jax_phenome', sourceName: 'JAX Phenome (MPD)', type: 'measure', title: 'Blood glucose' },
  { id: 'impc:measure:2', source: 'impc', sourceName: 'IMPReSS (IMPC)', type: 'measure', title: 'Blood glucose level' },
]

const STANDARDIZATION: StandardizationResult = {
  aiAvailable: true,
  clusters: [
    {
      clusterId: 'glucose',
      canonicalTerm: { iri: 'http://purl.obolibrary.org/obo/VT_0000188', label: 'blood glucose level', ontology: 'vt', confidence: 0.93 },
      memberRefIds: ['jax_phenome:measure:1', 'impc:measure:2'],
      evidence: ['Both describe glucose measured in blood.'],
      proposedRecord: { title: 'Blood glucose level', type: 'measure' },
      confidence: 0.93,
      harmonizedFields: [
        { field: 'unit', value: 'mg/dL', conflict: false, sourceValues: [{ source: 'JAX', value: 'mg/dL' }] },
        {
          field: 'method',
          value: 'enzymatic assay',
          conflict: true,
          sourceValues: [{ source: 'JAX', value: 'enzymatic assay' }, { source: 'IMPC', value: 'glucometer' }],
          rationale: 'Enzymatic assay is the more standard lab method.',
        },
      ],
    },
  ],
  warnings: [],
  provenance: [],
  usage: {},
}

function renderView(overrides: Partial<React.ComponentProps<typeof ReferenceCompareView>> = {}) {
  const props = {
    items: ITEMS,
    onBack: vi.fn(),
    onImport: vi.fn(),
    importing: false,
    standardizeEnabled: true,
    onStandardize: vi.fn(),
    standardizing: false,
    standardization: null as StandardizationResult | null,
    onImportStandardized: vi.fn(),
    ...overrides,
  }
  render(<ReferenceCompareView {...props} />)
  return props
}

describe('ReferenceCompareView', () => {
  it('shows "Import all" and triggers AI standardization before any result is in', async () => {
    const props = renderView()

    expect(screen.getByRole('button', { name: /Import all 2/ })).toBeInTheDocument()
    expect(screen.queryByTestId('standardization-panel')).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Standardize with AI/ }))
    expect(props.onStandardize).toHaveBeenCalledOnce()
  })

  it('hides the AI action when the feature flag is off', () => {
    renderView({ standardizeEnabled: false })
    expect(screen.queryByRole('button', { name: /Standardize with AI/ })).not.toBeInTheDocument()
  })

  it('renders the canonical term, linked members and confidence once standardized', () => {
    renderView({ standardization: STANDARDIZATION })

    const panel = screen.getByTestId('standardization-panel')
    expect(within(panel).getByText('blood glucose level')).toBeInTheDocument()
    expect(within(panel).getByText(/Blood glucose ↔ Blood glucose level/)).toBeInTheDocument()
    expect(within(panel).getByText(/93% confidence/)).toBeInTheDocument()

    const termLink = within(panel).getByRole('link', { name: /blood glucose level/ })
    expect(termLink).toHaveAttribute('href', 'http://purl.obolibrary.org/obo/VT_0000188')
  })

  it('renders the per-field harmonized record, flagging conflicts and rationale', () => {
    renderView({ standardization: STANDARDIZATION })

    const harmonized = screen.getByTestId('harmonized-fields')
    // Agreeing field: chosen value shown, no conflict marker on it.
    expect(within(harmonized).getByText('mg/dL')).toBeInTheDocument()
    // Conflicting field: chosen value + a conflict flag + the per-source values + rationale.
    expect(within(harmonized).getByText('enzymatic assay')).toBeInTheDocument()
    expect(within(harmonized).getByText('conflict')).toBeInTheDocument()
    expect(within(harmonized).getByText(/JAX: enzymatic assay.*IMPC: glucometer/)).toBeInTheDocument()
    expect(within(harmonized).getByText(/more standard lab method/)).toBeInTheDocument()
  })

  it('imports the standardized records via the dedicated action', async () => {
    const props = renderView({ standardization: STANDARDIZATION })

    expect(screen.queryByRole('button', { name: /Import all/ })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: /Import standardized/ }))
    expect(props.onImportStandardized).toHaveBeenCalledOnce()
  })
})
