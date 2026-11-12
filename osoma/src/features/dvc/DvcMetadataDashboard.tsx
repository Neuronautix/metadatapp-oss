import { useEffect, useMemo, useState } from 'react'
import { Activity, BarChart2, FlaskConical, Users, ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-react'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import type { FilterCriteria, AnimalRecord } from '@/domain/dvc/metadataMatcher.ts'
import { filterAnimals } from '@/domain/dvc/metadataMatcher.ts'
import { computeVcgForFilter } from '@/domain/dvc/vcgEngine.ts'
import { computeCircadianStats } from '@/domain/dvc/circadianModel.ts'
import { AnimalFilterPanel } from './AnimalFilterPanel.tsx'
import { CircadianChart } from './CircadianChart.tsx'
import { SinusoidMetadataPanel } from './SinusoidMetadataPanel.tsx'
import { VCGComparisonChart } from './VCGComparisonChart.tsx'
import { apiFetch } from '@/lib/api.ts'

// ---- Data loading -------------------------------------------------------

type RawAnimalRecord = {
  animalId: string
  metadata: Record<string, unknown>
  circadianParameters: {
    amplitude: number
    phase: number
    baseline: number
    noise: number
  }
}

type RawCage = {
  cageId: string
  system: string
  modalities?: string[]
  location: { facility: string; room: string; rack: string }
  environment?: {
    temperature?: number | null
    humidity?: number | null
    lightIntensity?: number | null
    noise?: number | null
    lightCycle?: string | null
    humanMovementWorkday?: number | null
  }
  animals: RawAnimalRecord[]
}

type RawHcmSystem = {
  id: string
  system: string
  modalities: string[]
  sourceAppCode: string
  cageCount: number
}

const normalizeAnimals = (cages: RawCage[]): AnimalRecord[] => {
  const records: AnimalRecord[] = []
  for (const cage of cages) {
    for (const animal of cage.animals) {
      const m = animal.metadata as Record<string, unknown>
      records.push({
        animalId: animal.animalId,
        cageId: cage.cageId,
        metadata: {
          animalId: animal.animalId,
          cageId: cage.cageId,
          species: (m.species as string) ?? 'mouse',
          strain: (m.strain as string) ?? '',
          sex: ((m.sex as string) === 'female' ? 'female' : 'male') as 'male' | 'female',
          ageWeeks: (m.ageWeeks as number) ?? 0,
          genotype: (m.genotype as string) ?? '',
          supplier: (m.supplier as string) ?? '',
          sanitaryStatus: (m.sanitaryStatus as string) ?? '',
          project: (m.project as string) ?? '',
          protocol: (m.protocol as string) ?? '',
          treatment: (m.treatment as string) ?? '',
          dose: (m.dose as string) ?? '',
          administrationRoute: (m.administrationRoute as string) ?? '',
          facility: (m.facility as string) ?? cage.location.facility,
          room: (m.room as string) ?? cage.location.room,
          rack: (m.rack as string) ?? cage.location.rack,
          beddingType: (m.beddingType as string) ?? '',
          dietType: (m.dietType as string) ?? '',
          waterSystem: (m.waterSystem as string) ?? '',
          temperatureMean: (m.temperatureMean as number) ?? 22,
          humidityMean: (m.humidityMean as number) ?? 55,
          lightIntensityMean: (m.lightIntensityMean as number) ?? cage.environment?.lightIntensity ?? 0,
          lightCycle: (m.lightCycle as string) ?? cage.environment?.lightCycle ?? '12:12',
          noiseLevel: (m.noiseLevel as string) ?? 'low',
          noiseMean: (m.noiseMean as number) ?? cage.environment?.noise ?? 0,
          humanMovementWorkday: (m.humanMovementWorkday as number) ?? cage.environment?.humanMovementWorkday ?? 0,
          dvcSystem: (m.dvcSystem as string) ?? '',
          firmwareVersion: (m.firmwareVersion as string) ?? '',
          analyticsVersion: (m.analyticsVersion as string) ?? '',
          sourceApp: (m.sourceApp as string) ?? 'metadatapp',
          modalities: Array.isArray(m.modalities) ? (m.modalities as string[]) : cage.modalities,
        },
        circadianParameters: animal.circadianParameters,
      })
    }
  }
  return records
}

// ---- Sub-components -----------------------------------------------------

const StatCard = ({
  icon: Icon,
  label,
  value,
  sub,
}: {
  icon: React.ElementType
  label: string
  value: string | number
  sub?: string
}) => (
  <Card className="flex flex-col gap-1 p-4">
    <div className="flex items-center gap-2 text-xs text-muted-foreground">
      <Icon className="h-3.5 w-3.5" />
      {label}
    </div>
    <div className="text-2xl font-bold">{value}</div>
    {sub && <div className="text-xs text-muted-foreground">{sub}</div>}
  </Card>
)

type SortKey = 'ageWeeks' | 'temperatureMean' | 'humidityMean' | 'lightIntensityMean' | 'noiseMean' | 'humanMovementWorkday' | 'amplitude' | 'phase'
type SortDir = 'asc' | 'desc'

const getSortValue = (animal: AnimalRecord, key: SortKey): number => {
  if (key === 'amplitude') return animal.circadianParameters.amplitude
  if (key === 'phase') return animal.circadianParameters.phase
  return (animal.metadata[key] as number) ?? 0
}

const SORTABLE_HEADERS: { label: string; key: SortKey | null }[] = [
  { label: 'Animal ID', key: null },
  { label: 'HCM system', key: null },
  { label: 'Strain', key: null },
  { label: 'Sex', key: null },
  { label: 'Age (wk)', key: 'ageWeeks' },
  { label: 'Temp', key: 'temperatureMean' },
  { label: 'Humidity', key: 'humidityMean' },
  { label: 'Light', key: 'lightIntensityMean' },
  { label: 'Noise', key: 'noiseMean' },
  { label: 'Human move', key: 'humanMovementWorkday' },
  { label: 'Amplitude', key: 'amplitude' },
  { label: 'Phase', key: 'phase' },
]

const PopulationTable = ({ animals }: { animals: AnimalRecord[] }) => {
  const [sortKey, setSortKey] = useState<SortKey | null>(null)
  const [sortDir, setSortDir] = useState<SortDir>('asc')

  const sorted = useMemo(() => {
    if (!sortKey) return animals
    return [...animals].sort((a, b) => {
      const diff = getSortValue(a, sortKey) - getSortValue(b, sortKey)
      return sortDir === 'asc' ? diff : -diff
    })
  }, [animals, sortKey, sortDir])

  const visible = sorted.slice(0, 50)

  const handleSort = (key: SortKey) => {
    if (sortKey === key) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'))
    } else {
      setSortKey(key)
      setSortDir('asc')
    }
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-border">
      <table className="w-full text-xs">
        <thead className="bg-muted/50">
          <tr>
            {SORTABLE_HEADERS.map(({ label, key }) =>
              key ? (
                <th
                  key={label}
                  className="cursor-pointer select-none px-3 py-2 text-left font-semibold text-muted-foreground hover:text-foreground"
                  onClick={() => handleSort(key)}
                >
                  <span className="inline-flex items-center gap-1">
                    {label}
                    {sortKey === key ? (
                      sortDir === 'asc' ? (
                        <ChevronUp className="h-3 w-3" />
                      ) : (
                        <ChevronDown className="h-3 w-3" />
                      )
                    ) : (
                      <ChevronsUpDown className="h-3 w-3 opacity-40" />
                    )}
                  </span>
                </th>
              ) : (
                <th key={label} className="px-3 py-2 text-left font-semibold text-muted-foreground">
                  {label}
                </th>
              )
            )}
          </tr>
        </thead>
        <tbody>
          {visible.map((animal, i) => (
            <tr key={animal.animalId} className={i % 2 === 0 ? 'bg-background' : 'bg-muted/20'}>
              <td className="px-3 py-1.5 font-mono text-[11px]">{animal.animalId}</td>
              <td className="px-3 py-1.5">{animal.metadata.dvcSystem}</td>
              <td className="px-3 py-1.5">{animal.metadata.strain}</td>
              <td className="px-3 py-1.5">{animal.metadata.sex}</td>
              <td className="px-3 py-1.5">{animal.metadata.ageWeeks}</td>
              <td className="px-3 py-1.5">{animal.metadata.temperatureMean.toFixed(1)}</td>
              <td className="px-3 py-1.5">{animal.metadata.humidityMean.toFixed(1)}</td>
              <td className="px-3 py-1.5">{(animal.metadata.lightIntensityMean ?? 0).toFixed(0)}</td>
              <td className="px-3 py-1.5">{(animal.metadata.noiseMean ?? 0).toFixed(1)}</td>
              <td className="px-3 py-1.5">{(animal.metadata.humanMovementWorkday ?? 0).toFixed(1)}</td>
              <td className="px-3 py-1.5">{animal.circadianParameters.amplitude.toFixed(1)}</td>
              <td className="px-3 py-1.5">{animal.circadianParameters.phase.toFixed(2)}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {animals.length > 50 && (
        <div className="border-t border-border px-4 py-2 text-xs text-muted-foreground">
          Showing 50 of {animals.length} animals
        </div>
      )}
    </div>
  )
}

// ---- Main component -----------------------------------------------------

export function DvcMetadataDashboard({ experimentId, mode = 'dvc' }: { experimentId?: string; mode?: 'dvc' | 'hcm' }) {
  const [allAnimals, setAllAnimals] = useState<AnimalRecord[]>([])
  const [systems, setSystems] = useState<RawHcmSystem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [criteria, setCriteria] = useState<FilterCriteria>({})

  useEffect(() => {
    setLoading(true)
    setError(null)

    const loadData = mode === 'hcm'
      ? apiFetch<{ cages: RawCage[]; systems: RawHcmSystem[] }>('/api/hcm/systems/summary')
          .catch(() => fetch('/mock/hcm/systemComparison.json').then((r) => r.json()))
      : experimentId
        ? apiFetch<{ cages: RawCage[]; systems?: RawHcmSystem[] }>(`/api/dvc/experiments/${experimentId}/summary`)
        : fetch('/mock/dvc/cageMetadata.json').then((r) => r.json())

    loadData
      .then((data: { cages: RawCage[]; systems?: RawHcmSystem[] }) => {
        setSystems(data.systems ?? [])
        setAllAnimals(normalizeAnimals(data.cages))
        setLoading(false)
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load HCM data.')
        setLoading(false)
      })
  }, [experimentId, mode])

  const filteredAnimals = useMemo(() => filterAnimals(allAnimals, criteria), [allAnimals, criteria])

  const vcgGroup = useMemo(() => computeVcgForFilter(allAnimals, criteria), [allAnimals, criteria])

  const stats = useMemo(
    () => computeCircadianStats(filteredAnimals.map((a) => a.circadianParameters)),
    [filteredAnimals]
  )

  // Limit individual curves shown for performance (Recharts renders become costly above ~10 series)
  const MAX_DISPLAYED_CURVES = 8
  const displayAnimals = filteredAnimals.slice(0, MAX_DISPLAYED_CURVES)

  if (loading) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-48 w-full" />
        <Skeleton className="h-80 w-full" />
      </div>
    )
  }

  if (error) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <PageHeader
          title={mode === 'hcm' ? 'HCM System Comparison' : 'DVC Analytics & Metadata Fusion'}
          subtitle={mode === 'hcm' ? 'Compare HomeCage Monitoring systems through extracted sinusoid metadata' : 'Circadian rhythm analysis with metadata-driven Virtual Control Group'}
        />
        <Card>
          <CardContent className="py-8 text-center text-sm text-muted-foreground">
            Unable to load DVC data: {error}
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-6 p-4">
      <PageHeader
        title={mode === 'hcm' ? 'HCM System Comparison' : 'DVC Analytics & Metadata Fusion'}
        subtitle={
          mode === 'hcm'
            ? 'Select HCM metadata from DVC, Olden Labs, Live Mouse Tracker, and Open-BEATBox cohorts to build groups, statistics, and scientific outputs'
            : experimentId
            ? 'Circadian rhythm analysis - live study data'
            : 'Circadian rhythm analysis with metadata-driven Virtual Control Group'
        }
      />

      {allAnimals.length === 0 && (
        <Card>
          <CardContent className="py-8 text-center text-sm text-muted-foreground">
            No HCM activity data found for this study. Import cage time-series data first.
          </CardContent>
        </Card>
      )}

      {/* Stats bar */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <StatCard icon={Users} label="Total population" value={allAnimals.length} sub="loaded" />
        <StatCard icon={FlaskConical} label="Filtered animals" value={filteredAnimals.length} sub={`of ${allAnimals.length}`} />
        <StatCard icon={Activity} label="Mean amplitude" value={stats.count > 0 ? stats.meanAmplitude.toFixed(1) : '—'} sub="activity counts/hour" />
        <StatCard icon={BarChart2} label={mode === 'hcm' ? 'HCM systems' : 'VCG matches'} value={mode === 'hcm' ? systems.length || new Set(allAnimals.map((animal) => animal.metadata.dvcSystem)).size : vcgGroup.matches.length} sub={mode === 'hcm' ? 'with sinusoid data' : '≥70% similarity'} />
      </div>

      {mode === 'hcm' && systems.length > 0 && (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          {systems.map((system) => (
            <Card key={system.id} className="p-4">
              <div className="text-sm font-semibold">{system.system}</div>
              <div className="mt-1 text-xs text-muted-foreground">{system.cageCount} cages · {system.sourceAppCode}</div>
              <div className="mt-3 flex flex-wrap gap-1">
                {system.modalities.map((modality) => (
                  <Badge key={modality} variant="outline" className="text-[10px]">{modality}</Badge>
                ))}
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Filters + Table row */}
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-[320px_1fr]">
        <AnimalFilterPanel
          criteria={criteria}
          onChange={setCriteria}
          systems={systems.length ? systems.map((system) => system.system) : Array.from(new Set(allAnimals.map((animal) => animal.metadata.dvcSystem))).filter(Boolean)}
        />

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Population Table</CardTitle>
            <CardDescription>Animals matching current filters</CardDescription>
          </CardHeader>
          <CardContent className="p-0 pb-3">
            {filteredAnimals.length === 0 ? (
              <div className="px-4 py-6 text-center text-sm text-muted-foreground">
                No animals match the current filters.
              </div>
            ) : (
              <PopulationTable animals={filteredAnimals} />
            )}
          </CardContent>
        </Card>
      </div>

      {/* Circadian chart */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Circadian Activity — Individual Curves</CardTitle>
          <CardDescription>
            {displayAnimals.length < filteredAnimals.length
              ? `Showing ${displayAnimals.length} of ${filteredAnimals.length} animals`
              : `${filteredAnimals.length} animal${filteredAnimals.length !== 1 ? 's' : ''} displayed`}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {displayAnimals.length === 0 ? (
            <div className="flex h-40 items-center justify-center text-sm text-muted-foreground">
              Apply filters to view circadian curves
            </div>
          ) : (
            <CircadianChart
              params={displayAnimals.map((a) => a.circadianParameters)}
              labels={displayAnimals.map((a) => a.animalId)}
            />
          )}
        </CardContent>
      </Card>

      {/* Sinusoid metadata features */}
      <SinusoidMetadataPanel animals={filteredAnimals} />

      {/* VCG comparison */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Virtual Control Group Comparison</CardTitle>
          <CardDescription>
            {mode === 'hcm'
              ? 'Selected cohort mean sinusoid vs metadata-matched comparator curve'
              : `Target cohort mean sinusoid vs matched VCG curve (${vcgGroup.matches.length} controls)`}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {filteredAnimals.length === 0 ? (
            <div className="flex h-40 items-center justify-center text-sm text-muted-foreground">
              Apply filters to compute VCG
            </div>
          ) : (
            <VCGComparisonChart vcgGroup={vcgGroup} />
          )}
        </CardContent>
      </Card>

      {/* Statistics panel */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Statistics & Scientific Outputs</CardTitle>
          <CardDescription>Circadian parameter distribution for filtered population</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            {[
              { label: 'Population N', value: stats.count },
              { label: 'Mean Amplitude', value: stats.meanAmplitude.toFixed(2) },
              { label: 'Amplitude σ', value: stats.stdAmplitude.toFixed(2) },
              { label: 'Mean Phase (rad)', value: stats.meanPhase.toFixed(3) },
              { label: 'Phase σ', value: stats.stdPhase.toFixed(3) },
              { label: 'Mean Baseline', value: stats.meanBaseline.toFixed(2) },
            ].map((item) => (
              <div key={item.label} className="flex flex-col gap-1 rounded-lg border border-border p-3">
                <div className="text-xs text-muted-foreground">{item.label}</div>
                <div className="text-lg font-semibold">{stats.count > 0 ? item.value : '—'}</div>
              </div>
            ))}
          </div>
          {mode === 'hcm' && (
            <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
              {[
                ['Group statistics', 'Amplitude, mesor, phase, and peak-hour tables by selected HCM system.'],
                ['Scientific report', 'Methods-ready comparison of system modalities and MIROsine outputs.'],
                ['FAIR export', 'Reusable cohort table with source app, cage, animal, and sinusoid provenance.'],
              ].map(([label, text]) => (
                <div key={label} className="rounded-lg border border-border p-3">
                  <div className="text-sm font-semibold">{label}</div>
                  <div className="mt-1 text-xs text-muted-foreground">{text}</div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
