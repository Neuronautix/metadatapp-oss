import { AlertCircle, CheckCircle2, Sparkles } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import type { AiProviderStatus } from '../assistant.types'

type AiStatusBadgeProps = {
  status: AiProviderStatus
}

export function AiStatusBadge({ status }: AiStatusBadgeProps) {
  if (!status.enabled || !status.available) {
    return (
      <Badge variant="warning" className="gap-1">
        <AlertCircle className="h-3.5 w-3.5" />
        {status.provider}: unavailable
      </Badge>
    )
  }

  return (
    <Badge variant={status.realProvider ? 'success' : 'outline'} className="gap-1">
      {status.realProvider ? <CheckCircle2 className="h-3.5 w-3.5" /> : <Sparkles className="h-3.5 w-3.5" />}
      {status.provider}: {status.realProvider ? 'live' : 'mock'}
    </Badge>
  )
}
