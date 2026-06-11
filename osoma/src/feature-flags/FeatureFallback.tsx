import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'

export interface FeatureFallbackProps {
  title: string
  description: string
  actionLabel?: string
  onAction?: () => void
  warning?: string
}

export function FeatureFallback({ title, description, actionLabel, onAction, warning }: FeatureFallbackProps) {
  return (
    <Card className="border-dashed border-2 border-line/60 bg-surface-2">
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        {warning ? (
          <Badge variant="warning" className="text-[10px] uppercase tracking-[0.2em]">
            {warning}
          </Badge>
        ) : null}
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className="flex items-center gap-3">
        <p className="text-sm text-slate-500">This feature is not visible right now.</p>
        {actionLabel && onAction ? (
          <Button size="sm" variant="ghost" onClick={onAction}>
            {actionLabel}
          </Button>
        ) : null}
      </CardContent>
    </Card>
  )
}
