import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Search } from 'lucide-react'
import { getSubjects } from './subject.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { TableCell, TableHead, TableRow } from '@/components/ui/table.tsx'
import { formatDate } from '@/lib/format.ts'
import { exportCsv } from '@/lib/export.ts'
import { DataGrid } from '@/components/DataGrid.tsx'

const speciesOptions = ['human', 'mouse', 'rat', 'arabidopsis', 'environment'] as const
const consentOptions = ['granted', 'restricted', 'withdrawn'] as const

export function SubjectsPage() {
  const [dataForExport, setDataForExport] = useState<any[]>([])

  const consentVariant = (value: string) => {
    if (value === 'granted') return 'success'
    if (value === 'restricted') return 'warning'
    return 'outline'
  }

  return (
    <DataGrid
      title="Subjects"
      subtitle="Registered participants with consent controls and cohort context."
      queryKey={['subjects']}
      queryFn={async (filters) => {
        const res = await getSubjects(filters)
        setDataForExport(res.data)
        return res
      }}
      initialFilters={{ search: '', species: '', consent: '' }}
      actions={
        <>
          <Button asChild>
            <Link to="/metadata/subjects/new">New subject</Link>
          </Button>
          <Button
            variant="outline"
            onClick={() => exportCsv(dataForExport, 'subjects')}
            disabled={!dataForExport.length}
          >
            Export CSV
          </Button>
        </>
      }
      renderFilters={(filters, setFilters, resetFilters) => (
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex w-full items-center gap-2 rounded-full border border-line bg-surface px-3 py-2 text-sm text-slate-500 md:max-w-xs">
            <Search className="h-4 w-4" />
            <Input
              value={filters.search}
              onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              placeholder="Search by subject, cohort"
              className="h-7 border-0 bg-transparent p-0 text-sm focus-visible:ring-0"
            />
          </div>
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={filters.species}
            onChange={(e) => setFilters({ ...filters, species: e.target.value })}
          >
            <option value="">All species</option>
            {speciesOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={filters.consent}
            onChange={(e) => setFilters({ ...filters, consent: e.target.value })}
          >
            <option value="">All consent</option>
            {consentOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
          <Button variant="ghost" size="sm" onClick={resetFilters}>
            Reset filters
          </Button>
        </div>
      )}
      renderHeader={() => (
        <TableRow>
          <TableHead>Subject</TableHead>
          <TableHead>Species</TableHead>
          <TableHead>Cohort</TableHead>
          <TableHead>Cage</TableHead>
          <TableHead>Consent</TableHead>
          <TableHead>Status</TableHead>
          <TableHead>Updated</TableHead>
        </TableRow>
      )}
      renderRow={(subject) => (
        <TableRow key={subject.id}>
          <TableCell>
            <Link to={`/subjects/${subject.id}`} className="font-medium text-slate-900">
              {subject.label}
            </Link>
            <p className="text-xs text-slate-500">{subject.id}</p>
          </TableCell>
          <TableCell className="capitalize">{subject.species}</TableCell>
          <TableCell>{subject.cohort}</TableCell>
          <TableCell>
            {subject.cageId ? (
              <>
                <Link to={`/cages/${subject.cageId}`} className="font-medium text-slate-900">
                  {subject.cageLabel ?? subject.cageId}
                </Link>
                {subject.cageRoom || subject.cageRack ? (
                  <p className="text-xs text-slate-500">
                    {subject.cageRoom ?? 'Unknown room'} · {subject.cageRack ?? 'Unknown rack'}
                  </p>
                ) : null}
              </>
            ) : (
              <span className="text-xs text-slate-400">Unassigned</span>
            )}
          </TableCell>
          <TableCell>
            <Badge variant={consentVariant(subject.consent)}>{subject.consent}</Badge>
          </TableCell>
          <TableCell>{subject.status}</TableCell>
          <TableCell>{formatDate(subject.updatedAt)}</TableCell>
        </TableRow>
      )}
      emptyState={{
        title: 'No subjects found',
        description: 'Adjust filters to locate participants.',
      }}
    />
  )
}
