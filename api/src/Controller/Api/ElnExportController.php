<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\User;
use App\Service\Eln\ElnExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Streams an investigation as a ".eln" file (The ELN Consortium File Format),
 * built from the FAIR3R DataCite 4.x exchange JSON.
 */
#[IsGranted('ROLE_USER')]
final class ElnExportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ElnExporter $exporter,
    ) {
    }

    #[Route(path: '/api/v1/export/eln/{investigationId}', name: 'api_export_eln', methods: ['GET'])]
    public function investigation(string $investigationId, Request $request): Response
    {
        try {
            $uuid = Uuid::fromString($investigationId);
        } catch (\InvalidArgumentException) {
            return $this->json(['message' => 'Invalid investigation ID format.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var Project|null $project */
        $project = $this->entityManager->getRepository(Project::class)->find($uuid);
        if (null === $project) {
            throw $this->createNotFoundException(\sprintf('Investigation %s not found', $investigationId));
        }

        $this->assertAccess($project->getAccount()->getId());

        return $this->exporter->stream($project, $request->getSchemeAndHttpHost());
    }

    #[Route(path: '/api/v1/export/eln/study/{studyId}', name: 'api_export_eln_study', methods: ['GET'])]
    public function study(string $studyId, Request $request): Response
    {
        try {
            $uuid = Uuid::fromString($studyId);
        } catch (\InvalidArgumentException) {
            return $this->json(['message' => 'Invalid study ID format.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var Experiment|null $experiment */
        $experiment = $this->entityManager->getRepository(Experiment::class)->find($uuid);
        if (null === $experiment) {
            throw $this->createNotFoundException(\sprintf('Study %s not found', $studyId));
        }

        $this->assertAccess($experiment->getAccount()->getId());

        return $this->exporter->streamStudy($experiment, $request->getSchemeAndHttpHost());
    }

    private function assertAccess(?Uuid $resourceAccountId): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $userAccountId = $user->getAccount()->getId();
        if (null === $resourceAccountId || null === $userAccountId || !$resourceAccountId->equals($userAccountId)) {
            throw $this->createAccessDeniedException('Resource not accessible.');
        }
    }
}
