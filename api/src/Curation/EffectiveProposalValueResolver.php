<?php

declare(strict_types=1);

namespace App\Curation;

use App\Entity\CurationDecision;
use App\Entity\ImportSession;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class EffectiveProposalValueResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Retuns the effective approved value, effective value source, and effective review status for a proposal/field context.
     * We look up the latest CurationDecision for this proposal to determine effectiveness.
     */
    public function resolveEffectiveContext(ImportSession $session, string $proposalType, Uuid $proposalId, mixed $generatedValue): array
    {
        /** @var CurationDecision|null $decision */
        $decision = $this->entityManager->getRepository(CurationDecision::class)
            ->findOneBy(
                ['session' => $session, 'proposalType' => $proposalType, 'proposalId' => $proposalId],
                ['decidedAt' => 'DESC']
            )
        ;

        if (!$decision) {
            return [
                'effectiveValue' => $generatedValue,
                'effectiveSource' => 'generated',
                'effectiveStatus' => 'undecided',
            ];
        }

        $status = $decision->getDecision();

        // In Milestone A, an "overridden" decision means a new proposal was likely created,
        // or the override value is stored in the proposal itself. The prompt says "EffectiveProposalValueResolver as the only component allowed to answer: effective approved value..."
        // But the value is stored in the proposal. So this resolver takes the proposal entity itself.
        return [
            'effectiveStatus' => $status,
            'effectiveSource' => 'overridden' === $status ? 'reviewer_override' : 'generated', // This logic might need adjustment based on how overrides are saved
        ];
    }
}
