<?php

declare(strict_types=1);

namespace App\AI\Runtime;

use App\AI\Mcp\ToolAccessPolicy;

/**
 * Provider-agnostic tool definitions (name + description + JSON-Schema input) for
 * the Reference Hub tools the agentic loop can call. The shape matches the
 * Anthropic / OpenAI "tools" contract so a gateway can forward them verbatim.
 *
 * Schemas mirror what {@see ReferenceToolDispatcher} actually reads from the
 * arguments — keep the two in sync.
 */
final class AgenticToolCatalog
{
    /**
     * @var array<string, array{description: string, input_schema: array<string, mixed>}>
     */
    private const array DEFINITIONS = [
        'search_reference_resources' => [
            'description' => 'Search the connected external reference databases (JAX Phenome, IMPC, NIH CDE, …) for strains, phenotype measures, procedures, CDEs or protocols matching a free-text query.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Free-text search query, e.g. "blood glucose".'],
                    'apps' => ['type' => 'string', 'description' => 'Optional comma-separated AppCodes to restrict the search (e.g. "jax_phenome,impc,nih_cde").'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results per source (1-50).'],
                ],
                'required' => ['query'],
            ],
        ],
        'list_my_references' => [
            'description' => 'List the references the lab has already imported into its Reference Hub library (newest first).',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer', 'description' => 'Max references to return (default 50).'],
                ],
            ],
        ],
        'standardize_references' => [
            'description' => 'Compare and link library references across sources: cluster equivalents, map each cluster to a canonical ontology term, and harmonize their metadata fields (flagging conflicts). Pass the federated ids of references already in the library.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'referenceIds' => ['type' => 'string', 'description' => 'Comma-separated federated reference ids from the library (e.g. "jax_phenome:measure:43003,impc:measure:IMPC_HEM_001").'],
                ],
                'required' => ['referenceIds'],
            ],
        ],
        'import_reference' => [
            'description' => 'Import the given reference result objects into the lab library. This is a write action and requires human approval.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'array', 'description' => 'Reference result objects to import (as returned by search_reference_resources).', 'items' => ['type' => 'object']],
                ],
                'required' => ['items'],
            ],
        ],
        'read_guideline_evidence' => [
            'description' => 'Read the deterministic, locally-computed guideline evidence (computed facts, elabFTW fields, connected-resource links, prior fills, FAIR signals) for a Study or Investigation, optionally narrowed to a single guideline field. Read-only; account-scoped; never calls external services.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'resourceType' => ['type' => 'string', 'description' => 'Either "study" or "investigation".'],
                    'resourceId' => ['type' => 'string', 'description' => 'UUID of the Study (Experiment) or Investigation (Project).'],
                    'fieldId' => ['type' => 'string', 'description' => 'Optional guideline field id to narrow the evidence to.'],
                ],
                'required' => ['resourceType', 'resourceId'],
            ],
        ],
    ];

    /**
     * Tool definitions for the given access policies (only tools we have a schema
     * for are returned), in the provider "tools" shape.
     *
     * @param list<ToolAccessPolicy> $registry
     *
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function definitions(array $registry): array
    {
        $defs = [];
        foreach ($registry as $policy) {
            $definition = self::DEFINITIONS[$policy->toolName] ?? null;
            if (null === $definition) {
                continue;
            }
            $defs[] = [
                'name' => $policy->toolName,
                'description' => $definition['description'],
                'input_schema' => $definition['input_schema'],
            ];
        }

        return $defs;
    }
}
