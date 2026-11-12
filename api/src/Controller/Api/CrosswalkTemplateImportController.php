<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Crosswalk\Import\ElnImportService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Imports ELN RO-Crate templates from an uploaded `.eln` file or a URL.
 */
#[IsGranted('ROLE_USER')]
final class CrosswalkTemplateImportController extends AbstractController
{
    public function __construct(
        private readonly ElnImportService $importService,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route(path: '/api/external-templates/import-eln', name: 'api_crosswalk_import_eln', methods: ['POST'])]
    public function importEln(Request $request): JsonResponse
    {
        $user = $this->requireUser();

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['message' => 'No ELN file uploaded.'], Response::HTTP_BAD_REQUEST);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ('' !== $extension && !\in_array($extension, ['eln', 'zip', 'json'], true)) {
            return new JsonResponse(['message' => 'Unsupported file type. Expected .eln.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $template = $this->importService->importEln($file, $user);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => 'Unable to import ELN file.', 'detail' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->serialized($template);
    }

    #[Route(path: '/api/external-templates/import-url', name: 'api_crosswalk_import_eln_url', methods: ['POST'])]
    public function importUrl(Request $request): JsonResponse
    {
        $user = $this->requireUser();

        $payload = $this->decodeBody($request);
        $url = \is_string($payload['url'] ?? null) ? $payload['url'] : null;
        if (null === $url || '' === trim($url)) {
            return new JsonResponse(['message' => 'A "url" is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $template = $this->importService->importElnUrl($url, $user);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => 'Unable to import ELN from URL.', 'detail' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->serialized($template);
    }

    private function serialized(object $entity): JsonResponse
    {
        $json = $this->serializer->serialize($entity, 'json', ['groups' => ['external_template.read', 'extracted_template_field.read']]);

        return new JsonResponse($json, Response::HTTP_CREATED, [], true);
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
