<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Curation\Registry\SchemaRegistry;
use App\Curation\Vocabulary\VocabularyResolver;
use App\Entity\ImportColumn;
use App\Entity\ImportRow;
use App\Entity\ImportSession;
use App\Entity\OntologyTermProposal;
use App\Entity\SchemaFieldMappingProposal;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GenerateVocabularyProposals
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SchemaRegistry $schemaRegistry,
        private VocabularyResolver $vocabularyResolver,
    ) {
    }

    public function execute(ImportSession $session): void
    {
        $mappingProposals = $this->entityManager->getRepository(SchemaFieldMappingProposal::class)
            ->findBy(['session' => $session])
        ;

        foreach ($mappingProposals as $mapping) {
            if ('rejected' === $mapping->getDecisionStatus()) {
                continue;
            }

            $column = $mapping->getColumn();
            $targetField = $mapping->getTargetField();
            $fieldParts = explode('.', $targetField);
            $fieldName = end($fieldParts);

            $fieldConfig = $this->schemaRegistry->getSubjectField($fieldName);
            if (!$fieldConfig || !($fieldConfig['vocabulary_adapter'] ?? null)) {
                continue;
            }

            $adapterName = $fieldConfig['vocabulary_adapter'];
            $adapter = $this->schemaRegistry->getVocabularyAdapter($adapterName);

            // For Milestone A, we only process 'entity' type here (Enums are in Normalization)
            if (!$adapter || 'entity' !== $adapter['type']) {
                continue;
            }

            $uniqueValues = $this->getUniqueValuesForColumn($session, $column);

            foreach ($uniqueValues as $rawValue) {
                if (empty(trim((string) $rawValue))) {
                    continue;
                }

                $suggestion = $this->vocabularyResolver->resolve($adapterName, (string) $rawValue);
                if ($suggestion) {
                    $this->createProposal($session, $column, $targetField, (string) $rawValue, $suggestion);
                }
            }
        }

        $session->setVocabularyGeneratedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function getUniqueValuesForColumn(ImportSession $session, ImportColumn $column): array
    {
        $rows = $this->entityManager->getRepository(ImportRow::class)
            ->findBy(['session' => $session])
        ;

        $values = [];
        $headerName = $column->getHeaderName();

        foreach ($rows as $row) {
            $payload = $row->getSourcePayload();
            if (isset($payload[$headerName])) {
                $values[(string) $payload[$headerName]] = true;
            }
        }

        return array_keys($values);
    }

    private function createProposal(
        ImportSession $session,
        ImportColumn $column,
        string $targetField,
        string $originalValue,
        string $termId,
    ): void {
        $repo = $this->entityManager->getRepository(OntologyTermProposal::class);
        $existing = $repo->findOneBy([
            'session' => $session,
            'column' => $column,
            'targetField' => $targetField,
            'originalValue' => $originalValue,
        ]);

        if ($existing) {
            if ('undecided' === $existing->getDecisionStatus()) {
                $existing->setTermId($termId);
                $existing->setTermLabel($originalValue); // Fallback to original value as label for now
            }

            return;
        }

        $proposal = new OntologyTermProposal();
        $proposal->setSession($session);
        $proposal->setColumn($column);
        $proposal->setTargetField($targetField);
        $proposal->setOriginalValue($originalValue);
        $proposal->setTermId($termId);
        $proposal->setTermLabel($originalValue);
        $proposal->setDecisionStatus('undecided');
        $proposal->setSource('generated');

        $this->entityManager->persist($proposal);
    }
}
