<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Curation\Registry\SchemaRegistry;
use App\Curation\Vocabulary\VocabularyResolver;
use App\Entity\ImportColumn;
use App\Entity\ImportRow;
use App\Entity\ImportSession;
use App\Entity\SchemaFieldMappingProposal;
use App\Entity\ValueNormalizationProposal;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GenerateNormalizationProposals
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SchemaRegistry $schemaRegistry,
        private VocabularyResolver $vocabularyResolver,
    ) {
    }

    public function execute(ImportSession $session): void
    {
        // 1. Get all active schema mapping proposals (approved or undecided)
        $mappingProposals = $this->entityManager->getRepository(SchemaFieldMappingProposal::class)
            ->findBy(['session' => $session])
        ;

        foreach ($mappingProposals as $mapping) {
            if ('rejected' === $mapping->getDecisionStatus()) {
                continue;
            }

            $column = $mapping->getColumn();
            $targetField = $mapping->getTargetField();

            // Extract the field basename (e.g., "Subject.species" -> "species")
            $fieldParts = explode('.', $targetField);
            $fieldName = end($fieldParts);

            // Get registry config for this subject field
            $fieldConfig = $this->schemaRegistry->getSubjectField($fieldName);
            if (!$fieldConfig || !($fieldConfig['vocabulary_adapter'] ?? null)) {
                continue;
            }

            $adapterName = $fieldConfig['vocabulary_adapter'];

            // 2. Identify unique values for this column in the session rows
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

        $session->setNormalizationsGeneratedAt(new \DateTimeImmutable());
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
        string $normalizedValue,
    ): void {
        $repo = $this->entityManager->getRepository(ValueNormalizationProposal::class);
        $existing = $repo->findOneBy([
            'session' => $session,
            'column' => $column,
            'targetField' => $targetField,
            'originalValue' => $originalValue,
        ]);

        if ($existing) {
            // Un-rejected if suggested again? We keep user decision if present
            if ('undecided' === $existing->getDecisionStatus()) {
                $existing->setNormalizedValue($normalizedValue);
            }

            return;
        }

        $proposal = new ValueNormalizationProposal();
        $proposal->setSession($session);
        $proposal->setColumn($column);
        $proposal->setTargetField($targetField);
        $proposal->setOriginalValue($originalValue);
        $proposal->setNormalizedValue($normalizedValue);
        $proposal->setDecisionStatus('undecided');
        $proposal->setSource('generated');

        $this->entityManager->persist($proposal);
    }
}
