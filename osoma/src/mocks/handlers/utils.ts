import { delay } from 'msw'

export const withLatency = async (min = 240, max = 820) => {
  await delay(min + Math.random() * (max - min))
}

export const matches = (value: string, search: string) => value.toLowerCase().includes(search.toLowerCase())

export const paginate = <T,>(items: T[], page: number, pageSize: number) => {
  const total = items.length
  const start = (page - 1) * pageSize
  const data = items.slice(start, start + pageSize)
  return { data, total }
}
