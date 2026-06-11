import { useMemo, useState } from 'react'
import type { ClipboardEvent } from 'react'
import { CheckCircle2, PlusCircle, Trash2, XCircle } from 'lucide-react'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog.tsx'
import { lookupSubjectIds } from './subjectLookup.ts'
import type { SubjectLookupResult } from './subjectLookup.ts'

const measurementTypes = [
    { value: 'body_weight', label: 'Body Weight (g)' },
    { value: 'glucose', label: 'Blood Glucose (mmol/L)' },
    { value: 'temperature', label: 'Body Temperature (°C)' },
    { value: 'tumor_volume', label: 'Tumor Volume (mm³)' },
    { value: 'food_intake', label: 'Food Intake (g/day)' },
    { value: 'water_intake', label: 'Water Intake (mL/day)' },
    { value: 'custom', label: 'Custom measurement' },
]

type ValidationStatus = 'idle' | 'found' | 'not-found'

type Row = {
    id: string
    subjectId: string
    value: string
    note: string
    status: ValidationStatus
    lookupResult?: SubjectLookupResult
}

const createRow = (index: number): Row => ({
    id: `row-${Date.now()}-${index}`,
    subjectId: '',
    value: '',
    note: '',
    status: 'idle',
})

const isValidNumericValue = (value: string): boolean => {
    const trimmed = value.trim()
    return trimmed.length > 0 && !Number.isNaN(Number(trimmed))
}

const EXAMPLE_DATA: Array<{ subjectId: string; value: string }> = [
    { subjectId: 'SD-2568', value: '23.42' },
    { subjectId: 'SD-2569', value: '24.81' },
    { subjectId: 'SD-2570', value: '22.56' },
    { subjectId: 'SD-2571', value: '25.14' },
    { subjectId: 'SD-2572', value: '23.07' },
    { subjectId: 'SD-2573', value: '24.39' },
]

