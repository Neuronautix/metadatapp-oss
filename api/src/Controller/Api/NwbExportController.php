<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Experiment;
use App\Entity\User;
use App\Export\Nwb\NwbExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
final class NwbExportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NwbExportService $exportService,
    ) {
    }

    #[Route('/api/v1/exports/nwb/experiments/{id}', name: 'api_nwb_export_experiment', methods: ['GET'])]
    public function exportExperiment(string $id): Response
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['message' => 'Invalid experiment ID format.'], Response::HTTP_BAD_REQUEST);
        }

        $experiment = $this->entityManager->getRepository(Experiment::class)->find($uuid);
        if (!$experiment instanceof Experiment) {
            throw $this->createNotFoundException(\sprintf('Experiment %s not found.', $id));
        }

        $this->assertExperimentAccount($experiment);

        try {
            $path = $this->exportService->exportExperiment($experiment);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'Unable to export experiment as NWB.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $experiment->getName()) ?: 'experiment';
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename . '.nwb');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    private function assertExperimentAccount(Experiment $experiment): void
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $user = $this->getUser();
        $experimentAccountId = $experiment->getAccount()->getId();
        $userAccountId = $user instanceof User ? $user->getAccount()->getId() : null;
        if (!$user instanceof User || null === $experimentAccountId || null === $userAccountId || !$experimentAccountId->equals($userAccountId)) {
            throw $this->createAccessDeniedException('Experiment not accessible.');
        }
    }
}
