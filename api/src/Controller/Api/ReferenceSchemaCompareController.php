<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\Schema\SchemaComparisonService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * AI "Compare Schemas": extracts structured field lists from the selected Reference
 * Hub results and asks the configured model gateway to produce a harmonized
 * field-group mapping.
 *
 * Body: { "items": ReferenceResult[] }
 */
#[Route('/references/compare-schemas', methods: ['POST'], format: 'json')]
final class ReferenceSchemaCompareController extends AbstractController
{
    public function __construct(private readonly SchemaComparisonService $comparisonService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $rawItems = \is_array($payload['items'] ?? null) ? array_values($payload['items']) : [];

        $items = array_values(array_filter(array_map(
            static function (mixed $raw): ?ReferenceResult {
                if (!\is_array($raw)) {
                    return null;
                }

                return new ReferenceResult(
                    id: (string) ($raw['id'] ?? ''),
                    source: (string) ($raw['source'] ?? ''),
                    sourceName: (string) ($raw['sourceName'] ?? ''),
                    type: (string) ($raw['type'] ?? ''),
                    title: (string) ($raw['title'] ?? ''),
                    subtitle: isset($raw['subtitle']) && \is_string($raw['subtitle']) ? $raw['subtitle'] : null,
                    description: isset($raw['description']) && \is_string($raw['description']) ? $raw['description'] : null,
                    externalUrl: isset($raw['externalUrl']) && \is_string($raw['externalUrl']) ? $raw['externalUrl'] : null,
                    identifiers: \is_array($raw['identifiers'] ?? null) ? $raw['identifiers'] : [],
                    raw: \is_array($raw['raw'] ?? null) ? $raw['raw'] : [],
                );
            },
            $rawItems,
        )));

        return new JsonResponse($this->comparisonService->compare($items)->toArray());
    }
}
