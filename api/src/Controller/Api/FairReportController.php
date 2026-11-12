<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AccountAwareInterface;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\User;
use App\Service\ArriveReportPdfService;
use App\Service\FairReportPdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Exposes FAIR assessment PDF report endpoints for Studies and Investigations.
 *
 * GET /studies/{id}/fair-report.pdf        — FAIR report for a single Study (Experiment)
 * GET /investigations/{id}/fair-report.pdf — FAIR report for an Investigation (Project)
 * GET /studies/{id}/arrive-report.pdf        — ARRIVE helper report for a single Study
 * GET /investigations/{id}/arrive-report.pdf — ARRIVE helper report for an Investigation
 *
 * Both endpoints enforce:
 *  - Authentication (ROLE_USER)
 *  - Tenant isolation (authenticated user's account must match the resource's account)
 */
#[IsGranted('ROLE_USER')]
final class FairReportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FairReportPdfService $fairReportPdfService,
        private readonly ArriveReportPdfService $arriveReportPdfService,
    ) {
    }

    #[Route(
        path: '/studies/{id}/fair-report.pdf',
        name: 'api_fair_report_study',
        methods: ['GET'],
    )]
    public function studyFairReport(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            return $this->json(['message' => 'Invalid study ID format.'], Response::HTTP_BAD_REQUEST);
        }
        $uuid = Uuid::fromString($id);

        /** @var Experiment|null $experiment */
        $experiment = $this->entityManager->getRepository(Experiment::class)->find($uuid);

        if (null === $experiment) {
            return $this->json(['message' => 'Study not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->belongsToCurrentAccount($experiment)) {
            return $this->json(['message' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        return $this->fairReportPdfService->generateForExperiment($experiment);
    }

    #[Route(
        path: '/investigations/{id}/fair-report.pdf',
        name: 'api_fair_report_investigation',
        methods: ['GET'],
    )]
    public function investigationFairReport(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            return $this->json(['message' => 'Invalid investigation ID format.'], Response::HTTP_BAD_REQUEST);
        }
        $uuid = Uuid::fromString($id);

        /** @var Project|null $project */
        $project = $this->entityManager->getRepository(Project::class)->find($uuid);

        if (null === $project) {
            return $this->json(['message' => 'Investigation not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->belongsToCurrentAccount($project)) {
            return $this->json(['message' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        return $this->fairReportPdfService->generateForProject($project);
    }

    #[Route(
        path: '/studies/{id}/arrive-report.pdf',
        name: 'api_arrive_report_study',
        methods: ['GET'],
    )]
    public function studyArriveReport(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            return $this->json(['message' => 'Invalid study ID format.'], Response::HTTP_BAD_REQUEST);
        }
        $uuid = Uuid::fromString($id);

        /** @var Experiment|null $experiment */
        $experiment = $this->entityManager->getRepository(Experiment::class)->find($uuid);

        if (null === $experiment) {
            return $this->json(['message' => 'Study not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->belongsToCurrentAccount($experiment)) {
            return $this->json(['message' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        return $this->arriveReportPdfService->generateForExperiment($experiment);
    }

    #[Route(
        path: '/investigations/{id}/arrive-report.pdf',
        name: 'api_arrive_report_investigation',
        methods: ['GET'],
    )]
    public function investigationArriveReport(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            return $this->json(['message' => 'Invalid investigation ID format.'], Response::HTTP_BAD_REQUEST);
        }
        $uuid = Uuid::fromString($id);

        /** @var Project|null $project */
        $project = $this->entityManager->getRepository(Project::class)->find($uuid);

        if (null === $project) {
            return $this->json(['message' => 'Investigation not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->belongsToCurrentAccount($project)) {
            return $this->json(['message' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        return $this->arriveReportPdfService->generateForProject($project);
    }

    private function belongsToCurrentAccount(AccountAwareInterface $resource): bool
    {
        $resourceAccountId = $resource->getAccount()->getId();
        $currentAccountId = $this->getCurrentUser()->getAccount()->getId();

        return null !== $resourceAccountId
            && null !== $currentAccountId
            && $resourceAccountId->equals($currentAccountId);
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            return $user;
        }

        if ($user instanceof UserInterface) {
            /** @var User|null $managedUser */
            $managedUser = $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => $user->getUserIdentifier(),
            ]);

            if ($managedUser instanceof User) {
                return $managedUser;
            }
        }

        throw $this->createAccessDeniedException('Authentication required.');
    }
}
