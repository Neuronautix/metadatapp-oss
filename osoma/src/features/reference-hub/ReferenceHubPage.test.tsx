import { render, screen, fireEvent, waitFor, within } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { ToastProvider } from '@/components/ui/toast.tsx'
import { ReferenceHubPage } from './ReferenceHubPage.tsx'
import * as referenceApi from './reference-hub.api.ts'

const { navigateMock } = vi.hoisted(() => ({ navigateMock: vi.fn() }))

vi.mock('./reference-hub.api.ts', async (importOriginal) => ({
    ...(await importOriginal<typeof import('./reference-hub.api.ts')>()),
    searchReferences: vi.fn(),
    importReferences: vi.fn(),
    listImportedReferences: vi.fn(),
    deleteImportedReference: vi.fn(),
    promoteReferenceToCde: vi.fn(),
    findSimilarReferences: vi.fn(),
    bestMatchReferences: vi.fn(),
}))

vi.mock('@/feature-flags/FeatureFlagProvider.tsx', () => ({
    useFeatureFlagContext: () => ({ isEnabled: () => true }),
}))

vi.mock('react-router-dom', async (importOriginal) => ({
    ...(await importOriginal<typeof import('react-router-dom')>()),
    useNavigate: () => navigateMock,
}))

const renderPage = (ui: ReactNode) => {
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    return render(
        <QueryClientProvider client={queryClient}>
            <ToastProvider>{ui}</ToastProvider>
        </QueryClientProvider>,
    )
}

const sampleResults = [
    { id: 'jax_phenome:measure:1', source: 'jax_phenome', sourceName: 'JAX Phenome (MPD)', type: 'measure', title: 'glucose (plasma)', subtitle: 'mg/dL', externalUrl: 'https://phenome.jax.org/measureset/1', raw: { measnum: 1 } },
    { id: 'nih_cde:cde_element:abc', source: 'nih_cde', sourceName: 'NIH CDE Repository', type: 'cde_element', title: 'Blood glucose unit of measure', externalUrl: 'https://cde.nlm.nih.gov/deView?tinyId=abc', raw: { tinyId: 'abc' } },
]

async function runSearch() {
    fireEvent.change(screen.getByPlaceholderText(/search across connected apps/i), { target: { value: 'glucose' } })
    fireEvent.click(screen.getByRole('button', { name: 'Search' }))
    await waitFor(() => expect(screen.getByText('glucose (plasma)')).toBeInTheDocument())
}

