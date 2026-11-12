<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Import\Nwb\NwbImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class NwbImportController extends AbstractController
{
    public function __construct(
        private readonly NwbImportService $importService,
    ) {
    }

    #[Route('/api/v1/imports/nwb', name: 'api_nwb_import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['message' => 'No NWB file uploaded.'], Response::HTTP_BAD_REQUEST);
        }

        if ('nwb' !== strtolower((string) $file->getClientOriginalExtension())) {
            return new JsonResponse(['message' => 'Unsupported file type. Expected .nwb.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            return new JsonResponse([
                'ok' => true,
                'nwb' => $this->importService->inspect($file),
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'message' => 'Unable to import NWB file.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
