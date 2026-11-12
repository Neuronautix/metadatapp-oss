import React from 'react'
import { EmptyState } from '@/components/EmptyState.tsx'

type ErrorBoundaryState = {
  hasError: boolean
  error?: Error
}

export class ErrorBoundary extends React.Component<React.PropsWithChildren, ErrorBoundaryState> {
  state: ErrorBoundaryState = { hasError: false }

  static getDerivedStateFromError(error: Error) {
    return { hasError: true, error }
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="px-6 py-10">
          <EmptyState
            title="Something went wrong"
            description={this.state.error?.message ?? 'Unexpected error'}
            actionLabel="Reload"
            onAction={() => window.location.reload()}
          />
        </div>
      )
    }

    return this.props.children
  }
}
