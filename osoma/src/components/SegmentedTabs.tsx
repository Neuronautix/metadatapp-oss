import { useState } from 'react'
import { Button } from '@/components/ui/button.tsx'

export type TabItem = {
  id: string
  label: string
  content: React.ReactNode
}

export function SegmentedTabs({ tabs, defaultTabId }: { tabs: TabItem[]; defaultTabId?: string }) {
  const initial = defaultTabId ?? tabs[0]?.id
  const [active, setActive] = useState(initial)

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-2 rounded-full border border-line bg-white p-1 text-sm">
        {tabs.map((tab) => (
          <Button
            key={tab.id}
            size="sm"
            variant={active === tab.id ? 'default' : 'ghost'}
            onClick={() => setActive(tab.id)}
          >
            {tab.label}
          </Button>
        ))}
      </div>
      {tabs.find((tab) => tab.id === active)?.content}
    </div>
  )
}
