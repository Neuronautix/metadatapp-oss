import type { ReactNode } from 'react'

export function DVCObservatoryLayout({
  control,
  timeline,
  insights,
}: {
  control: ReactNode
  timeline: ReactNode
  insights: ReactNode
}) {
  return (
    <div className="grid gap-6 lg:grid-cols-[1.1fr_2fr_1.1fr]">
      <div>{control}</div>
      <div>{timeline}</div>
      <div>{insights}</div>
    </div>
  )
}
