<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AccountAwareInterface;
use App\Entity\User;
use App\Repository\ExperimentRepository;
use App\Repository\ProjectRepository;
use App\Service\Fair2JsonLdBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes machine-readable FAIR² / Croissant ML metadata endpoints.
 *
 * Each Investigation (Project) and Study (Experiment) exposes a predictable
 * fair2.json URL that authenticated users can GET.
 * When a future `isPublic` visibility flag is introduced on the entities,
 * the access_control rules can be relaxed for public datasets.
 *
 * Spec: https://fair2.science / https://mlcommons.org/working-groups/data/croissant/
 */
class Fair2Controller extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ExperimentRepository $experimentRepository,
        private readonly Fair2JsonLdBuilder $builder,
    ) {
    }

    #[Route(
        path: '/investigations/{id}/fair2.json',
        name: 'api_fair2_investigation',
        methods: ['GET'],
    )]
    public function investigationFair2(string $id, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $project = $this->projectRepository->find($id);
        if (null === $project) {
            return $this->json(['error' => 'Investigation not found.'], Response::HTTP_NOT_FOUND);
        }
        if (!$this->canAccessAccountResource($project)) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $baseUrl = $request->getSchemeAndHttpHost();
        $document = $this->builder->buildForProject($project, $baseUrl);

        return $this->jsonLd($document);
    }

    #[Route(
        path: '/studies/{id}/fair2.json',
        name: 'api_fair2_study',
        methods: ['GET'],
    )]
    public function studyFair2(string $id, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $experiment = $this->experimentRepository->find($id);
        if (null === $experiment) {
            return $this->json(['error' => 'Study not found.'], Response::HTTP_NOT_FOUND);
        }
        if (!$this->canAccessAccountResource($experiment)) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $baseUrl = $request->getSchemeAndHttpHost();
        $document = $this->builder->buildForExperiment($experiment, $baseUrl);

        return $this->jsonLd($document);
    }

    private function canAccessAccountResource(AccountAwareInterface $resource): bool
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $resource->getAccount()->getId()?->toRfc4122() === $user->getAccount()->getId()?->toRfc4122();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonLd(array $data): JsonResponse
    {
        $response = new JsonResponse($data);
        $response->headers->set('Content-Type', 'application/ld+json');

        return $response;
    }
}
