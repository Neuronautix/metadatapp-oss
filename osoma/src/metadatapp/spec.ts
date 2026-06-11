import spec from '../../resources/metadatapp.json'

export const metadatappSpec = spec as {
  paths: Record<string, Record<string, unknown>>
  components?: { schemas?: Record<string, Record<string, unknown>> }
}
