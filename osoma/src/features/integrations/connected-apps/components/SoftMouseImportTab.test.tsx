import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { SoftMouseImportTab } from './SoftMouseImportTab.tsx'
import { validateCsv } from './softmouse-import.utils.ts'
import { ToastProvider } from '@/components/ui/toast.tsx'
import type { ReactNode } from 'react'

const renderWithToast = (ui: ReactNode) =>
    render(<ToastProvider>{ui}</ToastProvider>)

// ---------------------------------------------------------------------------
// Unit tests: validateCsv
// ---------------------------------------------------------------------------
describe('validateCsv', () => {
    it('passes when all required columns are present and rows exist', () => {
        const headers = ['name', 'cage', 'strain']
        const rows = [{ name: 'Mouse-001', cage: 'Cage-A', strain: 'C57BL/6' }]
        const result = validateCsv(headers, rows)
        expect(result.valid).toBe(true)
        expect(result.errors).toHaveLength(0)
        expect(result.rowCount).toBe(1)
    })

    it('errors when "name" column is missing', () => {
        const headers = ['cage', 'strain']
        const rows = [{ cage: 'Cage-A', strain: 'C57BL/6' }]
        const result = validateCsv(headers, rows)
        expect(result.valid).toBe(false)
        expect(result.errors).toContain('Missing required column: "name"')
    })

    it('errors when "cage" column is missing', () => {
        const headers = ['name', 'strain']
        const rows = [{ name: 'Mouse-001', strain: 'C57BL/6' }]
        const result = validateCsv(headers, rows)
        expect(result.valid).toBe(false)
        expect(result.errors).toContain('Missing required column: "cage"')
    })

    it('errors when there are no data rows', () => {
        const result = validateCsv(['name', 'cage'], [])
        expect(result.valid).toBe(false)
        expect(result.errors).toContain('No data rows found in CSV.')
    })

    it('warns about unknown columns', () => {
        const headers = ['name', 'cage', 'unknownCol']
        const rows = [{ name: 'Mouse-001', cage: 'Cage-A', unknownCol: 'x' }]
        const result = validateCsv(headers, rows)
        expect(result.valid).toBe(true)
        // validateCsv lowercases headers for matching; warning contains the lowercased name
        expect(result.warnings[0]).toMatch(/unknowncol/i)
    })

    it('is case-insensitive for required headers', () => {
        const headers = ['Name', 'CAGE']
        const rows = [{ Name: 'Mouse-001', CAGE: 'Cage-A' }]
        const result = validateCsv(headers, rows)
        expect(result.valid).toBe(true)
    })
})

// ---------------------------------------------------------------------------
// Component tests: SoftMouseImportTab
// ---------------------------------------------------------------------------
const VALID_CSV = 'name,cage,strain\nMouse-001,Cage-A,C57BL/6\nMouse-002,Cage-B,BALB/c'
const MISSING_CAGE_CSV = 'name,strain\nMouse-001,C57BL/6'

describe('SoftMouseImportTab', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders step 1 with file upload zone and textarea', () => {
        renderWithToast(<SoftMouseImportTab appId="app-1" />)
        expect(screen.getByRole('button', { name: /preview data/i })).toBeDisabled()
        expect(screen.getByLabelText(/csv content/i)).toBeInTheDocument()
    })

    it('file upload zone is keyboard-accessible via label', () => {
        renderWithToast(<SoftMouseImportTab appId="app-1" />)
        // The label element wraps the file input via htmlFor, making it keyboard-accessible
        const label = document.querySelector('label[for="softmouse-csv-file"]')
        const fileInput = document.getElementById('softmouse-csv-file')
        expect(label).not.toBeNull()
        expect(fileInput).not.toBeNull()
        expect(fileInput).toHaveAttribute('type', 'file')
        // The input has sr-only class (visually hidden but accessible)
        expect(fileInput).toHaveClass('sr-only')
    })

    it('happy path: paste valid CSV → preview → see validation pass', async () => {
        renderWithToast(<SoftMouseImportTab appId="app-1" />)

        fireEvent.change(screen.getByLabelText(/csv content/i), { target: { value: VALID_CSV } })

        await waitFor(() => {
            expect(screen.getByText(/2 rows detected/i)).toBeInTheDocument()
        })

        fireEvent.click(screen.getByRole('button', { name: /preview data/i }))

        await waitFor(() => {
            expect(screen.getByText(/ready to import — 2 animals/i)).toBeInTheDocument()
        })

        // Preview table should show header and data row
        expect(screen.getByText('Mouse-001')).toBeInTheDocument()
        expect(screen.getByText('Cage-A')).toBeInTheDocument()
    })

    it('shows validation errors when required column is missing', async () => {
        renderWithToast(<SoftMouseImportTab appId="app-1" />)

        fireEvent.change(screen.getByLabelText(/csv content/i), { target: { value: MISSING_CAGE_CSV } })

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /preview data/i })).not.toBeDisabled()
        })

        fireEvent.click(screen.getByRole('button', { name: /preview data/i }))

        await waitFor(() => {
            expect(screen.getByText(/missing required column.*cage/i)).toBeInTheDocument()
        })
    })

    it('correctly handles quoted CSV fields containing commas', async () => {
        // papaparse should handle: name with comma in quotes
        const csvWithQuotes = 'name,cage\n"Mouse, A",Cage-1'
        renderWithToast(<SoftMouseImportTab appId="app-1" />)

        fireEvent.change(screen.getByLabelText(/csv content/i), { target: { value: csvWithQuotes } })

        await waitFor(() => {
            expect(screen.getByText(/1 row detected/i)).toBeInTheDocument()
        })

        fireEvent.click(screen.getByRole('button', { name: /preview data/i }))

        await waitFor(() => {
            expect(screen.getByText('Mouse, A')).toBeInTheDocument()
        })
    })
})
