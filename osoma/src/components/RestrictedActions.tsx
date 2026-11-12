import { Button } from '@/components/ui/button.tsx'
import { Card } from '@/components/ui/card.tsx'

export function RestrictedActions({
  title,
  description,
  actionLabel,
  disabled,
  onAction,
}: {
  title: string
  description: string
  actionLabel: string
  disabled: boolean
  onAction: () => void
}) {
  return (
    <Card>
      <div>
        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{title}</p>
        <p className="mt-2 text-sm text-slate-600">{description}</p>
      </div>
      <div className="mt-4">
        <Button variant="outline" disabled={disabled} onClick={onAction}>
          {actionLabel}
        </Button>
      </div>
    </Card>
  )
}
