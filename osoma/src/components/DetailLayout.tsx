import type React from 'react'
import { PageHeader } from './PageHeader.tsx'
import { SegmentedTabs, type TabItem } from './SegmentedTabs.tsx'
import { Link } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/button.tsx'

export type DetailLayoutProps = {
    title: string
    subtitle?: string
    backLink: string
    backLabel?: string
    actions?: React.ReactNode
    tabs?: TabItem[]
    defaultTabId?: string
    children?: React.ReactNode
}

export function DetailLayout({
    title,
    subtitle,
    backLink,
    backLabel = 'Back',
    actions,
    tabs,
    defaultTabId,
    children,
}: DetailLayoutProps) {
    return (
        <div className="space-y-6">
            <PageHeader
                title={title}
                subtitle={subtitle}
                actions={
                    <div className="flex items-center gap-2">
                        <Button variant="ghost" size="sm" asChild>
                            <Link to={backLink}>
                                <ArrowLeft className="h-4 w-4" />
                                {backLabel}
                            </Link>
                        </Button>
                        {actions}
                    </div>
                }
            />

            {tabs && tabs.length > 0 ? (
                <SegmentedTabs tabs={tabs} defaultTabId={defaultTabId} />
            ) : null}

            {children}
        </div>
    )
}
