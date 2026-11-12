<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Entity\ImportSession;
use App\Entity\User;

final readonly class RecordBulkProposalDecision
{
    public function __construct(
        private RecordProposalDecision $recordProposalDecision,
    ) {
    }

    public function execute(ImportSession $session, array $proposals, string $decisionValue, User $reviewer): void
    {
        foreach ($proposals as $proposalData) {
            $this->recordProposalDecision->execute(
                $session,
                $proposalData['type'],
                $proposalData['id'],
                $decisionValue,
                $reviewer
            );
        }
    }
}
