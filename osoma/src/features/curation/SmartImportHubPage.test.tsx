import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { SmartImportHubPage } from './SmartImportHubPage.tsx'

const navigateMock = vi.fn()

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigateMock }
})

vi.mock('@/feature-flags/useFeatureFlag.ts', () => ({
  useFeatureFlag: vi.fn((flag: string) => {
    const defaults: Record<string, boolean> = {
      'metadatapp-feat.enabled': true,
      'feature.curationWorkflow.enabled': true,
      'import.enabled': true,
      'dataEntry.enabled': true,
    }
    return defaults[flag] ?? false
  }),
}))

function renderHub() {
  return render(
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <SmartImportHubPage />
    </MemoryRouter>,
  )
}

describe('SmartImportHubPage', () => {
  beforeEach(() => {
    navigateMock.mockReset()
  })

  it('renders the hub title and subtitle', () => {
    renderHub()
    expect(screen.getByRole('heading', { name: /Smart Import & Curation Hub/i })).toBeInTheDocument()
    expect(screen.getByText(/All data ingestion and AI-powered curation tools/i)).toBeInTheDocument()
  })

  it('shows the recommended pipeline steps', () => {
    renderHub()
    const stepLabels = ['Upload', 'Map', 'Validate', 'Resolve', 'Patch Review', 'Apply']
    for (const label of stepLabels) {
      expect(screen.getByText(label)).toBeInTheDocument()
    }
  })

  it('shows all tool cards', () => {
    renderHub()
    expect(screen.getAllByText('Advanced Curation Workflow').length).toBeGreaterThan(0)
    expect(screen.getByText('CSV Import Wizard')).toBeInTheDocument()
    expect(screen.getByText('Spreadsheet Data Entry')).toBeInTheDocument()
    expect(screen.getAllByText('AI Assistant').length).toBeGreaterThan(0)
    expect(screen.getByText('Per-Subject LLM Curation')).toBeInTheDocument()
    expect(screen.getByText('Schema Profiles & MappView')).toBeInTheDocument()
  })

  it('navigates to curation workflow on button click', async () => {
    const user = userEvent.setup()
    renderHub()
    await user.click(screen.getByRole('button', { name: /Open Curation Workflow/i }))
    expect(navigateMock).toHaveBeenCalledWith('/curation/import')
  })

  it('navigates to CSV import on button click', async () => {
    const user = userEvent.setup()
    renderHub()
    await user.click(screen.getByRole('button', { name: /Open CSV Import/i }))
    expect(navigateMock).toHaveBeenCalledWith('/import/csv')
  })

  it('navigates to AI assistant on button click', async () => {
    const user = userEvent.setup()
    renderHub()
    await user.click(screen.getByRole('button', { name: /Open AI Assistant/i }))
    expect(navigateMock).toHaveBeenCalledWith('/assistant')
  })

  it('shows disabled state when curation workflow flag is off', async () => {
    const { useFeatureFlag } = await import('@/feature-flags/useFeatureFlag.ts')
    vi.mocked(useFeatureFlag).mockImplementation((flag: string) => flag !== 'feature.curationWorkflow.enabled')
    renderHub()
    // Both the Curation Workflow and Schema Profiles cards are disabled when the flag is off
    const disabledMessages = screen.getAllByText(
      "Enable the 'API-Backed Data Curation' feature flag to use this tool.",
    )
    expect(disabledMessages.length).toBeGreaterThanOrEqual(1)
  })

  it('disables CSV Import and Data Entry when metadatapp-feat.enabled is off', async () => {
    const { useFeatureFlag } = await import('@/feature-flags/useFeatureFlag.ts')
    vi.mocked(useFeatureFlag).mockImplementation(
      (flag: string) => flag !== 'metadatapp-feat.enabled',
    )
    renderHub()
    const metaDisabledMessages = screen.getAllByText(
      "Requires the 'MetaDatAPI Features' flag to be enabled.",
    )
    expect(metaDisabledMessages.length).toBeGreaterThanOrEqual(2)
  })
})
