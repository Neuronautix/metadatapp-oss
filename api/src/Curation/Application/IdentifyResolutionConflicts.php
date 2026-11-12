<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Entity\ImportRow;
use App\Entity\ImportSession;
use App\Entity\SchemaFieldMappingProposal;
use App\Entity\Subject;
use Doctrine\ORM\EntityManagerInterface;

final readonly class IdentifyResolutionConflicts
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private \App\Curation\Vocabulary\VocabularyResolver $vocabularyResolver,
    ) {
    }

    public function execute(ImportSession $session): void
    {
        // 1. Find the column mapped to Subject.externalId or Subject.id/label
        $mappingProposals = $this->entityManager->getRepository(SchemaFieldMappingProposal::class)
            ->findBy(['session' => $session])
        ;

        $idColumn = null;
        foreach ($mappingProposals as $mapping) {
            if ('approved' === $mapping->getDecisionStatus() || 'undecided' === $mapping->getDecisionStatus()) {
                if (\in_array($mapping->getTargetField(), ['Subject.externalId', 'Subject.id', 'Subject.name'], true)) {
                    $idColumn = $mapping->getColumn();
                    break;
                }
            }
        }

        if (!$idColumn) {
            return;
        }

        $headerName = $idColumn->getHeaderName();
        $rows = $this->entityManager->getRepository(ImportRow::class)
            ->findBy(['session' => $session], ['rowIndex' => 'ASC'])
        ;

        $assignedUuids = []; // Local cache for session-level consistency

        foreach ($rows as $row) {
            $payload = $row->getSourcePayload();
            $idValue = (string) ($payload[$headerName] ?? '');

            if (empty(trim($idValue))) {
                continue;
            }

            // 1. Check if already matched in DB
            $matchId = $this->vocabularyResolver->resolve('subject_by_id', $idValue);

            if ($matchId) {
                $row->setResolvedSubjectId(\Symfony\Component\Uid\Uuid::fromString($matchId));
                $row->setResolutionStatus('resolved');
                continue;
            }

            // 2. Check if already assigned a new UUID in this session
            if (isset($assignedUuids[$idValue])) {
                $row->setResolvedSubjectId($assignedUuids[$idValue]);
                $row->setResolutionStatus('unresolved');
                continue;
            }

            // 3. Assign a brand new UUID
            $newUuid = \Symfony\Component\Uid\Uuid::v4();
            $assignedUuids[$idValue] = $newUuid;
            $row->setResolvedSubjectId($newUuid);
            $row->setResolutionStatus('unresolved');
        }

        $this->entityManager->flush();
    }
}
