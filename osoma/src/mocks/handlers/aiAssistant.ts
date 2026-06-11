import { http, HttpResponse } from 'msw'

export const aiAssistantHandlers = [
  http.get('/api/ai/status', () =>
    HttpResponse.json({
      status: {
        enabled: true,
        available: true,
        configured: true,
        realProvider: false,
        provider: 'mock',
        message: 'Using the deterministic mock AI gateway.',
        capabilities: [
          'chat',
          'suggest_form_inputs',
          'explain_missing_metadata',
          'draft_query_filters',
          'generate_export_content',
        ],
      },
      trace: {
        traceId: 'mock-ai-status',
        action: 'status',
        route: '/ai/status',
        recordedAt: new Date().toISOString(),
      },
    })
  ),

  http.post('/api/ai/chat', async ({ request }) => {
    const body = await request.json() as { messages?: Array<{ role: string; content: string }> }
    const lastMessage = getLastUserMessage(body.messages)

    let reply = 'I can help draft form inputs, explain missing metadata, create query filters, and generate export-ready structured content for review.'
    if (lastMessage.includes('filter') || lastMessage.includes('search')) {
      reply = 'Describe the records you want to find, and I can draft reviewable search filters that you can apply manually.'
    } else if (lastMessage.includes('missing') || lastMessage.includes('metadata')) {
      reply = 'Share the visible fields you want reviewed and I will explain what is missing and why it matters.'
    } else if (lastMessage.includes('export')) {
      reply = 'I can prepare export-oriented structured content such as an abstract, highlights, and keywords for review.'
    }

    return HttpResponse.json({
      reply,
      status: {
        enabled: true,
        available: true,
        configured: true,
        realProvider: false,
        provider: 'mock',
        message: 'Using the deterministic mock AI gateway.',
        capabilities: ['chat'],
      },
      trace: {
        traceId: 'mock-ai-chat',
        action: 'chat',
        route: '/ai/chat',
        recordedAt: new Date().toISOString(),
      },
      provenance: [],
      warnings: [],
      structuredOutput: {
        nextActions: ['Open a form', 'Open a list', 'Open a detail page'],
      },
    })
  }),

  http.post('/api/ai/suggest', async ({ request }) => {
    const body = await request.json() as {
      action?: string
      prompt?: string
      context?: Record<string, unknown>
    }

    const responseBase = {
      status: {
        enabled: true,
        available: true,
        configured: true,
        realProvider: false,
        provider: 'mock',
        message: 'Using the deterministic mock AI gateway.',
        capabilities: ['suggest'],
      },
      trace: {
        traceId: 'mock-ai-suggest',
        action: 'suggest',
        route: '/ai/suggest',
        recordedAt: new Date().toISOString(),
      },
      provenance: [],
      warnings: [],
      reviewRequired: true,
      canApplyDirectly: false,
    }

    if (body.action === 'draft_query_filters') {
      return HttpResponse.json({
        ...responseBase,
        action: body.action,
        summary: 'Drafted review filters from the free-text request.',
        suggestions: [
          {
            field: 'query',
            label: 'Recommended query',
            preview: String(body.prompt ?? 'focus on missing identifiers'),
            reason: 'Summarized the operator request into a reviewable query.',
            value: body.prompt ?? 'focus on missing identifiers',
          },
        ],
        structuredOutput: {
          filters: {
            prompt: String(body.prompt ?? ''),
            resource: body.context?.resource,
            page: body.context?.page,
          },
        },
      })
    }

    if (body.action === 'suggest_form_inputs') {
      const fields = (body.context?.fields ?? {}) as Record<string, unknown>

      return HttpResponse.json({
        ...responseBase,
        action: body.action,
        summary: 'Generated draft form suggestions for review.',
        suggestions: [
          {
            field: 'name',
            label: 'Name',
            preview: typeof fields.name === 'string' ? fields.name.trim() || 'Draft metadata record' : 'Draft metadata record',
            reason: 'Normalized a primary label for review.',
            value: typeof fields.name === 'string' ? fields.name.trim() || 'Draft metadata record' : 'Draft metadata record',
          },
        ],
        structuredOutput: {
          fields: {
            ...fields,
            name: typeof fields.name === 'string' ? fields.name.trim() || 'Draft metadata record' : 'Draft metadata record',
            description: typeof fields.description === 'string' && fields.description.trim()
              ? fields.description.trim().replace(/\s+/g, ' ')
              : 'Structured metadata draft prepared for review.',
          },
        },
      })
    }

    if (body.action === 'generate_export_content') {
      return HttpResponse.json({
        ...responseBase,
        action: body.action,
        summary: 'Generated export-oriented structured content for review.',
        suggestions: [
          {
            field: 'title',
            label: 'Export title',
            preview: String(body.context?.title ?? 'Metadata export draft'),
            reason: 'Prepared a reviewable export title.',
            value: String(body.context?.title ?? 'Metadata export draft'),
          },
        ],
        structuredOutput: {
          title: String(body.context?.title ?? 'Metadata export draft'),
          abstract: String(body.context?.description ?? 'AI-generated export draft for review.'),
          highlights: ['AI-generated preview only', 'Review before export'],
          keywords: ['metadata-review'],
          metadataChecklist: ['Confirm identifiers', 'Confirm missing metadata follow-up'],
        },
      })
    }

    return HttpResponse.json({
      ...responseBase,
      action: body.action ?? 'explain_missing_metadata',
      summary: 'Explained the visible missing metadata fields.',
      suggestions: [
        {
          field: 'missing_fields',
          label: 'Missing metadata',
          preview: 'Review the highlighted empty fields before finalizing this record.',
          reason: 'The record still contains empty or incomplete values.',
          value: body.context?.missingFields ?? [],
        },
      ],
      structuredOutput: {
        missingFields: body.context?.missingFields ?? [],
      },
    })
  }),
]

const getLastUserMessage = (messages: Array<{ role: string; content: string }> | undefined) =>
  messages?.filter((message) => message.role === 'user').at(-1)?.content?.toLowerCase() ?? ''
