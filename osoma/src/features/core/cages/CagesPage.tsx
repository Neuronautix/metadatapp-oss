import { Link } from 'react-router-dom'
import { Search } from 'lucide-react'
import { getCages } from './cage.api.ts'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { TableCell, TableHead, TableRow } from '@/components/ui/table.tsx'
import { DataGrid } from '@/components/DataGrid.tsx'

export function CagesPage() {
  return (
    <DataGrid
      title="Cages"
      subtitle="Fast access to cage-level context, activity, and welfare signals."
      queryKey={['cages']}
      queryFn={getCages}
      initialFilters={{ search: '' }}
      actions={
        <Button asChild>
          <Link to="/metadata/cages/new">New cage</Link>
        </Button>
      }
      renderFilters={(filters, setFilters) => (
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex w-full items-center gap-2 rounded-full border border-line bg-surface px-3 py-2 text-sm text-slate-500 md:max-w-xs">
            <Search className="h-4 w-4" />
            <Input
              value={filters.search}
              onChange={(e) => setFilters({ search: e.target.value })}
              placeholder="Search cage, room, rack"
              className="h-7 border-0 bg-transparent p-0 text-sm focus-visible:ring-0"
            />
          </div>
        </div>
      )}
      renderHeader={() => (
        <TableRow>
          <TableHead>Cage</TableHead>
          <TableHead>Room</TableHead>
          <TableHead>Rack</TableHead>
          <TableHead>Bedding</TableHead>
          <TableHead>Sanitary</TableHead>
          <TableHead>Last seen</TableHead>
          <TableHead>Alerts</TableHead>
        </TableRow>
      )}
      renderRow={(cage) => (
        <TableRow key={cage.id}>
          <TableCell className="font-medium text-slate-900">
            <Link to={`/cages/${cage.id}`}>{cage.label}</Link>
            <p className="text-xs text-slate-500">{cage.id}</p>
          </TableCell>
          <TableCell>{cage.room}</TableCell>
          <TableCell>{cage.rack}</TableCell>
          <TableCell>{cage.bedding}</TableCell>
          <TableCell>{cage.sanitary}</TableCell>
          <TableCell>{new Date(cage.lastSeen).toLocaleString()}</TableCell>
          <TableCell>{cage.alerts}</TableCell>
        </TableRow>
      )}
      emptyState={{
        title: 'No cages',
        description: 'No cages match the current search.',
      }}
    />
  )
}
