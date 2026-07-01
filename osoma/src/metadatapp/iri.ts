export type IriLike = string | { '@id'?: string; id?: string } | null | undefined

export const getIri = (value: IriLike): string | undefined => {
  if (!value) return undefined
  if (typeof value === 'string') return value
  return value['@id'] ?? value.id
}

export const getIdFromIri = (value: IriLike): string => {
  const iri = getIri(value)
  if (!iri) return ''
  const trimmed = iri.replace(/\/$/, '')
  const segments = trimmed.split('/')
  return segments[segments.length - 1] ?? iri
}

export const toIri = (resource: string, id?: string | null): string | undefined => {
  if (!id) return undefined
  const safe = String(id).replace(/^\//, '')
  return `/${resource}/${safe}`
}

export const asArray = <T>(value: T[] | null | undefined): T[] => (Array.isArray(value) ? value : [])
