import { useMemo, useRef, useState } from 'react'
import Papa from 'papaparse'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { Upload, CheckCircle2, XCircle, FileText, AlertTriangle } from 'lucide-react'
import { validateCsv } from './softmouse-import.utils.ts'

type ImportStep = 1 | 2 | 3

const SUPPORTED_FIELDS = ['name', 'cage', 'strain', 'sex', 'birthDate', 'earTag', 'genotype', 'status']
const REQUIRED_HEADERS = ['name', 'cage']

interface ParsedCsv {
    headers: string[]
    rows: Record<string, string>[]
}

function parseCsv(text: string): ParsedCsv {
    const result = Papa.parse<Record<string, string>>(text.trim(), {
        header: true,
        skipEmptyLines: true,
        transformHeader: (h) => h.trim(),
    })
    return {
        headers: result.meta.fields ?? [],
        rows: result.data,
    }
}

export function SoftMouseImportTab({ appId }: { appId: string }) {
    const [step, setStep] = useState<ImportStep>(1)
    const [rawCsv, setRawCsv] = useState('')
    const fileInputRef = useRef<HTMLInputElement>(null)
    const { toast } = useToast()

    const parsed = useMemo(() => parseCsv(rawCsv), [rawCsv])
    const { headers, rows: dataRows } = parsed
    const previewRows = dataRows.slice(0, 5)
    const validation = useMemo(() => validateCsv(headers, dataRows), [headers, dataRows])

    const handleFileUpload = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0]
        if (!file) return
        const reader = new FileReader()
        reader.onload = (e) => {
            setRawCsv((e.target?.result as string) ?? '')
        }
        reader.readAsText(file)
    }

    const handleImport = () => {
        // The backend endpoint POST /connected_apps/:appId/import is not yet available.
        // When it is, replace this with an apiFetch call using appId and the parsed rows.
        void appId
        toast({
            title: 'Import not yet available',
            description: 'The SoftMouse import endpoint is coming soon. Data has been validated and is ready.',
            variant: 'error',
        })
    }

    const handleReset = () => {
        setStep(1)
        setRawCsv('')
        if (fileInputRef.current) {
            fileInputRef.current.value = ''
        }
    }

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Upload className="h-5 w-5 text-slate-500" />
                        SoftMouse CSV Import
                    </CardTitle>
                    <CardDescription>
                        Upload an exported SoftMouse colony CSV to import animals into your investigation.
                        Required columns: <code className="text-xs">name</code>,{' '}
                        <code className="text-xs">cage</code>.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {/* Step indicator */}
                    <div className="flex items-center gap-3 text-xs text-slate-500">
                        {(['Upload', 'Preview', 'Import'] as const).map((label, idx) => (
                            <div key={label} className="flex items-center gap-1.5">
                                <span
                                    className={`flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold ${
                                        step === idx + 1
                                            ? 'bg-slate-900 text-white'
                                            : step > idx + 1
                                              ? 'bg-emerald-500 text-white'
                                              : 'bg-slate-100 text-slate-400'
                                    }`}
                                >
                                    {step > idx + 1 ? '✓' : idx + 1}
                                </span>
                                <span className={step === idx + 1 ? 'font-semibold text-slate-700' : ''}>{label}</span>
                                {idx < 2 && <span className="mx-1 text-slate-300">›</span>}
                            </div>
                        ))}
                    </div>

                    {step === 1 && (
                        <div className="space-y-4">
                            {/* Accessible file drop zone: a <label> wrapping the hidden input */}
                            <label
                                htmlFor="softmouse-csv-file"
                                className="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-line py-10 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-within:border-slate-400 focus-within:ring-2 focus-within:ring-slate-900 focus-within:ring-offset-2"
                            >
                                <FileText className="h-8 w-8 text-slate-400" aria-hidden="true" />
                                <div className="text-center">
                                    <p className="text-sm font-semibold text-slate-700">Click to upload CSV file</p>
                                    <p className="text-xs text-slate-400">or paste content below</p>
                                </div>
                                <input
                                    ref={fileInputRef}
                                    id="softmouse-csv-file"
                                    type="file"
                                    accept=".csv,text/csv"
                                    className="sr-only"
                                    onChange={handleFileUpload}
                                />
                            </label>

                            <div className="relative">
                                <div className="absolute inset-x-0 top-1/2 -translate-y-1/2 border-t border-line" />
                                <p className="relative mx-auto w-8 bg-white text-center text-xs text-slate-400">or</p>
                            </div>

                            <textarea
                                value={rawCsv}
                                onChange={(event) => setRawCsv(event.target.value)}
                                placeholder={`name,cage,strain,sex\nMouse-001,Cage-A,C57BL/6,M\nMouse-002,Cage-A,C57BL/6,F`}
                                className="min-h-32 w-full rounded-2xl border border-line p-3 font-mono text-xs"
                                aria-label="CSV content"
                            />

                            <div className="flex items-center justify-between">
                                <p className="text-xs text-slate-500">{dataRows.length > 0 ? `${dataRows.length} ${dataRows.length === 1 ? 'row' : 'rows'} detected` : 'No data yet'}</p>
                                <Button onClick={() => setStep(2)} disabled={dataRows.length === 0}>
                                    Preview data
                                </Button>
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-4">
                            {/* Validation summary */}
                            {validation.errors.length > 0 && (
                                <div className="rounded-xl border border-red-200 bg-red-50 p-4">
                                    <div className="flex items-center gap-2 text-sm font-semibold text-red-700">
                                        <XCircle className="h-4 w-4" />
                                        Validation errors
                                    </div>
                                    <ul className="mt-2 list-inside list-disc space-y-1 text-xs text-red-600">
                                        {validation.errors.map((err) => <li key={err}>{err}</li>)}
                                    </ul>
                                </div>
                            )}
                            {validation.warnings.length > 0 && (
                                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                    <div className="flex items-center gap-2 text-sm font-semibold text-amber-700">
                                        <AlertTriangle className="h-4 w-4" />
                                        Warnings
                                    </div>
                                    <ul className="mt-2 list-inside list-disc space-y-1 text-xs text-amber-600">
                                        {validation.warnings.map((w) => <li key={w}>{w}</li>)}
                                    </ul>
                                </div>
                            )}
                            {validation.valid && (
                                <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                    <div className="flex items-center gap-2 text-sm font-semibold text-emerald-700">
                                        <CheckCircle2 className="h-4 w-4" />
                                        Ready to import — {validation.rowCount} animals
                                    </div>
                                </div>
                            )}

                            {/* Column mapping summary */}
                            <div className="flex flex-wrap gap-2">
                                {headers.map((h) => {
                                    const isRequired = REQUIRED_HEADERS.includes(h.toLowerCase().trim())
                                    return (
                                        <Badge
                                            key={h}
                                            variant={isRequired ? 'default' : 'outline'}
                                        >
                                            {h}
                                            {isRequired ? ' *' : ''}
                                        </Badge>
                                    )
                                })}
                            </div>

                            {/* Preview table */}
                            <div className="overflow-x-auto rounded-2xl border border-line">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            {headers.map((h) => (
                                                <th key={h} className="px-3 py-2 text-left text-xs font-semibold text-slate-500">
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {previewRows.map((row, idx) => (
                                            <tr key={idx} className="hover:bg-slate-50/60">
                                                {headers.map((h) => (
                                                    <td key={h} className="px-3 py-2 text-xs text-slate-700">
                                                        {row[h] ?? ''}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {dataRows.length > 5 && (
                                    <p className="px-3 py-2 text-xs text-slate-400">
                                        Showing 5 of {dataRows.length} rows
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center justify-between">
                                <Button variant="outline" onClick={() => setStep(1)}>Back</Button>
                                <Button onClick={() => setStep(3)}>Continue to import</Button>
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="space-y-4">
                            <div className="rounded-2xl border border-line bg-slate-50 p-5">
                                <p className="text-sm font-semibold text-slate-800">Import summary</p>
                                <ul className="mt-3 space-y-2 text-sm text-slate-600">
                                    <li className="flex justify-between">
                                        <span>Animals to import</span>
                                        <span className="font-semibold">{dataRows.length}</span>
                                    </li>
                                    <li className="flex justify-between">
                                        <span>Columns mapped</span>
                                        <span className="font-semibold">
                                            {headers.filter((h) => SUPPORTED_FIELDS.includes(h.toLowerCase())).length} / {headers.length}
                                        </span>
                                    </li>
                                    <li className="flex justify-between">
                                        <span>Validation status</span>
                                        <span className={`font-semibold ${validation.valid ? 'text-emerald-600' : 'text-red-600'}`}>
                                            {validation.valid ? 'Passed' : 'Failed'}
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700">
                                The backend import endpoint is not yet available. Confirm will show a preview of this feature.
                            </div>

                            <div className="flex items-center justify-between">
                                <Button variant="outline" onClick={() => setStep(2)}>Back</Button>
                                <div className="flex items-center gap-2">
                                    <Button variant="outline" onClick={handleReset}>
                                        Start over
                                    </Button>
                                    <Button
                                        onClick={handleImport}
                                        disabled={!validation.valid}
                                    >
                                        Confirm import
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    )
}

