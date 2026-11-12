#!/usr/bin/env node
import { Server } from '@modelcontextprotocol/sdk/server/index.js'
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js'
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js'
import { z } from 'zod'

import { validateContextUsage } from './validators/contextUsage.js'
import { checkOntologyDepth } from './validators/ontologyDepth.js'
import { checkIdentifierShapes } from './validators/identifierShape.js'
import { KNOWN_VOCABULARIES } from './knownVocabularies.js'
import { validateCedarSchemaCompatibility } from './validators/cedarSchemaCompatibility.js'

const JsonLdInput = z.object({
  jsonld: z.union([
    z.string(),
    z.record(z.unknown()),
  ]),
})

const OntologyExpectations = z.object({
  jsonld: z.union([z.string(), z.record(z.unknown())]),
  expectations: z
    .object({
      species: z.boolean().optional(),
      units: z.boolean().optional(),
      procedureTyping: z.boolean().optional(),
      genotype: z.boolean().optional(),
    })
    .optional(),
})

function parseJsonLd(input: unknown): unknown {
  if (typeof input === 'string') {
    try {
      return JSON.parse(input)
    } catch (err) {
      throw new Error(`Input is a string but not valid JSON: ${(err as Error).message}`)
    }
  }
  return input
}

const server = new Server(
  {
    name: 'metadatapp-fair-validator',
    version: '0.1.0',
  },
  {
    capabilities: {
      tools: {},
    },
  },
)

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: 'validate_fair_jsonld',
      description:
        'Audit a Metadatapp FAIR² JSON-LD payload. Reports declared-but-unused vocabularies (ontology theatre), structural identifier issues (ORCID/DOI/ROR/NCBITaxon shape), and a coverage ratio.',
      inputSchema: {
        type: 'object',
        properties: {
          jsonld: {
            description:
              'Either a JSON-LD object or a JSON string. Accepts both compact and @graph form.',
          },
        },
        required: ['jsonld'],
      },
    },
    {
      name: 'check_ontology_depth',
      description:
        'Per-domain checks: species → NCBITaxon, measurements → QUDT, procedures → typed beyond schema:Thing, genotypes → MGI. Returns one verdict per check.',
      inputSchema: {
        type: 'object',
        properties: {
          jsonld: {
            description: 'JSON-LD object or string.',
          },
          expectations: {
            type: 'object',
            properties: {
              species: { type: 'boolean' },
              units: { type: 'boolean' },
              procedureTyping: { type: 'boolean' },
              genotype: { type: 'boolean' },
            },
            additionalProperties: false,
            description:
              'Toggle individual checks. Defaults to running every check.',
          },
        },
        required: ['jsonld'],
      },
    },
    {
      name: 'list_known_vocabularies',
      description:
        'Lists the ontology prefixes the validator knows (schema, bioschemas, obi, qudt, ncbitaxon, mgi, pato, prov, …) with their canonical namespace IRIs.',
      inputSchema: {
        type: 'object',
        properties: {},
      },
    },
    {
      name: 'validate_cedar_schema_compatibility',
      description:
        'Checks whether a Metadatapp JSON-LD payload can be exported/imported as a CEDAR-compatible template shape for cedar-rest-mcp workflows, and returns a generated template skeleton plus round-trip diagnostics.',
      inputSchema: {
        type: 'object',
        properties: {
          jsonld: {
            description: 'JSON-LD object or string.',
          },
        },
        required: ['jsonld'],
      },
    },
  ],
}))

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const name = request.params.name
  const args = request.params.arguments ?? {}

  try {
    if (name === 'validate_fair_jsonld') {
      const parsed = JsonLdInput.parse(args)
      const doc = parseJsonLd(parsed.jsonld)
      const usage = validateContextUsage(doc)
      const idIssues = checkIdentifierShapes(doc)
      const merged = {
        ...usage,
        issues: [...usage.issues, ...idIssues],
        valid: usage.valid && !idIssues.some((i) => i.level === 'error'),
      }
      return jsonResult(merged)
    }

    if (name === 'check_ontology_depth') {
      const parsed = OntologyExpectations.parse(args)
      const doc = parseJsonLd(parsed.jsonld)
      const result = checkOntologyDepth(doc, parsed.expectations)
      return jsonResult(result)
    }

    if (name === 'list_known_vocabularies') {
      return jsonResult(
        KNOWN_VOCABULARIES.map((v) => ({
          prefix: v.prefix,
          namespaces: v.namespaces,
          description: v.description,
          examples: v.examples,
        })),
      )
    }

    if (name === 'validate_cedar_schema_compatibility') {
      const parsed = JsonLdInput.parse(args)
      const doc = parseJsonLd(parsed.jsonld)
      return jsonResult(validateCedarSchemaCompatibility(doc))
    }

    return jsonResult({ error: `Unknown tool: ${name}` }, true)
  } catch (err) {
    return jsonResult(
      { error: err instanceof Error ? err.message : String(err) },
      true,
    )
  }
})

function jsonResult(payload: unknown, isError = false) {
  return {
    isError,
    content: [
      {
        type: 'text' as const,
        text: JSON.stringify(payload, null, 2),
      },
    ],
  }
}

const transport = new StdioServerTransport()
await server.connect(transport)

// MCP servers should keep stdout clean (it's the protocol channel).
// Use stderr for any logs.
process.stderr.write('metadatapp-fair-validator ready (stdio)\n')
