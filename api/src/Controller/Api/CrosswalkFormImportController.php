<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Crosswalk\Cde\CdeImportService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Imports external CDE Forms and Common Data Elements, either from a named
 * provider ({provider, externalId}) or from a manual JSON payload ({payload}).
 */
#[IsGranted('ROLE_USER')]
final class CrosswalkFormImportController extends AbstractController
{
    public function __construct(
        private readonly CdeImportService $cdeImportService,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route(path: '/api/external-forms/import', name: 'api_crosswalk_import_form', methods: ['POST'])]
    public function importForm(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $payload = $this->decodeBody($request);

        $provider = \is_string($payload['provider'] ?? null) ? $payload['provider'] : null;
        $externalId = \is_string($payload['externalId'] ?? null) ? $payload['externalId'] : null;

        if (null === $provider || null === $externalId) {
            return new JsonResponse(['message' => 'A "provider" and "externalId" are required to import a form.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $form = $this->cdeImportService->importFormFromProvider($provider, $externalId, $user);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => 'Unable to import CDE form.', 'detail' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null === $form) {
            return new JsonResponse(['message' => 'Form not found in the selected provider.'], Response::HTTP_NOT_FOUND);
        }

        $json = $this->serializer->serialize($form, 'json', ['groups' => ['external_form.read']]);

        return new JsonResponse($json, Response::HTTP_CREATED, [], true);
    }

    #[Route(path: '/api/cdes/import', name: 'api_crosswalk_import_cde', methods: ['POST'])]
    public function importCde(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $payload = $this->decodeBody($request);

        $provider = \is_string($payload['provider'] ?? null) ? $payload['provider'] : null;
        $externalId = \is_string($payload['externalId'] ?? null) ? $payload['externalId'] : null;
        $manual = $payload['payload'] ?? null;

        try {
            if (null !== $provider && null !== $externalId) {
                $cde = $this->cdeImportService->importFromProvider($provider, $externalId, $user);
                if (null === $cde) {
                    return new JsonResponse(['message' => 'CDE not found in the selected provider.'], Response::HTTP_NOT_FOUND);
                }
                $json = $this->serializer->serialize($cde, 'json', ['groups' => ['common_data_element.read']]);

                return new JsonResponse($json, Response::HTTP_CREATED, [], true);
            }

            if (\is_array($manual) || \is_string($manual)) {
                $cdes = $this->cdeImportService->importFromPayload($manual, $user);
                $json = $this->serializer->serialize($cdes, 'json', ['groups' => ['common_data_element.read']]);

                return new JsonResponse($json, Response::HTTP_CREATED, [], true);
            }
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => 'Unable to import CDE.', 'detail' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['message' => 'Provide either {provider, externalId} or {payload}.'], Response::HTTP_BAD_REQUEST);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(Request $request): array
    {
        $content = $request->getContent();
        if ('' === $content) {
            return [];
        }
        $decoded = json_decode($content, true);

        return \is_array($decoded) ? $decoded : [];
    }
}
