import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { Feature } from '@/feature-flags/Feature.tsx'
import type { AtmpStudy } from '@/domain/atmp/atmp.types.ts'

const HIGH_VCN_THRESHOLD = 10
const LOW_VIABILITY_THRESHOLD = 70

export function StudyOverviewCard({ study }: { study: AtmpStudy }) {
  const { toast } = useToast()
  const vcnHigh = study.productCharacterization.vectorCopyNumber >= HIGH_VCN_THRESHOLD
  const viabilityLow = study.productCharacterization.viabilityPercent < LOW_VIABILITY_THRESHOLD

  const handleCopy = async (label: string, value: string) => {
    try {
      await navigator.clipboard.writeText(value)
      toast({ title: `${label} copied`, description: value })
    } catch {
      toast({ title: 'Copy failed', description: 'Clipboard permission denied.' })
    }
  }

  return (
    <Card>
      <CardHeader className="space-y-3">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">ATMP Study Overview</p>
            <CardTitle className="mt-2 text-2xl">{study.title}</CardTitle>
          </div>
          <div className="flex flex-wrap gap-2">
            <Badge variant="outline">{study.atmpCategory}</Badge>
            <Badge variant="outline">{study.regulatoryStatus}</Badge>
          </div>
        </div>
        <Feature flag="atmp.enabled">
          <div className="flex flex-wrap gap-2">
            {vcnHigh ? <Badge variant="warning">High VCN</Badge> : null}
            {viabilityLow ? <Badge variant="critical">Viability low</Badge> : null}
          </div>
        </Feature>
      </CardHeader>
      <CardContent className="grid gap-6 text-sm text-slate-600 md:grid-cols-2">
        <div className="space-y-3">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Investigation ID</p>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => handleCopy('Investigation ID', study.investigationID)}
            >
              {study.investigationID}
            </Button>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Chain of Identity</p>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => handleCopy('COI', study.chainOfIdentityID)}
            >
              {study.chainOfIdentityID}
            </Button>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Species</p>
            <p className="font-semibold text-slate-800">{study.species}</p>
            <p>{study.modelSummary}</p>
          </div>
        </div>
        <div className="space-y-3">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Administration</p>
            <p className="font-semibold text-slate-800">{study.administrationRoute}</p>
            <p>
              {study.dosage.value} {study.dosage.unit}
              {study.dosage.frequency ? ` · ${study.dosage.frequency}` : ''}
            </p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Product</p>
            <p className="font-semibold text-slate-800">{study.productCharacterization.modality}</p>
            <p>
              Vector {study.productCharacterization.vectorType} · VCN{' '}
              {study.productCharacterization.vectorCopyNumber}
            </p>
            <p>Viability {study.productCharacterization.viabilityPercent}%</p>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
