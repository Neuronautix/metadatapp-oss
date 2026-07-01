<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ImportedReference;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Processes the `import_reference` MCP tool / POST /reference-imports operation by
 * delegating to {@see ReferenceImportService}. Reads the `items` payload from the
 * MCP call (`$context['mcp_data']`) or, for REST, from the JSON request body.
 *
 * This is the WRITE side of the Reference Hub tools. Authorization for the AI
 * agentic loop is enforced upstream by {@see \App\AI\Runtime\ToolUseOrchestrator}
 * (only invoked when human approval / write-back is enabled). The REST + MCP
 * operations themselves remain ROLE_USER scoped and tenant-isolated by the import
 * service.
 *
 * @implements ProcessorInterface<ImportReferenceCommand, ImportReferenceCommand>
 */
final readonly class ImportReferenceProcessor implements ProcessorInterface
{
    public function __construct(
        private ReferenceImportService $importService,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ImportReferenceCommand
    {
        $items = $this->extractItems($context);
        $saved = $this->importService->import($items);

        return new ImportReferenceCommand(
            id: 'import',
            count: \count($saved),
            imported: array_map(static fn (ImportedReference $reference): array => [
                'id' => (string) $reference->getId(),
                'referenceId' => $reference->getReferenceId(),
                'source' => $reference->getSource(),
                'sourceName' => $reference->getSourceName(),
                'type' => $reference->getType(),
                'title' => $reference->getTitle(),
                'externalUrl' => $reference->getExternalUrl(),
            ], $saved),
        );
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<mixed>
     */
    private function extractItems(array $context): array
    {
        $args = \is_array($context['mcp_data'] ?? null) ? $context['mcp_data'] : [];
        if ([] === $args) {
            $args = $this->readRequestBody();
        }

        $items = $args['items'] ?? null;

        return \is_array($items) ? array_values($items) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function readRequestBody(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($payload) ? $payload : [];
    }
}
