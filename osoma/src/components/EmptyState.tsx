import { Button } from '@/components/ui/button.tsx'
import { Card } from '@/components/ui/card.tsx'

export function EmptyState({
  title,
  description,
  actionLabel,
  onAction,
}: {
  title: string
  description: string
  actionLabel?: string
  onAction?: () => void
}) {
  return (
    <Card className="flex flex-col items-start gap-3">
      <div>
        <h3 className="font-display text-lg">{title}</h3>
        <p className="mt-1 text-sm text-slate-500">{description}</p>
      </div>
      {actionLabel && onAction ? <Button onClick={onAction}>{actionLabel}</Button> : null}
    </Card>
  )
}
