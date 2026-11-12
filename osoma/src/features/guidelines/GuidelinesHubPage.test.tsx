import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { GuidelinesHubPage } from './GuidelinesHubPage.tsx'
import * as guidelinesApi from './guidelines.api.ts'
import * as investigationApi from '@/features/core/investigations/investigation.api.ts'
import * as studyApi from '@/features/core/studies/study.api.ts'
import type { CompletionReport, GuidelineTemplateSummary } from './guidelines.types.ts'

vi.mock('./guidelines.api.ts', async (importOriginal) => ({
  ...(await importOriginal<typeof import('./guidelines.api.ts')>()),
  getGuidelineTemplates: vi.fn(),
  getCompletion: vi.fn(),
}))

vi.mock('@/features/core/investigations/investigation.api.ts', () => ({
  getInvestigations: vi.fn(),
}))

vi.mock('@/features/core/studies/study.api.ts', () => ({
  getStudies: vi.fn(),
}))

const templates: GuidelineTemplateSummary[] = [
  { id: 'arrive-v2', name: 'ARRIVE 2.0', version: '2.0', description: 'Reporting standard.', conformsTo: [], requiredMetadataCount: 5, columnCount: 0 },
  { id: 'prepare-v1', name: 'PREPARE', version: '1.0', description: 'Planning checklist.', conformsTo: [], requiredMetadataCount: 4, columnCount: 0 },
]

const fullCompletion: CompletionReport = {
  totals: { satisfiedDirect: 5, satisfiedViaCrosswalk: 0, partial: 0, missing: 0 },
  bySeverity: {},
  bySection: [],
  reportReview: { status: 'not_reviewed', reviewedBy: null, reviewedAt: null },
}

const renderPage = (ui: ReactNode) => {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>
    </MemoryRouter>,
  )
}

describe('GuidelinesHubPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(investigationApi.getInvestigations).mockResolvedValue({
      data: [{ id: 'inv-1', name: 'Cohort A' }],
      total: 1,
    } as never)
    vi.mocked(studyApi.getStudies).mockResolvedValue({ data: [], total: 0 } as never)
    vi.mocked(guidelinesApi.getGuidelineTemplates).mockResolvedValue(templates)
    vi.mocked(guidelinesApi.getCompletion).mockResolvedValue(fullCompletion)
  })

  it('renders one card per guideline template', async () => {
    renderPage(<GuidelinesHubPage />)
    expect(await screen.findByTestId('guideline-card-arrive-v2')).toHaveTextContent('ARRIVE 2.0')
    expect(screen.getByTestId('guideline-card-prepare-v1')).toHaveTextContent('PREPARE')
  })

  it('links each card to its dedicated page', async () => {
    renderPage(<GuidelinesHubPage />)
    const card = await screen.findByTestId('guideline-card-arrive-v2')
    expect(card).toHaveAttribute('href', '/guidelines/arrive-v2')
  })

  it('shows live completion once a target is picked, and carries it into the link', async () => {
    renderPage(<GuidelinesHubPage />)
    await screen.findByTestId('guideline-card-arrive-v2')

    fireEvent.change(screen.getByLabelText('Investigation'), { target: { value: 'inv-1' } })

    const card = await screen.findByTestId('guideline-card-arrive-v2')
    await waitFor(() => expect(card).toHaveTextContent('100% complete'))
    expect(card).toHaveAttribute('href', '/guidelines/arrive-v2?resType=investigations&resId=inv-1')
  })
})
