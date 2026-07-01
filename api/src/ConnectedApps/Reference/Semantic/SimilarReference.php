<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Semantic;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;

/**
 * A nearest-neighbour match from the account's reference library for a free-text
 * query or an existing library reference, ranked by semantic (cosine) similarity.
 *
 * Exposed two ways from one provider:
 *   - REST   GET /similar-references?q=…&referenceId=…&limit=…   (the Library "Find similar" button)
 *   - MCP    tool `find_similar_references`                       (the AI chatbot)
 *
 * Similarity is computed in pure PHP over the library (see
 * {@see ReferenceEmbeddingService}); it works with AI off and without pgvector.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/similar-references',
            paginationEnabled: false,
            provider: SimilarReferenceProvider::class,
            description: 'Find the nearest items in your reference library by semantic similarity.',
            parameters: new Parameters([
                'q' => new QueryParameter(key: 'q', description: 'Free-text query to find similar library items for.', schema: ['type' => 'string']),
                'referenceId' => new QueryParameter(key: 'referenceId', description: 'Federated id of a library item to find neighbours of (alternative to q).', schema: ['type' => 'string']),
                'limit' => new QueryParameter(key: 'limit', description: 'Max results (1-50).', schema: ['type' => 'integer', 'default' => 5]),
            ]),
        ),
    ],
    mcp: [
        'find_similar_references' => new McpTool(
            name: 'find_similar_references',
            description: "Find the most semantically similar resources already in the lab's reference library (its imported strains, phenotype measures, procedures, CDEs and protocols) for a free-text query or for an existing library item. Returns nearest matches with a similarity score and a deep link to each source-of-truth — useful for de-duplicating, cross-referencing, and discovering related standardized resources the lab already saved.",
            uriTemplate: '/similar-references',
            provider: SimilarReferenceProvider::class,
            parameters: new Parameters([
                'query' => new QueryParameter(key: 'query', property: 'query', description: 'Free-text query (e.g. "blood sugar", "locomotor activity"). Either query or referenceId is required.', schema: ['type' => 'string']),
                'referenceId' => new QueryParameter(key: 'referenceId', property: 'referenceId', description: 'Federated id of a library item to find neighbours of (e.g. "jax_phenome:measure:43003").', schema: ['type' => 'string']),
                'limit' => new QueryParameter(key: 'limit', property: 'limit', description: 'Max results (1-50, default 5).', schema: ['type' => 'integer', 'default' => 5]),
            ]),
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class SimilarReference
{
    /**
     * @param array<string, mixed> $identifiers
     */
    public function __construct(
        /** Federated id of the matched library item, e.g. "jax_phenome:measure:43003". */
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $source,
        public string $sourceName,
        public string $type,
        public string $title,
        public ?string $description = null,
        public ?string $externalUrl = null,
        /** Cosine similarity in [0, 1] (higher = more similar). */
        public float $score = 0.0,
        /** @var array<string, mixed> */
        public array $identifiers = [],
    ) {
    }
}
