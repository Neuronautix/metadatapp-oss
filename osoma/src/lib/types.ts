export type PaginatedResponse<T> = {
  data: T[]
  page: number
  pageSize: number
  total: number
}
