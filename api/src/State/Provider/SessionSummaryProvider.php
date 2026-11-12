<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Curation\Application\GetPatchBuildSummary;
use App\Curation\Application\GetPendingReviewSummary;
use App\Curation\Application\GetResolutionConflictSummary;
use App\Curation\Application\GetSessionStatusSummary;
use App\Entity\ImportSession;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<object>
 */
final readonly class SessionSummaryProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GetSessionStatusSummary $statusSummary,
        private GetPendingReviewSummary $reviewSummary,
        private GetResolutionConflictSummary $conflictSummary,
        private GetPatchBuildSummary $patchSummary,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $sessionId = $uriVariables['id'] ?? null;
        if (!$sessionId) {
            return null;
        }

        $session = $this->entityManager->getRepository(ImportSession::class)->find(Uuid::fromString((string) $sessionId));
        if (!$session) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }
        $path = $request->getPathInfo();

        return match (true) {
            str_ends_with($path, '/summary') => $this->statusSummary->execute($session),
            str_ends_with($path, '/review-summary') => $this->reviewSummary->execute($session),
            str_ends_with($path, '/resolution-summary') => $this->conflictSummary->execute($session),
            str_ends_with($path, '/patch-summary') => $this->patchSummary->execute($session),
            default => null,
        };
    }
}
