import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { JaxPhenomeBrowserPanel } from './JaxPhenomeBrowserPanel.tsx'
import * as api from '../connected-apps.api.ts'

vi.mock('../connected-apps.api.ts', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../connected-apps.api.ts')>()
    return {
        ...actual,
        searchJaxPhenomeMeasures: vi.fn(),
        getJaxPhenomeMeasure: vi.fn(),
        listJaxPhenomeStrains: vi.fn(),
    }
})

const renderPanel = (ui: ReactNode) => {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false } },
    })
    return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>)
}

describe('JaxPhenomeBrowserPanel', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('shows the empty prompt before a search is submitted', () => {
        renderPanel(<JaxPhenomeBrowserPanel appId="app-1" />)
        expect(screen.getByText(/search the mouse phenome database/i)).toBeInTheDocument()
        expect(api.searchJaxPhenomeMeasures).not.toHaveBeenCalled()
    })

    it('searches measures and renders results', async () => {
        vi.mocked(api.searchJaxPhenomeMeasures).mockResolvedValue({
            totalItems: 1,
            measures: [
                {
                    id: 'mpd-1001',
                    measnum: 1001,
                    name: 'Body weight',
                    description: 'Body weight measured at 8 weeks of age.',
                    units: 'g',
                    projectsym: 'Jaxwest1',
                },
            ],
        })

        renderPanel(<JaxPhenomeBrowserPanel appId="app-1" />)

        fireEvent.change(screen.getByPlaceholderText(/search phenotype measures/i), {
            target: { value: 'body weight' },
        })
        fireEvent.click(screen.getByRole('button', { name: /search/i }))

        await waitFor(() => {
            expect(screen.getByText('Body weight')).toBeInTheDocument()
        })
        expect(screen.getByText(/body weight measured at 8 weeks/i)).toBeInTheDocument()
        expect(api.searchJaxPhenomeMeasures).toHaveBeenCalledWith('app-1', 'body weight', 20, 0)
    })

    it('opens the detail view when a measure is clicked', async () => {
        vi.mocked(api.searchJaxPhenomeMeasures).mockResolvedValue({
            totalItems: 1,
            measures: [
                { id: 'mpd-1002', measnum: 1002, name: 'Fasting blood glucose', units: 'mg/dL' },
            ],
        })
        vi.mocked(api.getJaxPhenomeMeasure).mockResolvedValue({
            id: 'mpd-1002',
            measnum: 1002,
            name: 'Fasting blood glucose',
            description: 'Blood glucose after a 4-hour fast.',
            units: 'mg/dL',
            projectsym: 'Jaxwest2',
        })

        renderPanel(<JaxPhenomeBrowserPanel appId="app-1" />)

        fireEvent.change(screen.getByPlaceholderText(/search phenotype measures/i), {
            target: { value: 'glucose' },
        })
        fireEvent.click(screen.getByRole('button', { name: /search/i }))

        await waitFor(() => {
            expect(screen.getByText('Fasting blood glucose')).toBeInTheDocument()
        })

        fireEvent.click(screen.getByText('Fasting blood glucose'))

        await waitFor(() => {
            expect(api.getJaxPhenomeMeasure).toHaveBeenCalledWith('app-1', 'mpd-1002')
        })
        await waitFor(() => {
            expect(screen.getByText(/blood glucose after a 4-hour fast/i)).toBeInTheDocument()
        })
    })

    it('shows a no-results message when search returns nothing', async () => {
        vi.mocked(api.searchJaxPhenomeMeasures).mockResolvedValue({
            totalItems: 0,
            measures: [],
        })

        renderPanel(<JaxPhenomeBrowserPanel appId="app-1" />)

        fireEvent.change(screen.getByPlaceholderText(/search phenotype measures/i), {
            target: { value: 'nonexistent' },
        })
        fireEvent.click(screen.getByRole('button', { name: /search/i }))

        await waitFor(() => {
            expect(screen.getByText(/no measures found/i)).toBeInTheDocument()
        })
    })

    it('loads and lists strains when switching to the Strains tab', async () => {
        vi.mocked(api.listJaxPhenomeStrains).mockResolvedValue({
            totalItems: 2,
            strains: [
                { id: 'strain-1', strainid: 1, name: 'C57BL/6J', symbol: 'B6' },
                { id: 'strain-2', strainid: 2, name: 'BALB/cJ', symbol: 'C' },
            ],
        })

        renderPanel(<JaxPhenomeBrowserPanel appId="app-1" />)
        expect(api.listJaxPhenomeStrains).not.toHaveBeenCalled()

        fireEvent.click(screen.getByRole('tab', { name: /strains/i }))

        await waitFor(() => {
            expect(screen.getByText('C57BL/6J')).toBeInTheDocument()
        })
        expect(screen.getByText('BALB/cJ')).toBeInTheDocument()
        expect(api.listJaxPhenomeStrains).toHaveBeenCalledWith('app-1', 20, 0)
    })

    it('opens a strain detail view from the row without an extra fetch', async () => {
        vi.mocked(api.listJaxPhenomeStrains).mockResolvedValue({
            totalItems: 1,
            strains: [{ id: 'strain-9', strainid: 9, name: 'DBA/2J', symbol: 'D2', mpdName: 'DBA' }],
        })

        renderPanel(<JaxPhenomeBrowserPanel appId="app-1" />)
        fireEvent.click(screen.getByRole('tab', { name: /strains/i }))

        await waitFor(() => {
            expect(screen.getByText('DBA/2J')).toBeInTheDocument()
        })

        fireEvent.click(screen.getByText('DBA/2J'))

        // The detail panel renders extra row fields (e.g. mpdName) inline.
        await waitFor(() => {
            expect(screen.getByText('mpdName')).toBeInTheDocument()
        })
        expect(screen.getByText('DBA')).toBeInTheDocument()
    })
})