describe('ReferenceHubPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        window.localStorage.clear()
        vi.mocked(referenceApi.searchReferences).mockResolvedValue(sampleResults)
        vi.mocked(referenceApi.listImportedReferences).mockResolvedValue([])
        vi.mocked(referenceApi.importReferences).mockResolvedValue({ count: 2 })
    })

    it('searches and renders results grouped by source', async () => {
        renderPage(<ReferenceHubPage />)
        await runSearch()
        expect(screen.getByText('Blood glucose unit of measure')).toBeInTheDocument()
        expect(screen.getByText('JAX Phenome (MPD)')).toBeInTheDocument()
    })

    it('suggests a best match and badges the top result', async () => {
        vi.mocked(referenceApi.bestMatchReferences).mockResolvedValue([
            { id: 'nih_cde:cde_element:abc', score: 0.9, reason: 'Strong overlap with your query terms.' },
        ])
        renderPage(<ReferenceHubPage />)
        await runSearch()

        fireEvent.click(screen.getByRole('button', { name: /suggest best match/i }))
        await waitFor(() => expect(referenceApi.bestMatchReferences).toHaveBeenCalled())
        expect(await screen.findByText('Best match')).toBeInTheDocument()
    })

    it('selects results and imports them into the library', async () => {
        renderPage(<ReferenceHubPage />)
        await runSearch()

        fireEvent.click(screen.getByRole('checkbox', { name: /select glucose \(plasma\)/i }))
        fireEvent.click(screen.getByRole('checkbox', { name: /select blood glucose unit/i }))

        expect(screen.getByText('2 selected')).toBeInTheDocument()
        fireEvent.click(screen.getByRole('button', { name: /^Import$/i }))

        await waitFor(() => expect(referenceApi.importReferences).toHaveBeenCalledTimes(1))
        const imported = vi.mocked(referenceApi.importReferences).mock.calls[0][0]
        expect(imported.map((i) => i.id)).toEqual(['jax_phenome:measure:1', 'nih_cde:cde_element:abc'])
    })

    it('navigates to the compare page with the selected items', async () => {
        renderPage(<ReferenceHubPage />)
        await runSearch()
        fireEvent.click(screen.getByRole('checkbox', { name: /select glucose \(plasma\)/i }))
        fireEvent.click(screen.getByRole('checkbox', { name: /select blood glucose unit/i }))
        fireEvent.click(screen.getByRole('button', { name: /Compare/i }))

        expect(navigateMock).toHaveBeenCalledWith('/reference-hub/compare')
        const stored = JSON.parse(window.sessionStorage.getItem('osoma.referenceHub.compareItems') ?? '[]')
        expect(stored.map((i: { id: string }) => i.id)).toEqual(['jax_phenome:measure:1', 'nih_cde:cde_element:abc'])
    })

    it('lists and deletes imported references in the library tab', async () => {
        vi.mocked(referenceApi.listImportedReferences).mockResolvedValue([
            { id: 'ir-1', referenceId: 'jax_phenome:measure:1', source: 'jax_phenome', sourceName: 'JAX Phenome (MPD)', type: 'measure', title: 'glucose (plasma)', importedAt: '2026-06-29T12:00:00Z' },
        ])
        renderPage(<ReferenceHubPage />)

        fireEvent.click(screen.getByRole('tab', { name: /my library/i }))
        await waitFor(() => expect(screen.getByText('glucose (plasma)')).toBeInTheDocument())

        fireEvent.click(screen.getByRole('button', { name: /remove from library/i }))
        await waitFor(() => expect(referenceApi.deleteImportedReference).toHaveBeenCalledWith('ir-1'))
    })

    it('promotes a standardized library reference to a CDE', async () => {
        vi.mocked(referenceApi.promoteReferenceToCde).mockResolvedValue({ id: 'cde-1', label: 'Blood glucose' })
        vi.mocked(referenceApi.listImportedReferences).mockResolvedValue([
            { id: 'ir-9', referenceId: 'jax_phenome:measure:1', source: 'jax_phenome', sourceName: 'JAX Phenome (MPD)', type: 'measure', title: 'Blood glucose', importedAt: '2026-06-29T12:00:00Z', canonicalTermIri: 'http://purl.obolibrary.org/obo/VT_0000188', canonicalTermLabel: 'blood glucose level', canonicalTermOntology: 'vt' },
        ])
        renderPage(<ReferenceHubPage />)

        fireEvent.click(screen.getByRole('tab', { name: /my library/i }))
        await waitFor(() => expect(screen.getByText('Blood glucose')).toBeInTheDocument())

        fireEvent.click(screen.getByRole('button', { name: /promote blood glucose to cde/i }))
        await waitFor(() => expect(referenceApi.promoteReferenceToCde).toHaveBeenCalledWith('ir-9'))
    })

    it('finds similar references for a library item', async () => {
        vi.mocked(referenceApi.listImportedReferences).mockResolvedValue([
            { id: 'ir-5', referenceId: 'jax_phenome:measure:1', source: 'jax_phenome', sourceName: 'JAX Phenome (MPD)', type: 'measure', title: 'Blood glucose', importedAt: '2026-06-29T12:00:00Z' },
        ])
        vi.mocked(referenceApi.findSimilarReferences).mockResolvedValue([
            { id: 'impc:measure:2', source: 'impc', sourceName: 'IMPReSS (IMPC)', type: 'measure', title: 'Blood glucose level', score: 0.94 },
        ])
        renderPage(<ReferenceHubPage />)

        fireEvent.click(screen.getByRole('tab', { name: /my library/i }))
        await waitFor(() => expect(screen.getByText('Blood glucose')).toBeInTheDocument())

        fireEvent.click(screen.getByRole('button', { name: /find references similar to blood glucose/i }))
        await waitFor(() => expect(referenceApi.findSimilarReferences).toHaveBeenCalledWith('ir-5', 8))

        const results = await screen.findByTestId('similar-references-results')
        expect(within(results).getByText('Blood glucose level')).toBeInTheDocument()
        expect(within(results).getByText('94%')).toBeInTheDocument()
    })
})
