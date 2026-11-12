<?php

declare(strict_types=1);

namespace App\Guideline;

use App\Entity\Experiment;
use App\Entity\GuidelineFieldValue;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Resolves a guideline-scoped Study (Experiment) or Investigation (Project) by
 * UUID, enforces tenant isolation (account match or AccessDenied), and builds
 * the shared metadata dict + columns via the resolver.
 *
 * Shared by every guideline endpoint so the tenant check lives in one place.
 */
final readonly class GuidelineResourceLocator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
        private ResourceMetadataResolver $resolver,
    ) {
    }

    /**
     * @return array{
     *     resourceType: string,
     *     resourceId: Uuid,
     *     experiment: Experiment|null,
     *     project: Project|null,
     *     metadata: array<string, mixed>,
     *     columns: list<CsvColumn>
     * }
     */
    public function locateStudy(string $id): array
    {
        $experiment = $this->findStudy($id);
        $resolved = $this->resolver->resolveForStudy($experiment);

        return [
            'resourceType' => GuidelineFieldValue::RESOURCE_STUDY,
            'resourceId' => $experiment->getId() ?? Uuid::fromString($id),
            'experiment' => $experiment,
            'project' => null,
            'metadata' => $resolved['metadata'],
            'columns' => $resolved['columns'],
        ];
    }

    /**
     * @return array{
     *     resourceType: string,
     *     resourceId: Uuid,
     *     experiment: Experiment|null,
     *     project: Project|null,
     *     metadata: array<string, mixed>,
     *     columns: list<CsvColumn>
     * }
     */
    public function locateInvestigation(string $id): array
    {
        $project = $this->findInvestigation($id);
        $resolved = $this->resolver->resolveForInvestigation($project);

        return [
            'resourceType' => GuidelineFieldValue::RESOURCE_INVESTIGATION,
            'resourceId' => $project->getId() ?? Uuid::fromString($id),
            'experiment' => null,
            'project' => $project,
            'metadata' => $resolved['metadata'],
            'columns' => $resolved['columns'],
        ];
    }

    public function findStudy(string $id): Experiment
    {
        $uuid = $this->parseUuid($id, 'Study');
        /** @var Experiment|null $experiment */
        $experiment = $this->entityManager->getRepository(Experiment::class)->find($uuid);
        if (null === $experiment) {
            throw new NotFoundHttpException(\sprintf('Study "%s" not found.', $id));
        }
        $this->assertSameAccount($experiment->getAccount()->getId());

        return $experiment;
    }

    public function findInvestigation(string $id): Project
    {
        $uuid = $this->parseUuid($id, 'Investigation');
        /** @var Project|null $project */
        $project = $this->entityManager->getRepository(Project::class)->find($uuid);
        if (null === $project) {
            throw new NotFoundHttpException(\sprintf('Investigation "%s" not found.', $id));
        }
        $this->assertSameAccount($project->getAccount()->getId());

        return $project;
    }

    public function currentUser(): User
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
    }

    private function assertSameAccount(?Uuid $resourceAccountId): void
    {
        $userAccountId = $this->currentUser()->getAccount()->getId();
        if (null === $resourceAccountId || null === $userAccountId || !$resourceAccountId->equals($userAccountId)) {
            throw new AccessDeniedHttpException('You do not have access to this resource.');
        }
    }

    private function parseUuid(string $id, string $label): Uuid
    {
        try {
            return Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('%s "%s" not found.', $label, $id));
        }
    }
}
