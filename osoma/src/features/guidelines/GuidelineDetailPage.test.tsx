import { render, screen, fireEvent, waitFor, within } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { ToastProvider } from '@/components/ui/toast.tsx'
import { GuidelineDetailPage } from './GuidelineDetailPage.tsx'
import * as guidelinesApi from './guidelines.api.ts'
import * as investigationApi from '@/features/core/investigations/investigation.api.ts'
import * as studyApi from '@/features/core/studies/study.api.ts'
import type {
  CompletionReport,
  ConformanceReport,
  GuidelineReportReview,
  GuidelineTemplate,
} from './guidelines.types.ts'

vi.mock('./guidelines.api.ts', async (importOriginal) => ({
  ...(await importOriginal<typeof import('./guidelines.api.ts')>()),
  getGuidelineTemplates: vi.fn(),
  getGuidelineTemplate: vi.fn(),
  getConformance: vi.fn(),
  getCompletion: vi.fn(),
  setGuidelineField: vi.fn(),
  aiDraftGuidelineField: vi.fn(),
  aiDraftAll: vi.fn(),
  getFieldEvidence: vi.fn(),
  reviewGuidelineField: vi.fn(),
  submitGuidelineReviewDecision: vi.fn(),
  getGuidelineReview: vi.fn(),
}))

vi.mock('@/features/core/investigations/investigation.api.ts', () => ({
  getInvestigations: vi.fn(),
}))

vi.mock('@/features/core/studies/study.api.ts', () => ({
  getStudies: vi.fn(),
}))

const templateDetail: GuidelineTemplate = {
  id: 'arrive-v2',
  name: 'ARRIVE 2.0',
  version: '2.0',
  description: 'Reporting standard.',
  conformsTo: [],
  requiredColumns: [],
  requiredMetadata: [
    { id: 'randomisation_method', arriveSection: 'Randomisation', prepareSection: null, eqipdSection: null, guidance: 'How animals were allocated.', prompt: null, crosswalk: [], severity: 'high' },
    { id: 'humane_endpoints', arriveSection: 'Animal care and monitoring', prepareSection: null, eqipdSection: null, guidance: 'Define humane endpoints.', prompt: null, crosswalk: [], severity: 'medium' },
  ],
}

const conformance: ConformanceReport = {
  standard: 'ARRIVE 2.0',
  version: '2.0',
  score: { dimension: 'R', points: 3, maxPoints: 5, satisfiedPct: 60 },
  totals: { satisfied: 3, total: 5 },
  entries: [
    { standard: 'ARRIVE 2.0', section: 'Randomisation', arriveSection: 'Randomisation', prepareSection: null, eqipdSection: null, fieldId: 'randomisation_method', status: 'missing', satisfiedBy: null, severity: 'high', isColumnField: false },
    { standard: 'ARRIVE 2.0', section: 'Animal care and monitoring', arriveSection: 'Animal care and monitoring', prepareSection: null, eqipdSection: null, fieldId: 'humane_endpoints', status: 'satisfied', satisfiedBy: { metadata: 'humane_endpoints', viaCrosswalk: true }, severity: 'medium', isColumnField: false },
  ],
}

const completion: CompletionReport = {
  totals: { satisfiedDirect: 1, satisfiedViaCrosswalk: 1, partial: 0, missing: 2 },
  bySeverity: {
    high: { satisfied: 0, partial: 0, missing: 1 },
    medium: { satisfied: 1, partial: 0, missing: 0 },
  },
  bySection: [
    {
      section: 'Randomisation',
      satisfied: 0,
      partial: 0,
      missing: 1,
      fields: [
        { fieldId: 'randomisation_method', status: 'missing', severity: 'high', value: null, hasValue: false, viaCrosswalk: false, satisfyingSibling: null, prompt: null, guidance: 'How animals were allocated.', reviewStatus: 'draft', reviewedBy: null, reviewedAt: null },
      ],
    },
    {
      section: 'Animal care and monitoring',
      satisfied: 1,
      partial: 0,
      missing: 0,
      fields: [
        { fieldId: 'humane_endpoints', status: 'satisfied', severity: 'medium', value: 'Defined endpoints', hasValue: true, viaCrosswalk: true, satisfyingSibling: 'humane_endpoints', prompt: null, guidance: 'Define humane endpoints.', reviewStatus: 'draft', reviewedBy: null, reviewedAt: null },
      ],
    },
  ],
  reportReview: { status: 'not_reviewed', reviewedBy: null, reviewedAt: null },
}

const notReviewed: GuidelineReportReview = { status: 'not_reviewed', reviewedBy: null, reviewedAt: null, note: null, history: [] }

const renderPage = (ui: ReactNode) => {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <MemoryRouter initialEntries={['/guidelines/arrive-v2']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <QueryClientProvider client={queryClient}>
        <ToastProvider>
          <Routes>
            <Route path="/guidelines/:templateId" element={ui} />
          </Routes>
        </ToastProvider>
      </QueryClientProvider>
    </MemoryRouter>,
  )
}

