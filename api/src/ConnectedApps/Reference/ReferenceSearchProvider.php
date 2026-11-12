<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shared state provider for both the REST collection (GET /reference-search) and
 * the `search_reference_resources` MCP tool. Reads arguments from the MCP call
 * (`$context['mcp_data']`) or, for REST, the current request's query string, then
 * delegates to {@see ReferenceSearchService}.
 *
 * @implements ProviderInterface<ReferenceResult>
 */
final readonly class ReferenceSearchProvider implements ProviderInterface
{
    public function __construct(
        private ReferenceSearchService $searchService,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<ReferenceResult>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $args = \is_array($context['mcp_data'] ?? null) ? $context['mcp_data'] : [];
        if ([] === $args) {
            $args = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
        }

        $query = $this->stringArg($args, ['query', 'q']);
        $limit = (int) ($args['limit'] ?? 20);

        $appsRaw = $args['apps'] ?? '';
        $appCodes = \is_array($appsRaw)
            ? array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $appsRaw)))
            : array_values(array_filter(array_map('trim', explode(',', (string) $appsRaw))));

        return $this->searchService->search($query, $appCodes, $limit);
    }

    /**
     * @param array<string, mixed> $args
     * @param list<string>         $keys
     */
    private function stringArg(array $args, array $keys): string
    {
        foreach ($keys as $key) {
            if (\is_string($args[$key] ?? null) && '' !== trim($args[$key])) {
                return trim($args[$key]);
            }
        }

        return '';
    }
}
