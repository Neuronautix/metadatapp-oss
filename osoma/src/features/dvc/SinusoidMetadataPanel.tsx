import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import type { AnimalRecord } from '@/domain/dvc/metadataMatcher.ts'

/** Convert a phase angle (radians) to an estimated peak activity hour (0.0–23.9) */
const phaseTopeakHour = (phase: number): number => ((phase / (2 * Math.PI)) * 24 + 24) % 24

type Props = {
  animals: AnimalRecord[]
}

export function SinusoidMetadataPanel({ animals }: Props) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-base">Sinusoid Metadata Features (MIROsine)</CardTitle>
        <CardDescription>Per-animal fitted sinusoid parameters from HCM analysis</CardDescription>
      </CardHeader>
      <CardContent className="p-0 pb-3">
        {animals.length === 0 ? (
          <div className="px-4 py-6 text-center text-sm text-muted-foreground">
            No sinusoid data available
          </div>
        ) : (
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full text-xs">
              <thead className="bg-muted/50">
                <tr>
                  {['Animal ID', 'HCM system', 'Amplitude', 'Mesor (baseline)', 'Phase (rad)', 'Peak Hour'].map((h) => (
                    <th key={h} className="px-3 py-2 text-left font-semibold text-muted-foreground">
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {animals.map((animal, i) => {
                  const p = animal.circadianParameters
                  return (
                    <tr key={`${animal.animalId}-${animal.cageId}`} className={i % 2 === 0 ? 'bg-background' : 'bg-muted/20'}>
                      <td className="px-3 py-1.5 font-mono text-[11px]">{animal.animalId}</td>
                      <td className="px-3 py-1.5">{animal.metadata.dvcSystem || animal.metadata.sourceApp}</td>
                      <td className="px-3 py-1.5">{p.amplitude.toFixed(2)}</td>
                      <td className="px-3 py-1.5">{p.baseline.toFixed(2)}</td>
                      <td className="px-3 py-1.5">{p.phase.toFixed(3)}</td>
                      <td className="px-3 py-1.5">{phaseTopeakHour(p.phase).toFixed(1)}</td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
