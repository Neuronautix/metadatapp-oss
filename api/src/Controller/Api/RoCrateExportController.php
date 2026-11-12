<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Project;
use App\Entity\User;
use App\Service\RoCrate\RoCrateExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
final class RoCrateExportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoCrateExporter $exporter,
    ) {
    }

    #[Route(path: '/api/v1/export/ro-crate/{investigationId}', name: 'api_export_ro_crate', methods: ['GET'])]
    public function __invoke(string $investigationId): Response
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

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $projectAccountId = $project->getAccount()->getId();
        $userAccountId = $user->getAccount()->getId();
        if (null === $projectAccountId || null === $userAccountId || !$projectAccountId->equals($userAccountId)) {
            throw $this->createAccessDeniedException('Investigation not accessible.');
        }

        return $this->exporter->stream($project);
    }
}
