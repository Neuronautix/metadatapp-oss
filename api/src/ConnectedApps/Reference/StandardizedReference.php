<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\ConnectedApps\Reference\Standardization\StandardizationResult;
use App\Controller\Api\ReferenceBestMatchController;
use App\Controller\Api\ReferenceStandardizeController;

/**
 * The result of "AI Compare & Link / Standardize" over a set of Reference Hub
 * results: clusters of cross-source equivalents, each mapped to one canonical
 * ontology term with evidence and a proposed importable record.
 *
 * Exposed two ways:
 *   - REST   POST /references/standardize   (body { items: ReferenceResult[] }) — the Compare view
 *   - MCP    tool `standardize_references`  (arg referenceIds) — the AI chatbot, over the user's library
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/references/standardize',
            controller: ReferenceStandardizeController::class,
            description: 'Cluster the given reference results across sources and propose one canonical, importable record per concept.',
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/references/best-match',
            controller: ReferenceBestMatchController::class,
            description: 'Rank the given search results by relevance to the query (the "best match" hint).',
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
    ],
    mcp: [
        'standardize_references' => new McpTool(
            name: 'standardize_references',
            description: 'Compare and link reference resources the lab has imported into its library: cluster equivalents across sources (JAX Phenome, IMPC, NIH CDE, …), map each cluster to one canonical ontology term (VT/MP/MA/EFO) with confidence and evidence, and propose a single standardized record per concept. Pass the federated ids of references already in the library.',
            uriTemplate: '/references/standardize',
            provider: ReferenceStandardizeProvider::class,
            parameters: new Parameters([
                'referenceIds' => new QueryParameter(key: 'referenceIds', property: 'referenceIds', description: 'Comma-separated federated reference ids from the library (e.g. "jax_phenome:measure:43003,impc:measure:IMPC_HEM_001").', required: true, schema: ['type' => 'string']),
            ]),
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class StandardizedReference
{
    /**
     * @param list<array<string, mixed>> $clusters
     * @param list<string>               $warnings
     * @param list<array<string, mixed>> $provenance
     * @param array<string, mixed>       $usage
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public bool $aiAvailable,
        public array $clusters,
        public array $warnings,
        public array $provenance,
        public array $usage,
    ) {
    }

    public static function fromResult(StandardizationResult $result): self
    {
        $array = $result->toArray();

        return new self(
            id: 'standardization',
            aiAvailable: $array['aiAvailable'],
            clusters: $array['clusters'],
            warnings: $array['warnings'],
            provenance: $array['provenance'],
            usage: $array['usage'],
        );
    }
}
