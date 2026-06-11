<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Entity\CurationDecision;
use App\Entity\ImportSession;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class RecordProposalDecision
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(ImportSession $session, string $proposalType, Uuid $proposalId, string $decisionValue, User $reviewer): CurationDecision
    {
        $decision = new CurationDecision();
        $decision->setSession($session);
        $decision->setProposalType($proposalType);
        $decision->setProposalId($proposalId);
        $decision->setDecision($decisionValue); // 'approved', 'rejected', 'overridden'
        $decision->setDecidedBy($reviewer);

        $this->entityManager->persist($decision);
        $this->entityManager->flush();

        return $decision;
    }
}
