export type SearchResourceType =
  | 'investigation'
  | 'study'
  | 'sample'
  | 'subject'
  | 'assay'
  | 'dataset'
  | 'user'
  | 'organization'

export type SearchResult = {
  id: string
  type: SearchResourceType
  title: string
  subtitle?: string
  status?: string
}

export type SearchResponse = {
  query: string
  results: SearchResult[]
}
