import type { ReactNode } from 'react'
import { Sparkles } from 'lucide-react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

type AiActionPanelProps = {
  title: string
  description: string
  children: ReactNode
}

export function AiActionPanel({ title, description, children }: AiActionPanelProps) {
  return (
    <Card>
      <CardHeader>
        <div className="flex items-start gap-3">
          <div className="mt-0.5 rounded-xl bg-cyan-500/10 p-2 text-cyan-700">
            <Sparkles className="h-4 w-4" />
          </div>
          <div>
            <CardTitle>{title}</CardTitle>
            <CardDescription>{description}</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent className="flex flex-wrap gap-3">{children}</CardContent>
    </Card>
  )
}
