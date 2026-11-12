import type { CircadianTrace } from '@/domain/dvc/dvc.circadian.ts'
import { Circadian24hChart } from '@/features/integrations/tecniplast/dvc-observatory/Circadian24hChart.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'

export function CircadianMeanCompareChart({
  target,
  mean,
  stdBand,
}: {
  target: CircadianTrace
  mean: CircadianTrace
  stdBand?: number[] | null
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Circadian 24h mean compare</CardTitle>
        <CardDescription>Target trace vs VCG mean across selected controls.</CardDescription>
      </CardHeader>
      <CardContent>
        <Circadian24hChart title="Target vs VCG mean" target={target} mean={mean} stdBand={stdBand} />
      </CardContent>
    </Card>
  )
}
