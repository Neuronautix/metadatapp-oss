<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;

/**
 * A single normalized reference resource returned by the federated Reference Hub
 * search. Each result carries its source (which connected app produced it), a
 * human-facing title/description, a deep link to the source-of-truth, and the raw
 * upstream record for the detail drawer.
 *
 * Exposed two ways from one provider:
 *   - REST   GET /reference-search?q=…&apps=…&limit=…   (the Reference Hub page)
 *   - MCP    tool `search_reference_resources`          (the AI chatbot)
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/reference-search',
            paginationEnabled: false,
            provider: ReferenceSearchProvider::class,
            description: 'Federated search across the connected external reference databases.',
            parameters: new Parameters([
                'q' => new QueryParameter(key: 'q', description: 'Free-text search query.', required: true, schema: ['type' => 'string']),
                'apps' => new QueryParameter(key: 'apps', description: 'Comma-separated AppCodes to search (defaults to all active connected apps).', schema: ['type' => 'string']),
                'limit' => new QueryParameter(key: 'limit', description: 'Max results per source (1-50).', schema: ['type' => 'integer', 'default' => 20]),
            ]),
        ),
    ],
    mcp: [
        'search_reference_resources' => new McpTool(
            name: 'search_reference_resources',
            description: "Search standardized reference resources (strains, phenotype measures, phenotyping procedures, Common Data Elements, protocols) across the lab's connected external databases (JAX Phenome, IMPC/IMPReSS, NIH CDE, PreclinicalTrials) in a single query. Returns each match with its source database, a deep link to the source-of-truth, and identifiers — useful for finding and cross-referencing standardized resources.",
            uriTemplate: '/reference-search',
            provider: ReferenceSearchProvider::class,
            parameters: new Parameters([
                'query' => new QueryParameter(key: 'query', property: 'query', description: 'Free-text search query (e.g. "blood", "open field", "C57BL/6J").', required: true, schema: ['type' => 'string']),
                'apps' => new QueryParameter(key: 'apps', property: 'apps', description: 'Comma-separated AppCodes to restrict the search (e.g. "jax_phenome,impc,nih_cde"). Defaults to all active connected apps.', schema: ['type' => 'string']),
                'limit' => new QueryParameter(key: 'limit', property: 'limit', description: 'Max results per source (1-50, default 20).', schema: ['type' => 'integer', 'default' => 20]),
            ]),
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class ReferenceResult
{
    /**
     * @param array<string, mixed> $identifiers
     * @param array<string, mixed> $raw
     */
    public function __construct(
        /** Composite, collection-unique id: "{source}:{type}:{sourceId}". */
        #[ApiProperty(identifier: true)]
        public string $id,
        /** AppCode value of the producing connected app, e.g. "jax_phenome". */
        public string $source,
        /** Human-facing connected-app name, e.g. "JAX Phenome (MPD)". */
        public string $sourceName,
        /** Resource kind: strain | measure | procedure | pipeline | cde_element | protocol | template | schema | guideline | ontology_class. */
        public string $type,
        public string $title,
        public ?string $subtitle = null,
        public ?string $description = null,
        /** Deep link to the source-of-truth record on the external site. */
        public ?string $externalUrl = null,
        /** @var array<string, mixed> */
        public array $identifiers = [],
        /** @var array<string, mixed> Raw upstream record for the detail drawer. */
        public array $raw = [],
    ) {
    }
}
