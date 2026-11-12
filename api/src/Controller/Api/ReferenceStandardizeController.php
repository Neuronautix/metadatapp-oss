<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Reference\Standardization\ReferenceStandardizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * AI "Compare & Link": clusters the selected Reference Hub results across sources
 * and proposes one canonical, importable record per concept. Wired as the
 * `POST /references/standardize` API Platform operation on
 * {@see \App\ConnectedApps\Reference\StandardizedReference} (so it runs under the
 * API firewall), mirroring {@see ReferenceImportController}.
 *
 * Body: { "items": ReferenceResult[] }
 */
final class ReferenceStandardizeController extends AbstractController
{
    public function __construct(private readonly ReferenceStandardizationService $standardizationService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $items = \is_array($payload['items'] ?? null) ? array_values($payload['items']) : [];

        return new JsonResponse($this->standardizationService->standardize($items)->toArray());
    }
}
