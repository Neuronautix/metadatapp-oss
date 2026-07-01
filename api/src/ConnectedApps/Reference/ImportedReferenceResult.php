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
 * A reference resource that the current account has already imported into its
 * Reference Hub library. Exposed read-only to the AI chatbot via the
 * `list_my_references` MCP tool so the assistant can reason over what the lab has
 * already saved (e.g. before deciding whether to import a new search result).
 *
 * Exposed two ways from one provider:
 *   - REST   GET /my-references?limit=…       (parity with the Reference Hub page)
 *   - MCP    tool `list_my_references`         (the AI chatbot, read-only)
 */
#[ApiResource(
    shortName: 'MyReference',
    operations: [
        new GetCollection(
            uriTemplate: '/my-references',
            paginationEnabled: false,
            provider: ImportedReferenceListProvider::class,
            description: "The current account's imported reference library.",
            parameters: new Parameters([
                'limit' => new QueryParameter(key: 'limit', description: 'Max references to return (1-200).', schema: ['type' => 'integer', 'default' => 50]),
            ]),
        ),
    ],
    mcp: [
        'list_my_references' => new McpTool(
            name: 'list_my_references',
            description: 'List the reference resources the current account has already imported into its Reference Hub library (strains, phenotype measures, procedures, Common Data Elements, protocols). Read-only. Useful for checking what standardized resources the lab has already saved before searching or importing more.',
            uriTemplate: '/my-references',
            provider: ImportedReferenceListProvider::class,
            parameters: new Parameters([
                'limit' => new QueryParameter(key: 'limit', property: 'limit', description: 'Max references to return (1-200, default 50).', schema: ['type' => 'integer', 'default' => 50]),
            ]),
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class ImportedReferenceResult
{
    /**
     * @param array<string, mixed> $identifiers
     */
    public function __construct(
        /** Library row id (UUID). */
        #[ApiProperty(identifier: true)]
        public string $id,
        /** Stable federated id, e.g. "jax_phenome:measure:43003". */
        public string $referenceId,
        /** AppCode value of the producing connected app, e.g. "jax_phenome". */
        public string $source,
        /** Human-facing connected-app name, e.g. "JAX Phenome (MPD)". */
        public string $sourceName,
        /** Resource kind: strain | measure | procedure | pipeline | cde_element | protocol. */
        public string $type,
        public string $title,
        public ?string $description = null,
        /** Deep link to the source-of-truth record on the external site. */
        public ?string $externalUrl = null,
        /** @var array<string, mixed> */
        public array $identifiers = [],
        /** ISO-8601 import timestamp. */
        public ?string $importedAt = null,
    ) {
    }
}
