<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;

/**
 * Write command that imports federated Reference Hub results into the current
 * account's library. Exposed as the `import_reference` MCP tool (a mutating tool
 * that REQUIRES human approval — see {@see ToolAccessPolicy} and
 * {@see \App\AI\Governance\AiFeatureFlags::allowsWriteBack()}). Backed by
 * {@see ImportReferenceProcessor}, which delegates to {@see ReferenceImportService}.
 *
 *   - REST   POST /reference-imports   body: { items: ReferenceResult[] }
 *   - MCP    tool `import_reference`    args: { items: ReferenceResult[] }
 */
#[ApiResource(
    shortName: 'ImportReferenceCommand',
    operations: [
        new Post(
            uriTemplate: '/reference-imports',
            processor: ImportReferenceProcessor::class,
            description: "Import selected federated reference results into the account's library.",
        ),
    ],
    mcp: [
        'import_reference' => new McpTool(
            name: 'import_reference',
            description: "Import one or more reference resources (as returned by search_reference_resources) into the current account's Reference Hub library so they can be reused. This is a WRITE action: it persists records and requires human approval to be enabled. Pass the full result payloads under `items`. Idempotent per (account, reference id).",
            method: 'POST',
            uriTemplate: '/reference-imports',
            processor: ImportReferenceProcessor::class,
            parameters: new Parameters([
                'items' => new QueryParameter(key: 'items', property: 'items', description: 'Array of reference result payloads to import (each must include at least an `id`).', required: true, schema: ['type' => 'array']),
            ]),
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class ImportReferenceCommand
{
    /**
     * @param list<array<string, mixed>> $imported
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id = 'import',
        public int $count = 0,
        /** @var list<array<string, mixed>> */
        public array $imported = [],
    ) {
    }
}
