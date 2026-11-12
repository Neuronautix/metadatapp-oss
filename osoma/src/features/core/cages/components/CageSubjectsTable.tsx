import { Link } from 'react-router-dom'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import type { AnimalSubject } from '@/features/integrations/tecniplast/tecniplast.types.ts'

export function CageSubjectsTable({ subjects }: { subjects: AnimalSubject[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Subjects in cage</CardTitle>
      </CardHeader>
      <CardContent>
        {subjects.length ? (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Subject</TableHead>
                <TableHead>Species</TableHead>
                <TableHead>Strain</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {subjects.map((subject) => (
                <TableRow key={subject.id}>
                  <TableCell>
                    <Link to={`/tp/subjects/${subject.id}`} className="font-medium text-slate-900">
                      {subject.label}
                    </Link>
                    <p className="text-xs text-slate-500">{subject.id}</p>
                  </TableCell>
                  <TableCell>{subject.species}</TableCell>
                  <TableCell>{subject.strain}</TableCell>
                  <TableCell>{subject.status}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        ) : (
          <p className="text-sm text-slate-500">No subjects assigned to this cage.</p>
        )}
      </CardContent>
    </Card>
  )
}
