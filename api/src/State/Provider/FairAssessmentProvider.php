<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Experiment;
use App\Entity\User;
use App\Resource\FairAssessment;
use App\Service\FairAssessmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Provides a FairAssessment virtual resource for a given Study (Experiment) ID.
 *
 * @implements ProviderInterface<FairAssessment>
 */
final readonly class FairAssessmentProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FairAssessmentService $assessmentService,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FairAssessment
    {
        $id = $uriVariables['id'] ?? null;
        if (null === $id) {
            throw new NotFoundHttpException('Study ID is required.');
        }

        try {
            $uuid = Uuid::fromString((string) $id);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('Study "%s" not found.', $id));
        }

        /** @var Experiment|null $experiment */
        $experiment = $this->entityManager->getRepository(Experiment::class)->find($uuid);

        if (null === $experiment) {
            throw new NotFoundHttpException(\sprintf('Study "%s" not found.', $id));
        }

        // Tenant isolation: the authenticated user must belong to the same account.
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$user instanceof User || $experiment->getAccount()->getId()?->toRfc4122() !== $user->getAccount()->getId()?->toRfc4122()) {
            throw new AccessDeniedHttpException('You do not have access to this study.');
        }

        $assessment = $this->assessmentService->assessExperiment($experiment);

        $resource = new FairAssessment();
        $resource->id = $experiment->getId()?->toRfc4122() ?? $id;
        $resource->studyName = $experiment->getName();
        $resource->investigationName = $experiment->getProject()?->getName() ?? '';
        $resource->score = $assessment['score'];
        $resource->criteria = $assessment['criteria'];
        $resource->assessedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}
