import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'

export function DvcFeatureDisabledPage({
  mode = 'module',
}: {
  mode?: 'module' | 'observatory' | 'nam'
}) {
  const title =
    mode === 'observatory'
      ? 'DVC Observatory disabled'
      : mode === 'nam'
        ? 'NAM-VCG disabled'
        : 'DVC module disabled'
  const description =
    mode === 'observatory'
      ? 'The DVC Observatory cockpit is hidden for this audience preset.'
      : mode === 'nam'
        ? 'NAM-VCG workbench and FAIR navigator are hidden for this audience preset.'
        : 'DVC activity explorer is not visible for this audience preset.'

  return (
    <div className="space-y-6">
      <PageHeader
        title={title}
        subtitle="Feature flags keep DVC features safe while we iterate."
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
