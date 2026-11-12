import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { getAtmpStudies, type AtmpStudyFilters } from './atmp.api.ts'
import { atmpCategories } from '@/domain/atmp/atmp.enums.ts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { StatCard } from '@/components/StatCard.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Feature } from '@/feature-flags/Feature.tsx'
import { formatDate } from '@/lib/format.ts'

export function AtmpStudiesPage() {
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [species, setSpecies] = useState('')
  const queryClient = useQueryClient()

  const filters = useMemo<AtmpStudyFilters>(
    () => ({ search, atmpCategory: category, species }),
    [search, category, species]
  )

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['atmp-studies', filters],
    queryFn: () => getAtmpStudies(filters),
  })

  const studies = data?.data ?? []
  const speciesOptions = useMemo(() => {
    return Array.from(new Set(studies.map((study) => study.species))).sort()
  }, [studies])

  const total = studies.length
  const highRisk = studies.filter(
    (study) => study.productCharacterization.vectorCopyNumber >= 10 || study.productCharacterization.viabilityPercent < 70
  ).length
  const glpCount = studies.filter((study) => study.regulatoryStatus === 'GLP-Pivotal').length

  return (
    <div className="space-y-6">
      <PageHeader
        title="ATMP Preclinical Studies"
        subtitle="Track ATMP characterization, model design, and chain-of-identity readiness."
        actions={
          <Feature flag="atmp.enabled">
            <Button asChild>
              <Link to="/atmp/new">New ATMP study</Link>
            </Button>
          </Feature>
        }
      />

      <div className="grid gap-4 md:grid-cols-3">
        <StatCard title="Studies" value={`${total}`} helper="Active ATMP programs" />
        <StatCard title="GLP pivotal" value={`${glpCount}`} helper="Regulatory focus" />
        <StatCard title="Edge cases" value={`${highRisk}`} helper="VCN / viability risks" />
      </div>

      <Card className="space-y-6">
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex w-full items-center gap-2 rounded-full border border-line bg-surface px-3 py-2 text-sm text-slate-500 md:max-w-xs">
            <Search className="h-4 w-4" />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search investigation, COI, study title"
              className="h-7 border-0 bg-transparent p-0 text-sm focus-visible:ring-0"
            />
          </div>
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={category}
            onChange={(event) => setCategory(event.target.value)}
          >
            <option value="">All categories</option>
            {atmpCategories.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={species}
            onChange={(event) => setSpecies(event.target.value)}
          >
            <option value="">All species</option>
            {speciesOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
          <Button
            variant="outline"
            onClick={() => {
              setSearch('')
              setCategory('')
              setSpecies('')
            }}
          >
            Reset filters
          </Button>
        </div>

        {isLoading ? (
          <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, index) => (
              <Skeleton key={index} className="h-12 w-full" />
            ))}
          </div>
        ) : isError ? (
          <EmptyState
            title="ATMP studies unavailable"
            description={(error as Error).message}
            actionLabel="Retry"
            onAction={() => queryClient.invalidateQueries({ queryKey: ['atmp-studies'] })}
          />
        ) : studies.length === 0 ? (
          <EmptyState
            title="No ATMP studies"
            description="Adjust filters or create a new ATMP study."
          />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Investigation</TableHead>
                <TableHead>Study</TableHead>
                <TableHead>Category</TableHead>
                <TableHead>Species</TableHead>
                <TableHead>Route</TableHead>
                <TableHead>Regulatory</TableHead>
                <TableHead>Updated</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {studies.map((study) => (
                <TableRow key={study.investigationID}>
                  <TableCell className="font-medium text-slate-900">
                    <Link to={`/atmp/${study.investigationID}`}>{study.investigationID}</Link>
                  </TableCell>
                  <TableCell>{study.title}</TableCell>
                  <TableCell>{study.atmpCategory}</TableCell>
                  <TableCell>{study.species}</TableCell>
                  <TableCell>{study.administrationRoute}</TableCell>
                  <TableCell>{study.regulatoryStatus}</TableCell>
                  <TableCell>{formatDate(study.updatedAt)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>
    </div>
  )
}
