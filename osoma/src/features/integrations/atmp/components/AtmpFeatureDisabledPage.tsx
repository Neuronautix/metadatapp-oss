import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'

export function AtmpFeatureDisabledPage({ mode = 'module' }: { mode?: 'module' | 'form' }) {
  const title = mode === 'form' ? 'ATMP form disabled' : 'ATMP module disabled'
  const description =
    mode === 'form'
      ? 'Create/edit workflows are hidden for this audience preset.'
      : 'ATMP studies are not visible for this audience preset.'

  return (
    <div className="space-y-6">
      <PageHeader
        title={title}
        subtitle="Feature flags keep production safe while we iterate fast."
      />
      <Card>
        <CardHeader>
          <CardTitle>{description}</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-wrap items-center gap-3 text-sm text-slate-500">
          <p>Switch presets or toggle flags in Feature Flag Studio to unlock this view.</p>
          <Button variant="outline" asChild>
            <Link to="/admin/feature-flags">Open Feature Flag Studio</Link>
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}
