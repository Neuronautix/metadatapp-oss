<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Reference\Semantic\ReferenceBestMatchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Ranks the given search results by relevance to the query ("best match" hint).
 * Wired as the `POST /references/best-match` API Platform operation on
 * {@see \App\ConnectedApps\Reference\StandardizedReference}, mirroring the
 * standardize endpoint.
 *
 * Body: { "query": string, "items": ReferenceResult[], "limit"?: int }
 */
final class ReferenceBestMatchController extends AbstractController
{
    public function __construct(private readonly ReferenceBestMatchService $bestMatchService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $query = \is_string($payload['query'] ?? null) ? $payload['query'] : '';
        $items = \is_array($payload['items'] ?? null) ? array_values($payload['items']) : [];
        $limit = \is_int($payload['limit'] ?? null) ? $payload['limit'] : 3;

        return new JsonResponse(['matches' => $this->bestMatchService->rank($query, $items, $limit)]);
    }
}
