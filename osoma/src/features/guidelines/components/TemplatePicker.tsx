import { Card, CardContent } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { cn } from '@/lib/utils.ts'
import type { GuidelineTemplateSummary } from '../guidelines.types.ts'

type Props = {
  templates: GuidelineTemplateSummary[]
  selectedId: string | null
  onSelect: (id: string) => void
}

export function TemplatePicker({ templates, selectedId, onSelect }: Props) {
  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="radiogroup" aria-label="Guideline template">
      {templates.map((template) => {
        const active = template.id === selectedId
        return (
          <Card
            key={template.id}
            role="radio"
            aria-checked={active}
            tabIndex={0}
            onClick={() => onSelect(template.id)}
            onKeyDown={(event) => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()
                onSelect(template.id)
              }
            }}
            className={cn(
              'cursor-pointer transition hover:border-brand/60',
              active && 'border-brand ring-2 ring-brand/30',
            )}
          >
            <CardContent className="space-y-2 p-4">
              <div className="flex items-center justify-between gap-2">
                <span className="font-display text-sm font-semibold text-ink">{template.name}</span>
                <Badge variant="outline">v{template.version}</Badge>
              </div>
              <p className="text-xs text-muted">{template.description}</p>
              <div className="flex flex-wrap gap-1.5 text-[11px] text-muted">
                <Badge variant="outline">{template.requiredMetadataCount} metadata</Badge>
                {template.columnCount > 0 ? <Badge variant="outline">{template.columnCount} columns</Badge> : null}
                {template.conformsTo.length > 0 ? (
                  <Badge variant="outline">inherits {template.conformsTo.join(', ')}</Badge>
                ) : null}
              </div>
            </CardContent>
          </Card>
        )
      })}
    </div>
  )
}
