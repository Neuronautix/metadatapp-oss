import { PageHeader } from '@/components/PageHeader'
import { AssistantChat } from '../components/AssistantChat'

export function AssistantPage() {
  return (
    <div className="space-y-8">
      <PageHeader
        title="AI Assistant"
        subtitle="Governed preview-only assistant for drafting metadata suggestions, query filters, and export-ready structure."
      />

      <div className="py-4">
        <AssistantChat />
      </div>
    </div>
  )
}
