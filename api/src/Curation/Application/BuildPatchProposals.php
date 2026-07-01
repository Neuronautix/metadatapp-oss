<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Entity\ImportRow;
use App\Entity\ImportSession;
use App\Entity\PatchProposal;
use App\Entity\SchemaFieldMappingProposal;
use App\Entity\ValueNormalizationProposal;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BuildPatchProposals
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private \Symfony\Component\PropertyAccess\PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function execute(ImportSession $session): void
    {
        // 1. Get all approved/undecided mappings
        $mappings = $this->entityManager->getRepository(SchemaFieldMappingProposal::class)
            ->findBy(['session' => $session])
        ;

        $activeMappings = [];
        foreach ($mappings as $m) {
            if ('rejected' !== $m->getDecisionStatus()) {
                $header = $m->getColumn()->getHeaderName();
                $activeMappings[$header] = $m->getTargetField();
            }
        }

        // 2. Get all approved/undecided normalizations
        $normalizations = $this->entityManager->getRepository(ValueNormalizationProposal::class)
            ->findBy(['session' => $session])
        ;

        $activeNormalizations = [];
        foreach ($normalizations as $n) {
            if ('rejected' !== $n->getDecisionStatus()) {
                $column = $n->getColumn();
                if ($column) {
                    $activeNormalizations[$column->getHeaderName()][(string) $n->getOriginalValue()] = $n->getNormalizedValue();
                }
            }
        }

        $rows = $this->entityManager->getRepository(ImportRow::class)
            ->findBy(['session' => $session])
        ;

        foreach ($rows as $row) {
            $this->buildRowPatches($session, $row, $activeMappings, $activeNormalizations);
        }

        $session->setPatchesBuiltAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function buildRowPatches(
        ImportSession $session,
        ImportRow $row,
        array $activeMappings,
        array $activeNormalizations,
    ): void {
        $payload = $row->getSourcePayload();
        $subjectId = $row->getResolvedSubjectId();

        if (!$subjectId) {
            return;
        }

        /** @var \App\Entity\Subject|null $subject */
        $subject = $this->entityManager->find(\App\Entity\Subject::class, $subjectId);

        foreach ($activeMappings as $header => $targetField) {
            $rawValue = $payload[$header] ?? null;
            if (null === $rawValue || '' === trim((string) $rawValue)) {
                continue;
            }

            // Apply normalization/vocabulary if available
            $proposedValue = $activeNormalizations[$header][(string) $rawValue] ?? $rawValue;

            // Delta Check: only propose if value is different from DB
            if ($subject && $this->isSameValue($subject, $targetField, $proposedValue)) {
                continue;
            }

            $this->createPatch($session, $row, $subjectId, $targetField, $proposedValue);
        }
    }

    private function isSameValue(\App\Entity\Subject $subject, string $targetField, mixed $proposedValue): bool
    {
        $fieldParts = explode('.', $targetField);
        $propertyName = end($fieldParts);

        try {
            $existingValue = $this->propertyAccessor->getValue($subject, $propertyName);

            // Normalize existing value for comparison
            if ($existingValue instanceof \BackedEnum) {
                $existingValue = $existingValue->value;
            } elseif ($existingValue instanceof \DateTimeInterface) {
                $existingValue = $existingValue->format('Y-m-d');
                // Trim proposed if it's a date string too
                if (\is_string($proposedValue)) {
                    $proposedValue = substr($proposedValue, 0, 10);
                }
            } elseif ($existingValue instanceof \App\Entity\Strain) {
                $existingValue = (string) $existingValue->getId();
            }

            // Loose comparison for strings/numbers
            return (string) $existingValue === (string) $proposedValue;
        } catch (\Exception) {
            return false;
        }
    }

    private function createPatch(
        ImportSession $session,
        ImportRow $row,
        \Symfony\Component\Uid\Uuid $subjectId,
        string $targetField,
        mixed $value,
    ): void {
        $repo = $this->entityManager->getRepository(PatchProposal::class);
        $existing = $repo->findOneBy([
            'session' => $session,
            'row' => $row,
            'targetField' => $targetField,
        ]);

        if ($existing) {
            $existing->setProposedValue($value);

            return;
        }

        $patch = new PatchProposal();
        $patch->setSession($session);
        $patch->setRow($row);
        $patch->setSubjectId($subjectId);
        $patch->setTargetField($targetField);
        $patch->setProposedValue($value);
        $patch->setDecisionStatus('undecided');
        $patch->setSource('generated');

        $this->entityManager->persist($patch);
    }
}