async function selectTarget() {
  await screen.findByText('ARRIVE 2.0')
  fireEvent.change(screen.getByLabelText('Investigation'), { target: { value: 'inv-1' } })
}

describe('GuidelineDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(investigationApi.getInvestigations).mockResolvedValue({
      data: [{ id: 'inv-1', name: 'Cohort A' }],
      total: 1,
    } as never)
    vi.mocked(studyApi.getStudies).mockResolvedValue({ data: [], total: 0 } as never)
    vi.mocked(guidelinesApi.getGuidelineTemplate).mockResolvedValue(templateDetail)
    vi.mocked(guidelinesApi.getConformance).mockResolvedValue(conformance)
    vi.mocked(guidelinesApi.getCompletion).mockResolvedValue(completion)
    vi.mocked(guidelinesApi.getGuidelineReview).mockResolvedValue(notReviewed)
    vi.mocked(guidelinesApi.setGuidelineField).mockResolvedValue({ fieldId: 'randomisation_method', value: 'x', source: 'manual', status: 'satisfied' })
    vi.mocked(guidelinesApi.getFieldEvidence).mockResolvedValue({
      fieldId: 'randomisation_method',
      computed: [],
      suggestions: [],
      evidence: [],
    })
  })

  it('renders the dedicated page for its template (title + description)', async () => {
    renderPage(<GuidelineDetailPage />)
    expect(await screen.findByRole('heading', { name: 'ARRIVE 2.0' })).toBeInTheDocument()
    expect(screen.getByText('Reporting standard.')).toBeInTheDocument()
  })

  it('shows conformance entries with status badges and the score', async () => {
    renderPage(<GuidelineDetailPage />)
    await selectTarget()

    expect(await screen.findByTestId('conformance-score')).toHaveTextContent('3/5')
    expect(screen.getByTestId('totals-satisfied')).toHaveTextContent('3')
    expect(screen.getByTestId('totals-missing')).toHaveTextContent('2')

    const missingRow = await screen.findByTestId('guideline-field-randomisation_method')
    expect(within(missingRow).getByText('Missing')).toBeInTheDocument()
  })

  it('saves a manual field value and invalidates conformance', async () => {
    renderPage(<GuidelineDetailPage />)
    await selectTarget()

    const row = await screen.findByTestId('guideline-field-randomisation_method')
    fireEvent.change(within(row).getByLabelText('Value for randomisation_method'), {
      target: { value: 'Block randomisation' },
    })
    fireEvent.click(within(row).getByRole('button', { name: /^save$/i }))

    await waitFor(() =>
      expect(guidelinesApi.setGuidelineField).toHaveBeenCalledWith(
        'investigations',
        'inv-1',
        'arrive-v2',
        'randomisation_method',
        'Block randomisation',
      ),
    )
  })

  it('marks a filled field reviewed and shows the reviewer', async () => {
    vi.mocked(guidelinesApi.reviewGuidelineField).mockResolvedValue({
      fieldId: 'humane_endpoints',
      reviewStatus: 'reviewed',
      reviewedBy: 'alice@example.org',
      reviewedAt: '2026-07-01T00:00:00Z',
    })
    renderPage(<GuidelineDetailPage />)
    await selectTarget()

    // The satisfied field is in the second (collapsed) section — expand it.
    fireEvent.click(await screen.findByRole('button', { name: /Animal care and monitoring/i }))
    const row = await screen.findByTestId('guideline-field-humane_endpoints')
    fireEvent.click(within(row).getByTestId('review-toggle-humane_endpoints'))

    await waitFor(() =>
      expect(guidelinesApi.reviewGuidelineField).toHaveBeenCalledWith(
        'investigations',
        'inv-1',
        'arrive-v2',
        'humane_endpoints',
        'reviewed',
      ),
    )
  })

  it('shows the QC panel and records a report-level approval', async () => {
    vi.mocked(guidelinesApi.submitGuidelineReviewDecision).mockResolvedValue({
      status: 'approved',
      reviewedBy: 'alice@example.org',
      reviewedAt: '2026-07-01T00:00:00Z',
      note: null,
      history: [{ action: 'report_approved', fieldId: null, actor: 'alice@example.org', occurredAt: '2026-07-01T00:00:00Z', note: null }],
    })
    renderPage(<GuidelineDetailPage />)
    await selectTarget()

    const panel = await screen.findByTestId('review-panel')
    expect(within(panel).getByTestId('review-status')).toHaveTextContent(/not yet reviewed/i)

    fireEvent.click(within(panel).getByTestId('approve-report'))

    await waitFor(() =>
      expect(guidelinesApi.submitGuidelineReviewDecision).toHaveBeenCalledWith(
        'investigations',
        'inv-1',
        'arrive-v2',
        'approved',
        undefined,
      ),
    )
  })
})