export function DataEntryGridPage() {
    const [measurementType, setMeasurementType] = useState('body_weight')
    const [rows, setRows] = useState<Row[]>(Array.from({ length: 6 }, (_, i) => createRow(i)))
    const [validating, setValidating] = useState(false)
    const [confirmOpen, setConfirmOpen] = useState(false)
    const [importDone, setImportDone] = useState(false)

    const filledRows = useMemo(
        () => rows.filter((r) => r.subjectId.trim() && isValidNumericValue(r.value)),
        [rows]
    )
    const validatedRows = useMemo(
        () => rows.filter((r) => r.status === 'found' && isValidNumericValue(r.value)),
        [rows]
    )
    const unknownRows = useMemo(() => rows.filter((r) => r.status === 'not-found'), [rows])
    const hasValidated = useMemo(() => rows.some((r) => r.status !== 'idle'), [rows])

    const cohortGroups = useMemo(() => {
        const map = new Map<string, number>()
        for (const row of validatedRows) {
            const cohort = row.lookupResult?.cohort ?? 'Unknown cohort'
            map.set(cohort, (map.get(cohort) ?? 0) + 1)
        }
        return [...map.entries()].sort((a, b) => b[1] - a[1])
    }, [validatedRows])

    const dominantCohort = cohortGroups.length > 0 ? cohortGroups[0][0] : null

    const measurementLabel =
        measurementTypes.find((m) => m.value === measurementType)?.label ?? measurementType

    const updateCell = (rowIndex: number, key: keyof Row, value: string) => {
        setRows((prev) =>
            prev.map((row, i) =>
                i === rowIndex ? { ...row, [key]: value, status: 'idle', lookupResult: undefined } : row
            )
        )
    }

    const addRow = () => {
        setRows((prev) => [...prev, createRow(prev.length)])
    }

    const removeRow = (rowIndex: number) => {
        setRows((prev) => prev.filter((_, i) => i !== rowIndex))
    }

    const handlePaste = (rowIndex: number, event: ClipboardEvent<HTMLInputElement>) => {
        const content = event.clipboardData.getData('text')
        if (!content.includes('\t') && !content.includes('\n')) return
        event.preventDefault()

        const matrix = content
            .trim()
            .split('\n')
            .filter(Boolean)
            .map((line) => line.split('\t'))

        setRows((prev) => {
            const next = [...prev]
            matrix.forEach((cols, offset) => {
                const target = rowIndex + offset
                if (target >= next.length) {
                    next.push(createRow(next.length))
                }
                next[target] = {
                    ...next[target],
                    subjectId: cols[0]?.trim() ?? next[target].subjectId,
                    value: cols[1]?.trim() ?? next[target].value,
                    note: cols[2]?.trim() ?? next[target].note,
                    status: 'idle',
                    lookupResult: undefined,
                }
            })
            return next
        })
    }

    const loadExample = () => {
        setRows((prev) => {
            const base =
                prev.length >= EXAMPLE_DATA.length
                    ? [...prev]
                    : [
                          ...prev,
                          ...Array.from({ length: EXAMPLE_DATA.length - prev.length }, (_, i) =>
                              createRow(prev.length + i)
                          ),
                      ]
            return base.map((row, i) => {
                const ex = EXAMPLE_DATA[i]
                if (!ex) return row
                return { ...row, subjectId: ex.subjectId, value: ex.value, status: 'idle', lookupResult: undefined }
            })
        })
    }

    const handleValidate = async () => {
        const ids = filledRows.map((r) => r.subjectId.trim())
        if (!ids.length) return
        setValidating(true)
        try {
            const results = await lookupSubjectIds(ids)
            setRows((prev) =>
                prev.map((row) => {
                    const trimmed = row.subjectId.trim()
                    if (!trimmed) return row
                    const hit = results[trimmed]
                    if (!hit) return { ...row, status: 'not-found' as ValidationStatus }
                    return {
                        ...row,
                        status: (hit.found ? 'found' : 'not-found') as ValidationStatus,
                        lookupResult: hit,
                    }
                })
            )
        } finally {
            setValidating(false)
        }
    }

    const handleConfirmImport = () => {
        setConfirmOpen(false)
        setImportDone(true)
    }

    const resetGrid = () => {
        setImportDone(false)
        setRows(Array.from({ length: 6 }, (_, i) => createRow(i)))
    }

    if (importDone) {
        return (
            <div className="space-y-6">
                <PageHeader
                    title="Spreadsheet Data Entry"
                    subtitle="Bulk edit records with keyboard-friendly table input and clipboard paste support."
                />
                <Card>
                    <CardContent className="flex flex-col items-center gap-4 py-16">
                        <CheckCircle2 className="h-12 w-12 text-green-500" />
                        <p className="text-lg font-semibold">
                            {validatedRows.length} {measurementLabel} measurement
                            {validatedRows.length !== 1 ? 's' : ''} imported
                            {dominantCohort ? ` into "${dominantCohort}"` : ''}.
                        </p>
                        <Button onClick={resetGrid}>Start new entry</Button>
                    </CardContent>
                </Card>
            </div>
        )
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Spreadsheet Data Entry"
                subtitle="Bulk edit records with keyboard-friendly table input and clipboard paste support."
                actions={
                    <Button variant="outline" size="sm" onClick={loadExample}>
                        Load example data
                    </Button>
                }
            />

            {/* Measurement type selector */}
            <Card>
                <CardHeader>
                    <CardTitle>Measurement type</CardTitle>
                    <CardDescription>Select what kind of data you are recording.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-wrap gap-2">
                        {measurementTypes.map((m) => (
                            <button
                                key={m.value}
                                onClick={() => setMeasurementType(m.value)}
                                className={`rounded-full border px-4 py-1.5 text-sm transition-colors ${
                                    measurementType === m.value
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-line bg-surface text-slate-600 hover:border-slate-400'
                                }`}
                            >
                                {m.label}
                            </button>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Data grid */}
            <Card>
                <CardHeader>
                    <CardTitle>Bulk input grid</CardTitle>
                    <CardDescription>
                        Enter Subject IDs and {measurementLabel} values. Paste tab-separated data from Excel/Sheets
                        (ID[Tab]Value) to fill multiple rows at once.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="overflow-x-auto rounded-2xl border border-line">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs text-slate-500">Subject ID</th>
                                    <th className="px-3 py-2 text-left text-xs text-slate-500">{measurementLabel}</th>
                                    <th className="px-3 py-2 text-left text-xs text-slate-500">Note (optional)</th>
                                    {hasValidated && (
                                        <th className="px-3 py-2 text-left text-xs text-slate-500">Status</th>
                                    )}
                                    <th className="w-10 px-2 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, rowIndex) => (
                                    <tr key={row.id} className="border-t border-line">
                                        <td className="px-2 py-1">
                                            <input
                                                value={row.subjectId}
                                                onChange={(e) => updateCell(rowIndex, 'subjectId', e.target.value)}
                                                onPaste={(e) => handlePaste(rowIndex, e)}
                                                placeholder="e.g. SD-2568"
                                                className="h-9 w-full rounded-lg border border-line px-2 text-sm focus:outline-none focus:ring-1 focus:ring-slate-400"
                                            />
                                        </td>
                                        <td className="px-2 py-1">
                                            <input
                                                value={row.value}
                                                onChange={(e) => updateCell(rowIndex, 'value', e.target.value)}
                                                placeholder="0.00"
                                                className={`h-9 w-full rounded-lg border px-2 text-sm focus:outline-none focus:ring-1 focus:ring-slate-400 ${
                                                    row.subjectId.trim() && row.value.trim() && !isValidNumericValue(row.value)
                                                        ? 'border-red-400 bg-red-50'
                                                        : 'border-line'
                                                }`}
                                            />
                                        </td>
                                        <td className="px-2 py-1">
                                            <input
                                                value={row.note}
                                                onChange={(e) => updateCell(rowIndex, 'note', e.target.value)}
                                                placeholder="Optional"
                                                className="h-9 w-full rounded-lg border border-line px-2 text-sm focus:outline-none focus:ring-1 focus:ring-slate-400"
                                            />
                                        </td>
                                        {hasValidated && (
                                            <td className="px-3 py-1">
                                                {row.status === 'found' && (
                                                    <span className="flex items-center gap-1 text-xs text-green-600">
                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                        {row.lookupResult?.cohort ?? 'Found'}
                                                    </span>
                                                )}
                                                {row.status === 'not-found' && (
                                                    <span className="flex items-center gap-1 text-xs text-red-500">
                                                        <XCircle className="h-3.5 w-3.5" />
                                                        Not found
                                                    </span>
                                                )}
                                                {row.status === 'idle' && row.subjectId && (
                                                    <span className="text-xs text-slate-400">—</span>
                                                )}
                                            </td>
                                        )}
                                        <td className="px-2 py-1 text-right">
                                            <button
                                                onClick={() => removeRow(rowIndex)}
                                                className="rounded p-1 text-slate-300 hover:text-red-400"
                                                title="Remove row"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex items-center justify-between">
                        <button
                            onClick={addRow}
                            className="flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"
                        >
                            <PlusCircle className="h-4 w-4" />
                            Add row
                        </button>
                        <p className="text-xs text-slate-400">
                            {filledRows.length} row{filledRows.length !== 1 ? 's' : ''} filled
                        </p>
                    </div>

                    {/* Validation summary */}
                    {hasValidated && (
                        <div className="rounded-xl border border-line bg-slate-50 p-3 text-sm">
                            <p className="font-medium text-slate-700">Validation summary</p>
                            <ul className="mt-1 space-y-0.5 text-slate-600">
                                <li>
                                    <CheckCircle2 className="mr-1 inline h-3.5 w-3.5 text-green-500" />
                                    {validatedRows.length} subject{validatedRows.length !== 1 ? 's' : ''} found
                                </li>
                                {unknownRows.length > 0 && (
                                    <li>
                                        <XCircle className="mr-1 inline h-3.5 w-3.5 text-red-400" />
                                        {unknownRows.length} subject{unknownRows.length !== 1 ? 's' : ''} not
                                        recognized — these rows will be skipped
                                    </li>
                                )}
                                {cohortGroups.map(([cohort, count]) => (
                                    <li key={cohort} className="text-xs text-slate-500">
                                        &nbsp;&nbsp;• {count} measurement{count !== 1 ? 's' : ''} → study cohort
                                        &quot;{cohort}&quot;
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="flex items-center justify-end gap-3">
                        {hasValidated ? (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        setRows((prev) =>
                                            prev.map((r) => ({ ...r, status: 'idle', lookupResult: undefined }))
                                        )
                                    }
                                >
                                    Re-enter data
                                </Button>
                                <Button disabled={validatedRows.length === 0} onClick={() => setConfirmOpen(true)}>
                                    Confirm import ({validatedRows.length} rows)
                                </Button>
                            </>
                        ) : (
                            <Button onClick={handleValidate} disabled={validating || filledRows.length === 0}>
                                {validating ? 'Validating IDs…' : 'Validate Subject IDs'}
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Confirmation dialog */}
            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm data import</DialogTitle>
                        <DialogDescription>
                            You are about to add <strong>{validatedRows.length}</strong> {measurementLabel}{' '}
                            measurement{validatedRows.length !== 1 ? 's' : ''} to the following investigation
                            {cohortGroups.length !== 1 ? 's' : ''}:
                        </DialogDescription>
                    </DialogHeader>

                    <ul className="space-y-1 rounded-xl border border-line bg-slate-50 p-3 text-sm">
                        {cohortGroups.map(([cohort, count]) => (
                            <li key={cohort} className="flex items-center justify-between">
                                <span className="font-medium text-slate-800">{cohort}</span>
                                <span className="text-slate-500">
                                    {count} record{count !== 1 ? 's' : ''}
                                </span>
                            </li>
                        ))}
                    </ul>

                    {unknownRows.length > 0 && (
                        <p className="text-xs text-slate-500">
                            {unknownRows.length} unrecognized subject ID{unknownRows.length !== 1 ? 's' : ''} will be
                            skipped.
                        </p>
                    )}

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleConfirmImport}>Yes, import data</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    )
}
