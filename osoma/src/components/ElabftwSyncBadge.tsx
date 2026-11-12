import { Badge } from '@/components/ui/badge.tsx'

type ElabftwSyncBadgeProps = {
  elaftwExternalId?: string
}

export function ElabftwSyncBadge({ elaftwExternalId }: ElabftwSyncBadgeProps) {
  return (
    <Badge
      variant="outline"
      className="gap-1.5 border-brand/20 bg-brand/5 text-[11px] font-medium text-slate-700"
      title={elaftwExternalId ? `ElabFTW ID: ${elaftwExternalId}` : 'Synced to ElabFTW'}
    >
      <img
        src="/Connected Apps Logo/Elabftw.png"
        alt=""
        aria-hidden="true"
        className="h-3.5 w-3.5 object-contain"
      />
      <span>Synced to ElabFTW</span>
    </Badge>
  )
}
