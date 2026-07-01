import { useState } from 'react'
import { Button } from '@/components/ui/button.tsx'

export function DetailTabs({
  overview,
  audit,
}: {
  overview: React.ReactNode
  audit: React.ReactNode
}) {
  const [tab, setTab] = useState<'overview' | 'audit'>('overview')

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2 rounded-full border border-line bg-white p-1 text-sm">
        <Button variant={tab === 'overview' ? 'default' : 'ghost'} size="sm" onClick={() => setTab('overview')}>
          Overview
        </Button>
        <Button variant={tab === 'audit' ? 'default' : 'ghost'} size="sm" onClick={() => setTab('audit')}>
          Audit
        </Button>
      </div>
      {tab === 'overview' ? overview : audit}
    </div>
  )
}
