<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Entity\ImportRow;
use App\Entity\ImportSession;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GetSessionStatusSummary
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Payload must include unresolved row count, ambiguous row count,
     * proposal counts by type and status, reviewer override count, etc.
     */
    public function execute(ImportSession $session): \App\Curation\UI\ImportSessionSummary
    {
        $unresolvedRows = $this->entityManager->getRepository(ImportRow::class)
            ->count(['session' => $session, 'resolutionStatus' => 'unresolved'])
        ;
        $ambiguousRows = $this->entityManager->getRepository(ImportRow::class)
            ->count(['session' => $session, 'resolutionStatus' => 'ambiguous'])
        ;

        $proposals = $this->entityManager->getRepository(\App\Entity\SchemaFieldMappingProposal::class)
            ->findBy(['session' => $session])
        ;

        $proposalData = array_map(static fn (\App\Entity\SchemaFieldMappingProposal $p) => [
            'id' => (string) $p->getId(),
            'column_id' => (string) $p->getColumn()->getColumnIndex(),
            'header' => $p->getColumn()->getHeaderName(),
            'target_field' => $p->getTargetField(),
            'status' => $p->getDecisionStatus(),
        ], $proposals);

        if (!$id = $session->getId()) {
            throw new \RuntimeException('Session ID cannot be null.');
        }

        return new \App\Curation\UI\ImportSessionSummary(
            $id,
            $session->getStatus()->value,
            $unresolvedRows,
            $ambiguousRows,
            [], // next_allowed_actions
            $proposalData,
            [
                'profiled_at' => $session->getProfiledAt()?->format(\DateTimeInterface::ATOM),
                'schema_generated_at' => $session->getSchemaMappingsGeneratedAt()?->format(\DateTimeInterface::ATOM),
                'patched_built_at' => $session->getPatchesBuiltAt()?->format(\DateTimeInterface::ATOM),
                'applied_at' => $session->getAppliedAt()?->format(\DateTimeInterface::ATOM),
            ]
        );
    }
}
