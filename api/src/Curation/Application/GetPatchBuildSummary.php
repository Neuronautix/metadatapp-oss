<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Entity\ImportSession;
use App\Entity\PatchProposal;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GetPatchBuildSummary
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(ImportSession $session): \App\Curation\UI\PatchBuildSummary
    {
        $patches = $this->entityManager->getRepository(PatchProposal::class)
            ->findBy(['session' => $session])
        ;

        $readyToApply = 0;
        $subjectDeltas = [];

        foreach ($patches as $patch) {
            if ('approved' === $patch->getDecisionStatus() || 'undecided' === $patch->getDecisionStatus()) {
                ++$readyToApply;
            }

            $sid = (string) $patch->getSubjectId();
            if (!isset($subjectDeltas[$sid])) {
                $subjectDeltas[$sid] = [
                    'subject_id' => $sid,
                    'patch_count' => 0,
                    'fields' => [],
                ];
            }
            ++$subjectDeltas[$sid]['patch_count'];
            $subjectDeltas[$sid]['fields'][] = [
                'target_field' => $patch->getTargetField(),
                'proposed_value' => $patch->getProposedValue(),
                'status' => $patch->getDecisionStatus(),
            ];
        }

        if (!$id = $session->getId()) {
            throw new \RuntimeException('Session ID cannot be null.');
        }

        return new \App\Curation\UI\PatchBuildSummary(
            $id,
            \count($patches),
            $readyToApply,
            \count($subjectDeltas),
            array_values($subjectDeltas)
        );
    }
}
