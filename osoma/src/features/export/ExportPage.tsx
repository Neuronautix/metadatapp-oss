import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Download, FileArchive, FileCheck, FileJson, FileText, Loader2 } from 'lucide-react'
import {
  getInvestigations,
  exportInvestigationAnimalsCsv,
  exportInvestigationArriveReportPdf,
  exportInvestigationEln,
  exportInvestigationFair2JsonLd,
  exportInvestigationFairReportPdf,
  exportInvestigationRoCrate,
} from '@/features/core/investigations/investigation.api.ts'
import { exportStudyEln, exportStudyFair2JsonLd, getStudies } from '@/features/core/studies/study.api.ts'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { downloadBlob } from '@/lib/download.ts'

export function ExportPage() {
  const { toast } = useToast()
  const { data: investigations } = useQuery({
    queryKey: ['investigations'],
    queryFn: getInvestigations,
  })

  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [selectedStudyId, setSelectedStudyId] = useState<string | null>(null)

  const investigationOptions = useMemo(() => investigations?.data ?? [], [investigations])

  const { data: studies } = useQuery({
    queryKey: ['export-studies', selectedId],
    queryFn: () => getStudies({ page: 1, pageSize: 100, investigation: selectedId ?? undefined }),
    enabled: Boolean(selectedId),
  })

  const studyOptions = useMemo(
    () => (studies?.data ?? []).filter((study) => (selectedId ? study.investigationId === selectedId : true)),
    [studies, selectedId],
  )

  useEffect(() => {
    if (!selectedId && investigationOptions.length) {
      setSelectedId(investigationOptions[0].id)
    }
  }, [investigationOptions, selectedId])

  useEffect(() => {
    if (!studyOptions.length) {
      setSelectedStudyId(null)
      return
    }

    if (!selectedStudyId || !studyOptions.some((study) => study.id === selectedStudyId)) {
      setSelectedStudyId(studyOptions[0].id)
    }
  }, [studyOptions, selectedStudyId])

  const makeFilename = (suffix: string) => `investigation-${selectedId ?? 'unknown'}-${suffix}`

  const roCrateMutation = useMutation({
    mutationFn: () => {
      if (!selectedId) {
        throw new Error('Select an investigation before exporting.')
      }
      return exportInvestigationRoCrate(selectedId)
    },
    onSuccess: (blob) => {
      downloadBlob(blob, makeFilename('ro-crate.zip'))
      toast({ title: 'RO-Crate export ready', description: 'Download complete.' })
    },
    onError: (error: Error) => {
      toast({ title: 'Export failed', description: error.message, variant: 'error' })
    },
  })

  const elnMutation = useMutation({
    mutationFn: () => {
      if (!selectedId) {
        throw new Error('Select an investigation before exporting.')
      }
      return exportInvestigationEln(selectedId)
    },
    onSuccess: (blob) => {
      downloadBlob(blob, makeFilename('fair3r.eln'))
      toast({ title: 'ELN export ready', description: 'Download complete.' })
    },
    onError: (error: Error) => {
      toast({ title: 'Export failed', description: error.message, variant: 'error' })
    },
  })

  const csvMutation = useMutation({
    mutationFn: () => {
      if (!selectedId) {
        throw new Error('Select an investigation before exporting.')
      }
      return exportInvestigationAnimalsCsv(selectedId)
    },
    onSuccess: (payload) => {
      const blob = new Blob([payload.csv], { type: 'text/csv' })
      downloadBlob(blob, makeFilename('animals.csv'))
      toast({ title: 'CSV export ready', description: `${payload.rowCount} animals exported.` })
    },
    onError: (error: Error) => {
      toast({ title: 'Export failed', description: error.message, variant: 'error' })
    },
  })

  const jsonLdMutation = useMutation({
    mutationFn: () => {
      if (!selectedId) {
        throw new Error('Select an investigation before exporting.')
      }
      return exportInvestigationFair2JsonLd(selectedId)
    },
    onSuccess: (blob) => {
      downloadBlob(blob, makeFilename('fair2.jsonld'))
      toast({ title: 'JSON-LD export ready', description: 'FAIR² JSON-LD download complete.' })
    },
    onError: (error: Error) => {
      toast({ title: 'Export failed', description: error.message, variant: 'error' })
    },
  })

  const studyJsonLdMutation = useMutation({
    mutationFn: () => {
      if (!selectedStudyId) {
        throw new Error('Select a study before exporting.')
      }

      return exportStudyFair2JsonLd(selectedStudyId)
    },
    onSuccess: (blob) => {
      downloadBlob(blob, `study-${selectedStudyId ?? 'unknown'}-fair2.jsonld`)
      toast({ title: 'Study JSON-LD export ready', description: 'FAIR² JSON-LD download complete.' })
    },
    onError: (error: Error) => {
      toast({ title: 'Export failed', description: error.message, variant: 'error' })
    },
  })

  const studyElnMutation = useMutation({
    mutationFn: () => {
      if (!selectedStudyId) {
        throw new Error('Select a study before exporting.')
      }
      return exportStudyEln(selectedStudyId)
    },
    onSuccess: (blob) => {
      downloadBlob(blob, `study-${selectedStudyId ?? 'unknown'}-fair3r.eln`)
      toast({ title: 'Study ELN export ready', description: 'Download complete.' })
    },
    onError: (error: Error) => {
      toast({ title: 'Export failed', description: error.message, variant: 'error' })
    },
  })

  const fairReportMutation = useMutation({
    mutationFn: () => {
      if (!selectedId) {
        throw new Error('Select an investigation before exporting.')
      }
      return exportInvestigationFairReportPdf(selectedId)
    },
    onSuccess: (blob) => {
      downloadBlob(blob, makeFilename('fair-report.pdf'))
      toast({ title: 'FAIR report ready', description: 'PDF download complete.' })
    },
    onError: (error: Error) => {
      toast({ title: 'Export failed', description: error.message, variant: 'error' })
    },
  })

  const arriveReportMutation = useMutation({
    mutationFn: () => {
      if (!selectedId) {
        throw new Error('Select an investigation before exporting.')
      }
      return exportInvestigationArriveReportPdf(selectedId)
    },
    onSuccess: (blob) => {
      downloadBlob(blob, makeFilename('arrive-report.pdf'))
      toast({ title: 'ARRIVE report ready', description: 'PDF download complete.' })
    },
    onError: (error: Error) => {
      toast({ title: 'Export failed', description: error.message, variant: 'error' })
    },
  })

  const disabled = !selectedId

  return (
    <div className="space-y-6">
      <div>
        <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Exports</p>
        <h1 className="font-display text-2xl">RO-Crate & data exports</h1>
        <p className="text-sm text-slate-500">Select an investigation and choose your preferred export format.</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Target investigation</CardTitle>
          <CardDescription>Exports will include all studies, subjects, time series, and behaviors under this investigation.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4 md:flex-row md:items-center">
          <select
            className="w-full rounded-lg border border-line bg-white p-3 text-sm md:w-96"
            value={selectedId ?? ''}
            onChange={(event) => setSelectedId(event.target.value)}
            disabled={!investigationOptions.length}
          >
            {investigationOptions.map((investigation) => (
              <option key={investigation.id} value={investigation.id}>
                {investigation.name}
              </option>
            ))}
          </select>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Optional study target</CardTitle>
          <CardDescription>Use this to export FAIR² JSON-LD for a single study under the selected investigation.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4 md:flex-row md:items-center">
          <select
            className="w-full rounded-lg border border-line bg-white p-3 text-sm md:w-96"
            value={selectedStudyId ?? ''}
            onChange={(event) => setSelectedStudyId(event.target.value)}
            disabled={!studyOptions.length}
          >
            {studyOptions.map((study) => (
              <option key={study.id} value={study.id}>
                {study.name}
              </option>
            ))}
          </select>
        </CardContent>
      </Card>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Download className="h-4 w-4" />
              RO-Crate
            </CardTitle>
            <CardDescription>Generates a full RO-Crate package with metadata and checksums.</CardDescription>
          </CardHeader>
          <CardContent>
            <Button className="w-full" disabled={disabled || roCrateMutation.isPending} onClick={() => roCrateMutation.mutate()}>
              {roCrateMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}
              Export as RO-Crate
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileArchive className="h-4 w-4" />
              ELN file
            </CardTitle>
            <CardDescription>
              Builds a spec-compliant .eln (RO-Crate) package from the FAIR3R exchange metadata, with ORCID authors, ontology
              terms, and FAIR scores.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button className="w-full" disabled={disabled || elnMutation.isPending} onClick={() => elnMutation.mutate()}>
              {elnMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <FileArchive className="mr-2 h-4 w-4" />}
              Export as ELN
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileText className="h-4 w-4" />
              CSV (animals)
            </CardTitle>
            <CardDescription>Exports the current investigation roster as CSV.</CardDescription>
          </CardHeader>
          <CardContent>
            <Button className="w-full" variant="outline" disabled={disabled || csvMutation.isPending} onClick={() => csvMutation.mutate()}>
              {csvMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <FileText className="mr-2 h-4 w-4" />}
              Export CSV
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileJson className="h-4 w-4" />
              JSON-LD (investigation)
            </CardTitle>
            <CardDescription>Downloads the FAIR² / Croissant JSON-LD document for this investigation.</CardDescription>
          </CardHeader>
          <CardContent>
            <Button className="w-full" variant="secondary" disabled={disabled || jsonLdMutation.isPending} onClick={() => jsonLdMutation.mutate()}>
              {jsonLdMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <FileJson className="mr-2 h-4 w-4" />}
              Export JSON-LD
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileJson className="h-4 w-4" />
              JSON-LD (study)
            </CardTitle>
            <CardDescription>Downloads the FAIR² / Croissant JSON-LD document for one study.</CardDescription>
          </CardHeader>
          <CardContent>
            <Button
              className="w-full"
              variant="secondary"
              disabled={!selectedStudyId || studyJsonLdMutation.isPending}
              onClick={() => studyJsonLdMutation.mutate()}
            >
              {studyJsonLdMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <FileJson className="mr-2 h-4 w-4" />}
              Export Study JSON-LD
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileArchive className="h-4 w-4" />
              ELN file (study)
            </CardTitle>
            <CardDescription>Builds a spec-compliant .eln (RO-Crate) package for one study from its FAIR3R exchange metadata.</CardDescription>
          </CardHeader>
          <CardContent>
            <Button
              className="w-full"
              disabled={!selectedStudyId || studyElnMutation.isPending}
              onClick={() => studyElnMutation.mutate()}
            >
              {studyElnMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <FileArchive className="mr-2 h-4 w-4" />}
              Export Study ELN
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileCheck className="h-4 w-4" />
              FAIR report
            </CardTitle>
            <CardDescription>Downloads a PDF assessment with FAIR pillar scores and missing metadata notes.</CardDescription>
          </CardHeader>
          <CardContent>
            <Button className="w-full" variant="outline" disabled={disabled || fairReportMutation.isPending} onClick={() => fairReportMutation.mutate()}>
              {fairReportMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <FileCheck className="mr-2 h-4 w-4" />}
              Export FAIR PDF
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileCheck className="h-4 w-4" />
              ARRIVE helper
            </CardTitle>
            <CardDescription>Generates an ARRIVE 2.0-style PDF helper report for animal experiment documentation.</CardDescription>
          </CardHeader>
          <CardContent>
            <Button className="w-full" variant="outline" disabled={disabled || arriveReportMutation.isPending} onClick={() => arriveReportMutation.mutate()}>
              {arriveReportMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <FileCheck className="mr-2 h-4 w-4" />}
              Export ARRIVE PDF
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
