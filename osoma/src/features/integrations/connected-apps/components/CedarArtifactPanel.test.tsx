import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import type { ReactNode } from 'react'
import { CedarArtifactPanel } from './CedarArtifactPanel.tsx'
import { ToastProvider } from '@/components/ui/toast.tsx'
import * as api from '../connected-apps.api.ts'

vi.mock('../connected-apps.api.ts', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../connected-apps.api.ts')>()
    return {
        ...actual,
        getCedarArtifact: vi.fn(),
        createCedarArtifact: vi.fn(),
        updateCedarArtifact: vi.fn(),
        deleteCedarArtifact: vi.fn(),
        validateCedarArtifact: vi.fn(),
        importCedarTemplate: vi.fn(),
        exportCanonicalFormToCedar: vi.fn(),
        listCanonicalForms: vi.fn(),
    }
})

const renderPanel = (ui: ReactNode) => {
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    return render(
        <MemoryRouter>
            <QueryClientProvider client={queryClient}>
                <ToastProvider>{ui}</ToastProvider>
            </QueryClientProvider>
        </MemoryRouter>,
    )
}

const IRI = 'https://repo.metadatacenter.org/templates/abc'

function setIri(value: string) {
    fireEvent.change(screen.getByPlaceholderText(/repo\.metadatacenter\.org/i), {
        target: { value },
    })
}

describe('CedarArtifactPanel', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        vi.mocked(api.listCanonicalForms).mockResolvedValue([
            { id: 'form-1', title: 'HCM Minimal Metadata Form' },
        ])
    })

    it('fetches an artifact and loads it into the editor', async () => {
        vi.mocked(api.getCedarArtifact).mockResolvedValue({
            artifact: { '@id': IRI, 'schema:name': 'My template' },
        })

        renderPanel(<CedarArtifactPanel appId="app-1" />)
        setIri(IRI)
        fireEvent.click(screen.getByRole('button', { name: /fetch/i }))

        await waitFor(() => {
            expect(api.getCedarArtifact).toHaveBeenCalledWith('app-1', 'template', IRI)
        })
        await waitFor(() => {
            expect(screen.getByText(/"schema:name": "My template"/)).toBeInTheDocument()
        })
    })

    it('does not call the API when the editor JSON is invalid', async () => {
        renderPanel(<CedarArtifactPanel appId="app-1" />)

        fireEvent.change(screen.getByPlaceholderText(/Paste a CEDAR/i), {
            target: { value: '{ not valid json' },
        })
        fireEvent.click(screen.getByRole('button', { name: /create/i }))

        // parseDraft rejects the malformed JSON before any request is issued.
        await new Promise((resolve) => setTimeout(resolve, 0))
        expect(api.createCedarArtifact).not.toHaveBeenCalled()
    })

    it('asks for confirmation before deleting and only deletes after confirming', async () => {
        vi.mocked(api.deleteCedarArtifact).mockResolvedValue({ ok: true, deleted: IRI })

        renderPanel(<CedarArtifactPanel appId="app-1" />)
        setIri(IRI)

        // Opening the toolbar Delete shows the confirmation dialog, not an API call.
        fireEvent.click(screen.getByRole('button', { name: /^delete$/i }))
        expect(api.deleteCedarArtifact).not.toHaveBeenCalled()
        expect(screen.getByRole('heading', { name: /delete this cedar template/i })).toBeInTheDocument()

        // Confirming in the dialog performs the delete.
        fireEvent.click(screen.getByRole('button', { name: /delete template/i }))
        await waitFor(() => {
            expect(api.deleteCedarArtifact).toHaveBeenCalledWith('app-1', 'template', IRI)
        })
    })

    it('cancelling the delete dialog does not delete', () => {
        renderPanel(<CedarArtifactPanel appId="app-1" />)
        setIri(IRI)

        fireEvent.click(screen.getByRole('button', { name: /^delete$/i }))
        fireEvent.click(screen.getByRole('button', { name: /cancel/i }))

        expect(api.deleteCedarArtifact).not.toHaveBeenCalled()
    })

    it('offers a Crosswalk Studio deep link after a successful template import', async () => {
        vi.mocked(api.importCedarTemplate).mockResolvedValue({
            ok: true,
            id: 'tpl-1',
            title: 'Imported template',
            externalUrl: IRI,
            fieldCount: 4,
            crosswalkId: 'cw-1',
        })

        renderPanel(<CedarArtifactPanel appId="app-1" />)
        setIri(IRI)
        fireEvent.click(screen.getByRole('button', { name: /import/i }))

        await waitFor(() => {
            expect(api.importCedarTemplate).toHaveBeenCalledWith('app-1', IRI)
        })

        const link = await screen.findByRole('link', { name: /open in crosswalk studio/i })
        expect(link).toHaveAttribute('href', '/template-crosswalks?crosswalk=cw-1')
        expect(screen.getByText(/4 field\(s\) extracted/i)).toBeInTheDocument()
    })

    it('pretty-prints the editor JSON via Format JSON', () => {
        renderPanel(<CedarArtifactPanel appId="app-1" />)

        const editor = screen.getByPlaceholderText(/Paste a CEDAR/i) as HTMLTextAreaElement
        fireEvent.change(editor, { target: { value: '{"@type":"x","schema:name":"T"}' } })
        fireEvent.click(screen.getByRole('button', { name: /format json/i }))

        expect(editor.value).toContain('\n')
        expect(editor.value).toContain('"@type": "x"')
    })

    it('renders the validation report as a list of messages', async () => {
        vi.mocked(api.validateCedarArtifact).mockResolvedValue({
            validates: false,
            errors: [{ message: 'Missing @id' }],
            warnings: [{ message: 'Deprecated property foo' }],
        })

        renderPanel(<CedarArtifactPanel appId="app-1" />)
        fireEvent.change(screen.getByPlaceholderText(/Paste a CEDAR/i), {
            target: { value: '{"@type":"x"}' },
        })
        fireEvent.click(screen.getByRole('button', { name: /validate/i }))

        await waitFor(() => {
            expect(screen.getByText('Missing @id')).toBeInTheDocument()
        })
        expect(screen.getByText('Deprecated property foo')).toBeInTheDocument()
        // Rendered as list items, not a raw JSON blob.
        expect(screen.getByText('Missing @id').closest('li')).not.toBeNull()
    })

    it('exports a selected canonical form to CEDAR and loads the result into the editor', async () => {
        vi.mocked(api.exportCanonicalFormToCedar).mockResolvedValue({
            ok: true,
            id: 'https://repo.metadatacenter.org/templates/new-form',
            title: 'HCM Minimal Metadata Form',
            fieldCount: 7,
            artifact: { '@id': 'https://repo.metadatacenter.org/templates/new-form', 'schema:name': 'HCM' },
        })

        renderPanel(<CedarArtifactPanel appId="app-1" />)

        // The form list loads; pick it, then push.
        const select = await screen.findByRole('combobox', { name: /canonical form to export/i })
        await waitFor(() => {
            expect(screen.getByRole('option', { name: 'HCM Minimal Metadata Form' })).toBeInTheDocument()
        })
        fireEvent.change(select, { target: { value: 'form-1' } })
        fireEvent.click(screen.getByRole('button', { name: /push to cedar/i }))

        await waitFor(() => {
            expect(api.exportCanonicalFormToCedar).toHaveBeenCalledWith('app-1', 'form-1', undefined)
        })
        await waitFor(() => {
            expect(screen.getByText(/"schema:name": "HCM"/)).toBeInTheDocument()
        })
    })
})
