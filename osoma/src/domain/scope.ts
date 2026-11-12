export type ScopeType = 'facility' | 'room' | 'rack' | 'cage'

export type ScopeRef = {
  type: ScopeType
  id: string
  label?: string
}
