import { http, HttpResponse } from 'msw'
import { canonicalForms } from '@/mocks/data/canonical-forms.ts'
import { withLatency } from './utils.ts'

export const canonicalFormsHandlers = [
  http.get('/api/canonical-forms', async () => {
    await withLatency()
    return HttpResponse.json({
      '@context': '/contexts/CanonicalForm',
      '@id': '/canonical-forms',
      '@type': 'hydra:Collection',
      'hydra:member': canonicalForms,
      'hydra:totalItems': canonicalForms.length,
      'hydra:view': {
        '@id': '/canonical-forms?page=1',
        '@type': 'hydra:PartialCollectionView',
      },
    })
  }),

  http.get('/api/canonical-forms/:id', async ({ params }) => {
    await withLatency()
    const form = canonicalForms.find((entry) => entry.id === params.id)
    if (!form) {
      return HttpResponse.json({ message: 'Canonical form not found.' }, { status: 404 })
    }
    return HttpResponse.json(form)
  }),
]
