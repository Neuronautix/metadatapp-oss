import { http, HttpResponse } from 'msw'
import { withLatency } from './utils.ts'

const taxonomyItems = [
  {
    label: 'Mus musculus',
    sublabel: 'NCBITaxon:10090',
    value: 'mouse',
    externalId: 'http://purl.obolibrary.org/obo/NCBITaxon_10090',
    scheme: 'NCBITaxon',
  },
  {
    label: 'Rattus norvegicus',
    sublabel: 'NCBITaxon:10116',
    value: 'rat',
    externalId: 'http://purl.obolibrary.org/obo/NCBITaxon_10116',
    scheme: 'NCBITaxon',
  },
  {
    label: 'Danio rerio',
    sublabel: 'NCBITaxon:7955',
    value: 'zebrafish',
    externalId: 'http://purl.obolibrary.org/obo/NCBITaxon_7955',
    scheme: 'NCBITaxon',
  },
]

const rorItems = [
  {
    label: 'Université de Paris',
    sublabel: 'Paris, France',
    value: 'Université de Paris',
    externalId: 'https://ror.org/03yrm5c26',
    scheme: 'ROR',
  },
  {
    label: 'Sorbonne Université',
    sublabel: 'Paris, France',
    value: 'Sorbonne Université',
    externalId: 'https://ror.org/02en5vm52',
    scheme: 'ROR',
  },
]

const orcidItems = [
  {
    label: 'Ada Lovelace',
    sublabel: 'Analytical Engine Institute',
    value: '0000-0001-2345-6789',
    externalId: 'https://orcid.org/0000-0001-2345-6789',
    scheme: 'ORCID',
  },
  {
    label: 'Alan Turing',
    sublabel: 'Bletchley Park',
    value: '0000-0002-9876-5432',
    externalId: 'https://orcid.org/0000-0002-9876-5432',
    scheme: 'ORCID',
  },
]

const strainItems = [
  {
    label: 'C57BL/6J',
    sublabel: 'EFO:0004472',
    value: 'C57BL/6J',
    externalId: 'http://www.ebi.ac.uk/efo/EFO_0004472',
    scheme: 'EFO',
  },
  {
    label: 'BALB/c',
    sublabel: 'EFO:0004473',
    value: 'BALB/c',
    externalId: 'http://www.ebi.ac.uk/efo/EFO_0004473',
    scheme: 'EFO',
  },
]

const includesCaseInsensitive = (value: string, query: string) => value.toLowerCase().includes(query.toLowerCase())

export const lookupHandlers = [
  http.get('/api/lookups/:source', async ({ params, request }) => {
    await withLatency()
    const query = new URL(request.url).searchParams.get('q')?.trim() ?? ''
    if (!query) {
      return HttpResponse.json({ items: [] })
    }

    const items = (() => {
      switch (params.source) {
        case 'orcid':
          return orcidItems.filter((item) => includesCaseInsensitive(item.label, query) || includesCaseInsensitive(item.value, query))
        case 'ror':
          return rorItems.filter((item) => includesCaseInsensitive(item.label, query) || includesCaseInsensitive(item.sublabel ?? '', query))
        case 'ols_ncbitaxon':
          return taxonomyItems.filter((item) => includesCaseInsensitive(item.label, query) || includesCaseInsensitive(item.value, query))
        case 'ols_efo':
          return strainItems.filter((item) => includesCaseInsensitive(item.label, query) || includesCaseInsensitive(item.sublabel ?? '', query))
        default:
          return []
      }
    })()

    return HttpResponse.json({ items })
  }),

  http.post('/api/lookups/strain/resolve', async ({ request }) => {
    await withLatency()
    const payload = await request.json() as { label?: string; externalId?: string | null }
    const label = payload.label?.trim()
    if (!label) {
      return HttpResponse.json({ message: 'The strain label is required.' }, { status: 400 })
    }

    const slug = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')

    return HttpResponse.json({
      value: `/strains/${slug || 'resolved-strain'}`,
      label,
      externalId: payload.externalId ?? null,
    })
  }),
]
