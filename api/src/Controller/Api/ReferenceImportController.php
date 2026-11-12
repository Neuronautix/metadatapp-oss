<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Reference\ReferenceImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Batch-imports selected Reference Hub results into the account's library.
 * Wired as the `POST /imported_references/import` API Platform operation on
 * {@see \App\Entity\ImportedReference} (so it runs under the API firewall).
 *
 * Body: { "items": ReferenceResult[] }
 */
final class ReferenceImportController extends AbstractController
{
    public function __construct(private readonly ReferenceImportService $importService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $items = \is_array($payload['items'] ?? null) ? array_values($payload['items']) : [];

        $saved = $this->importService->import($items);

        return new JsonResponse([
            'count' => \count($saved),
            'imported' => array_map(static fn ($reference): array => [
                'id' => (string) $reference->getId(),
                'referenceId' => $reference->getReferenceId(),
                'source' => $reference->getSource(),
                'sourceName' => $reference->getSourceName(),
                'type' => $reference->getType(),
                'title' => $reference->getTitle(),
                'externalUrl' => $reference->getExternalUrl(),
            ], $saved),
        ], 201);
    }
}
