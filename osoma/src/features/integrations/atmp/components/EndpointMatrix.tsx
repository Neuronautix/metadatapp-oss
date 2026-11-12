import { useState } from 'react'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import type { EndpointMatrixEntry } from '@/domain/atmp/atmp.types.ts'
import { EndpointDetailsDrawer } from './EndpointDetailsDrawer.tsx'

const statusVariant = (status: EndpointMatrixEntry['status']) => {
  if (status === 'Complete') return 'success'
  if (status === 'In Progress') return 'warning'
  if (status === 'Blocked') return 'critical'
  return 'outline'
}

export function EndpointMatrix({ entries }: { entries: EndpointMatrixEntry[] }) {
  const [selected, setSelected] = useState<EndpointMatrixEntry | null>(null)

  return (
    <>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Endpoint Matrix</CardTitle>
          <Badge variant="outline">{entries.length} endpoints</Badge>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Endpoint</TableHead>
                <TableHead>Category</TableHead>
                <TableHead>Timepoint</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Owner</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {entries.map((entry) => (
                <TableRow
                  key={entry.id}
                  className="cursor-pointer"
                  onClick={() => setSelected(entry)}
                >
                  <TableCell className="font-medium text-slate-900">{entry.name}</TableCell>
                  <TableCell>{entry.category}</TableCell>
                  <TableCell>{entry.timepoint}</TableCell>
                  <TableCell>
                    <Badge variant={statusVariant(entry.status)}>{entry.status}</Badge>
                  </TableCell>
                  <TableCell>{entry.owner}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
      <EndpointDetailsDrawer entry={selected} open={Boolean(selected)} onClose={() => setSelected(null)} />
    </>
  )
}
