<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Lookup\ExternalLookupService;
use App\Lookup\StrainLookupResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/lookups', name: 'api_lookups_')]
#[IsGranted('ROLE_USER')]
final class LookupController extends AbstractController
{
    public function __construct(
        private ExternalLookupService $externalLookupService,
        private StrainLookupResolver $strainLookupResolver,
    ) {
    }

    #[Route('/{source}', name: 'search', methods: ['GET'])]
    public function search(string $source, Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if ('' === $query) {
            return new JsonResponse(['items' => []]);
        }

        try {
            return new JsonResponse([
                'items' => $this->externalLookupService->search($source, $query),
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable) {
            return new JsonResponse([
                'message' => 'Unable to fetch lookup suggestions.',
            ], 502);
        }
    }

    #[Route('/strain/resolve', name: 'resolve_strain', methods: ['POST'])]
    public function resolveStrain(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return new JsonResponse([
                'message' => 'Invalid strain resolution payload.',
            ], 400);
        }

        $label = trim((string) ($payload['label'] ?? ''));
        if ('' === $label) {
            return new JsonResponse([
                'message' => 'Invalid strain resolution payload.',
                'detail' => 'The strain label is required.',
            ], 400);
        }

        $externalId = isset($payload['externalId']) && \is_string($payload['externalId']) && '' !== trim($payload['externalId'])
            ? trim($payload['externalId'])
            : null;

        try {
            $strain = $this->strainLookupResolver->resolve($label, $externalId);
            $strainId = $strain->getId()?->toRfc4122();
            if (null === $strainId) {
                throw new \RuntimeException('Resolved strain is missing an identifier.');
            }

            return new JsonResponse([
                'value' => '/strains/' . $strainId,
                'label' => $strain->getName(),
                'externalId' => $strain->getLink(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'message' => 'Invalid strain resolution payload.',
                'detail' => $e->getMessage(),
            ], 400);
        } catch (\Throwable) {
            return new JsonResponse([
                'message' => 'Unable to resolve the selected strain.',
            ], 502);
        }
    }
}
