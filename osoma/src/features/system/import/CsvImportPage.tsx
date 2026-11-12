import { useCallback, useMemo, useRef, useState } from 'react'
import type { ChangeEvent, DragEvent } from 'react'
import { CheckCircle2, Upload, XCircle } from 'lucide-react'
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
import { lookupSubjectIds } from '../data-entry/subjectLookup.ts'
import type { SubjectLookupResult } from '../data-entry/subjectLookup.ts'

type ImportStep = 1 | 2 | 3 | 4

const supportedFields = ['id', 'name', 'species', 'strain', 'cage', 'birthAt']

const SUBJECT_ID_PATTERN = /^[A-Z]{2,4}-\d{3,6}$/

function detectSubjectIdColumn(headers: string[]): number {
    const hints = ['id', 'subject', 'animal', 'mouse', 'subject_id', 'animal_id', 'sd', 'sub']
    for (let i = 0; i < headers.length; i++) {
        if (hints.some((h) => headers[i].toLowerCase().includes(h))) return i
    }
    return 0
}

function extractSubjectIds(rows: string[][], colIndex: number): string[] {
    return rows
        .slice(1)
        .map((row) => row[colIndex]?.trim() ?? '')
        .filter((v) => v.length > 0)
}

export function CsvImportPage() {
    const [step, setStep] = useState<ImportStep>(1)
    const [rawCsv, setRawCsv] = useState('')
    const [templateName, setTemplateName] = useState('')
    const [savedTemplate, setSavedTemplate] = useState('')
    const [dragging, setDragging] = useState(false)
    const [fileName, setFileName] = useState<string | null>(null)
    const [columnMapping, setColumnMapping] = useState<Record<string, string>>({})
    const [lookupResults, setLookupResults] = useState<Record<string, SubjectLookupResult>>({})
    const [lookingUp, setLookingUp] = useState(false)
    const [confirmOpen, setConfirmOpen] = useState(false)
    const [importDone, setImportDone] = useState(false)
    const fileInputRef = useRef<HTMLInputElement>(null)

    const rows = useMemo(() => {
        const lines = rawCsv
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean)
        if (!lines.length) return [] as string[][]
        return lines.map((line) => line.split(',').map((cell) => cell.trim()))
    }, [rawCsv])

    const headers = rows[0] ?? []
    const previewRows = rows.slice(1, 6)
    const hasRequired = headers.some((h) => h.toLowerCase() === 'name')

    const subjectColIndex = useMemo(() => detectSubjectIdColumn(headers), [headers])
    const subjectIds = useMemo(() => extractSubjectIds(rows, subjectColIndex), [rows, subjectColIndex])
    const detectedSubjectIds = useMemo(
        () => subjectIds.filter((id) => SUBJECT_ID_PATTERN.test(id)),
        [subjectIds]
    )

    const foundSubjects = useMemo(
        () => Object.values(lookupResults).filter((r) => r.found),
        [lookupResults]
    )
    const unknownSubjects = useMemo(
        () => Object.values(lookupResults).filter((r) => !r.found),
        [lookupResults]
    )

    const cohortGroups = useMemo(() => {
        const map = new Map<string, number>()
        for (const r of foundSubjects) {
            const cohort = r.cohort ?? 'Unknown cohort'
            map.set(cohort, (map.get(cohort) ?? 0) + 1)
        }
        return [...map.entries()]
    }, [foundSubjects])

    const readFile = useCallback((file: File) => {
        setFileName(file.name)
        const reader = new FileReader()
        reader.onload = (e) => {
            setRawCsv((e.target?.result as string) ?? '')
        }
        reader.readAsText(file)
    }, [])

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0]
        if (file) readFile(file)
    }

    const handleDrop = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault()
        setDragging(false)
        const file = e.dataTransfer.files[0]
        if (file) readFile(file)
    }

    const handleRunLookup = async () => {
        const ids = detectedSubjectIds.length > 0 ? detectedSubjectIds : subjectIds.slice(0, 50)
        if (!ids.length) return
        setLookingUp(true)
        try {
            const results = await lookupSubjectIds(ids)
            setLookupResults(results)
        } finally {
            setLookingUp(false)
        }
    }

    const handleConfirmImport = () => {
        setConfirmOpen(false)
        setImportDone(true)
    }

    if (importDone) {
        return (
            <div className="space-y-6">
                <PageHeader title="CSV Import" subtitle="Upload, map, validate, and import external metadata safely." />
                <Card>
                    <CardContent className="flex flex-col items-center gap-4 py-16">
                        <CheckCircle2 className="h-12 w-12 text-green-500" />
                        <p className="text-lg font-semibold">
                            {foundSubjects.length} record{foundSubjects.length !== 1 ? 's' : ''} imported
                            {cohortGroups.length > 0 ? ` into "${cohortGroups[0][0]}"` : ''}.
                        </p>
                        <Button
                            onClick={() => {
                                setImportDone(false)
                                setStep(1)
                                setRawCsv('')
                                setFileName(null)
                                setColumnMapping({})
                                setLookupResults({})
                            }}
                        >
                            Import another file
                        </Button>
                    </CardContent>
                </Card>
            </div>
        )
    }

    return (
        <div className="space-y-6">
            <PageHeader title="CSV Import" subtitle="Upload, map, validate, and import external metadata safely." />

            <Card>
                <CardHeader>
                    <CardTitle>Step {step} of 4</CardTitle>
                    <CardDescription>
                        {step === 1
                            ? 'Upload or paste CSV'
                            : step === 2
                              ? 'Map columns'
                              : step === 3
                                ? 'Run pre-flight QC & subject ID lookup'
                                : 'Confirm import'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {/* ── Step 1: Upload ── */}
                    {step === 1 ? (
                        <>
                            {/* Drag-and-drop zone */}
                            <div
                                onDragOver={(e) => { e.preventDefault(); setDragging(true) }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={handleDrop}
                                className={`flex cursor-pointer flex-col items-center gap-3 rounded-2xl border-2 border-dashed px-6 py-10 text-center transition-colors ${
                                    dragging
                                        ? 'border-slate-500 bg-slate-50'
                                        : 'border-line bg-surface hover:border-slate-400'
                                }`}
                                onClick={() => fileInputRef.current?.click()}
                            >
                                <Upload className="h-8 w-8 text-slate-400" />
                                <div>
                                    <p className="font-medium text-slate-700">
                                        {fileName ? fileName : 'Drop a CSV file here, or click to browse'}
                                    </p>
                                    <p className="text-xs text-slate-400">Supports .csv files</p>
                                </div>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".csv,text/csv"
                                    className="hidden"
                                    onChange={handleFileChange}
                                />
                            </div>

                            <div className="flex items-center gap-3">
                                <div className="h-px flex-1 bg-line" />
                                <span className="text-xs text-slate-400">or paste CSV content below</span>
                                <div className="h-px flex-1 bg-line" />
                            </div>

                            <textarea
                                value={rawCsv}
                                onChange={(e) => { setRawCsv(e.target.value); setFileName(null) }}
                                placeholder={"Paste CSV content here\n\nExample:\nID,BW Value\nSD-2568,23.42\nSD-2569,24.81"}
                                className="min-h-52 w-full rounded-2xl border border-line p-3 text-sm"
                            />
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-slate-500">Rows detected: {rows.length > 0 ? rows.length - 1 : 0} data rows</p>
                                <Button onClick={() => setStep(2)} disabled={rows.length < 2}>
                                    Continue to mapping
                                </Button>
                            </div>
                        </>
                    ) : null}

                    {/* ── Step 2: Column mapping ── */}
                    {step === 2 ? (
                        <>
                            <div className="grid gap-2">
                                {headers.map((header) => (
                                    <div
                                        key={header}
                                        className="flex items-center justify-between rounded-xl border border-line px-3 py-2 text-sm"
                                    >
                                        <span>{header}</span>
                                        <select
                                            className="rounded-full border border-line px-3 py-1 text-xs"
                                            value={columnMapping[header] ?? ''}
                                            onChange={(e) =>
                                                setColumnMapping((prev) => ({ ...prev, [header]: e.target.value }))
                                            }
                                        >
                                            <option value="">Ignore</option>
                                            {supportedFields.map((field) => (
                                                <option key={field} value={field}>
                                                    {field}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                ))}
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <input
                                    value={templateName}
                                    onChange={(e) => setTemplateName(e.target.value)}
                                    placeholder="Template name"
                                    className="h-9 rounded-full border border-line px-3 text-sm"
                                />
                                <Button
                                    variant="outline"
                                    onClick={() => setSavedTemplate(templateName.trim())}
                                    disabled={!templateName.trim()}
                                >
                                    Save template
                                </Button>
                                {savedTemplate ? (
                                    <span className="text-xs text-slate-500">Saved: {savedTemplate}</span>
                                ) : null}
                            </div>
                            <div className="flex items-center justify-between">
                                <Button variant="outline" onClick={() => setStep(1)}>
                                    Back
                                </Button>
                                <Button onClick={() => setStep(3)}>Continue to QC</Button>
                            </div>
                        </>
                    ) : null}

                    {/* ── Step 3: QC ── */}
                    {step === 3 ? (
                        <>
                            <div className="rounded-2xl border border-line p-4 text-sm">
                                <p className="font-semibold text-slate-800">Pre-flight checks</p>
                                <ul className="mt-2 list-disc space-y-1 pl-5 text-slate-600">
                                    <li>
                                        {hasRequired
                                            ? '✅ Required field "name" present'
                                            : '❌ Missing required field "name"'}
                                    </li>
                                    <li>Data rows: {rows.length - 1}</li>
                                    <li>Template: {savedTemplate || 'Not saved'}</li>
                                    <li>
                                        Subject IDs detected:{' '}
                                        {detectedSubjectIds.length > 0
                                            ? `${detectedSubjectIds.length} (column "${headers[subjectColIndex]}")`
                                            : subjectIds.length > 0
                                              ? `${subjectIds.length} non-standard IDs (column "${headers[subjectColIndex]}")`
                                              : 'None detected'}
                                    </li>
                                </ul>
                            </div>

                            {/* Subject ID lookup panel */}
                            {subjectIds.length > 0 && (
                                <div className="rounded-xl border border-line bg-slate-50 p-4 text-sm">
                                    <div className="flex items-center justify-between">
                                        <p className="font-medium text-slate-700">Subject ID lookup</p>
                                        {Object.keys(lookupResults).length === 0 ? (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={handleRunLookup}
                                                disabled={lookingUp}
                                            >
                                                {lookingUp ? 'Looking up…' : 'Run lookup'}
                                            </Button>
                                        ) : (
                                            <Button size="sm" variant="outline" onClick={handleRunLookup} disabled={lookingUp}>
                                                Re-run
                                            </Button>
                                        )}
                                    </div>
                                    {Object.keys(lookupResults).length > 0 && (
                                        <ul className="mt-3 space-y-1">
                                            <li className="flex items-center gap-1 text-green-600">
                                                <CheckCircle2 className="h-3.5 w-3.5" />
                                                {foundSubjects.length} subject{foundSubjects.length !== 1 ? 's' : ''} found in registry
                                            </li>
                                            {unknownSubjects.length > 0 && (
                                                <li className="flex items-center gap-1 text-red-500">
                                                    <XCircle className="h-3.5 w-3.5" />
                                                    {unknownSubjects.length} subject
                                                    {unknownSubjects.length !== 1 ? 's' : ''} not found — will be skipped
                                                </li>
                                            )}
                                            {cohortGroups.map(([cohort, count]) => (
                                                <li key={cohort} className="pl-5 text-xs text-slate-500">
                                                    • {count} record{count !== 1 ? 's' : ''} → cohort &quot;{cohort}&quot;
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            )}

                            <div className="overflow-x-auto rounded-2xl border border-line">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            {headers.map((header) => (
                                                <th
                                                    key={header}
                                                    className="px-3 py-2 text-left text-xs text-slate-500"
                                                >
                                                    {header}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {previewRows.map((row, index) => (
                                            <tr key={index} className="border-t border-line">
                                                {row.map((cell, cellIndex) => (
                                                    <td key={`${index}-${cellIndex}`} className="px-3 py-2">
                                                        {cell}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="flex items-center justify-between">
                                <Button variant="outline" onClick={() => setStep(2)}>
                                    Back
                                </Button>
                                <Button onClick={() => setStep(4)} disabled={foundSubjects.length === 0 && subjectIds.length > 0 && Object.keys(lookupResults).length > 0}>
                                    Continue to confirmation
                                </Button>
                            </div>
                        </>
                    ) : null}

                    {/* ── Step 4: Confirmation ── */}
                    {step === 4 ? (
                        <>
                            <div className="rounded-2xl border border-line p-5 text-sm">
                                <p className="font-semibold text-slate-800">Ready to import</p>
                                <ul className="mt-3 space-y-1.5 text-slate-600">
                                    <li>
                                        <strong>{foundSubjects.length}</strong> subject
                                        {foundSubjects.length !== 1 ? 's' : ''} will receive new records
                                    </li>
                                    {unknownSubjects.length > 0 && (
                                        <li className="text-slate-400">
                                            {unknownSubjects.length} unrecognised ID
                                            {unknownSubjects.length !== 1 ? 's' : ''} will be skipped
                                        </li>
                                    )}
                                    <li>Template: {savedTemplate || 'Not saved'}</li>
                                </ul>
                            </div>

                            {cohortGroups.length > 0 && (
                                <div className="rounded-xl border border-line bg-slate-50 p-4">
                                    <p className="mb-2 text-sm font-medium text-slate-700">
                                        Data will be added to:
                                    </p>
                                    <ul className="space-y-1 text-sm">
                                        {cohortGroups.map(([cohort, count]) => (
                                            <li key={cohort} className="flex items-center justify-between">
                                                <span className="font-medium text-slate-800">{cohort}</span>
                                                <span className="text-slate-500">
                                                    {count} record{count !== 1 ? 's' : ''}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            <div className="flex items-center justify-between">
                                <Button variant="outline" onClick={() => setStep(3)}>
                                    Back
                                </Button>
                                <Button onClick={() => setConfirmOpen(true)} disabled={foundSubjects.length === 0}>
                                    Import data
                                </Button>
                            </div>
                        </>
                    ) : null}
                </CardContent>
            </Card>

            {/* Final confirmation dialog */}
            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm import</DialogTitle>
                        <DialogDescription>
                            Add <strong>{foundSubjects.length}</strong> record
                            {foundSubjects.length !== 1 ? 's' : ''} to{' '}
                            {cohortGroups.length === 1
                                ? `investigation "${cohortGroups[0][0]}"`
                                : `${cohortGroups.length} investigation cohorts`}
                            ?
                        </DialogDescription>
                    </DialogHeader>
                    <ul className="space-y-1 rounded-xl border border-line bg-slate-50 p-3 text-sm">
                        {cohortGroups.map(([cohort, count]) => (
                            <li key={cohort} className="flex items-center justify-between">
                                <span className="font-medium text-slate-800">{cohort}</span>
                                <span className="text-slate-500">{count} record{count !== 1 ? 's' : ''}</span>
                            </li>
                        ))}
                    </ul>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmOpen(false)}>
                            No, cancel
                        </Button>
                        <Button onClick={handleConfirmImport}>Yes, import data</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    )
}
