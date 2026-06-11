<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Entity\ImportSession;

final readonly class GetResolutionConflictSummary
{
    public function __construct(
        private \Doctrine\ORM\EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(ImportSession $session): \App\Curation\UI\ResolutionConflictSummary
    {
        $rows = $this->entityManager->getRepository(\App\Entity\ImportRow::class)
            ->findBy(['session' => $session])
        ;

        $resolvedCount = 0;
        $unresolvedCount = 0;
        $uniqueResolved = [];
        $uniqueUnresolved = [];

        foreach ($rows as $row) {
            $id = (string) $row->getResolvedSubjectId();
            if ('resolved' === $row->getResolutionStatus()) {
                ++$resolvedCount;
                $uniqueResolved[$id] = true;
            } else {
                ++$unresolvedCount;
                $uniqueUnresolved[$id] = true;
            }
        }

        if (!$id = $session->getId()) {
            throw new \RuntimeException('Session ID cannot be null.');
        }

        return new \App\Curation\UI\ResolutionConflictSummary(
            $id,
            [
                'total' => \count($rows),
                'resolved' => $resolvedCount,
                'unresolved' => $unresolvedCount,
                'ambiguous' => \count($rows) - ($resolvedCount + $unresolvedCount), // default to ambiguous if needed
            ],
            [
                'existing_to_update' => \count($uniqueResolved),
                'new_to_create' => \count($uniqueUnresolved),
            ]
        );
    }
}
