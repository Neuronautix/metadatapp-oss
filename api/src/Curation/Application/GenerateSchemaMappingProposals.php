<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Entity\ImportSession;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GenerateSchemaMappingProposals
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private \App\Curation\Registry\SchemaRegistry $schemaRegistry,
    ) {
    }

    /**
     * Proposal generation services upsert undecided proposals by natural key and never rewrite decided proposals.
     */
    public function execute(ImportSession $session): void
    {
        $columns = $this->entityManager->getRepository(\App\Entity\ImportColumn::class)->findBy(['session' => $session]);
        $entities = $this->schemaRegistry->getAllEntities();

        foreach ($columns as $column) {
            $header = $column->getHeaderName();
            $normalizedHeader = $this->normalizeHeader($header);

            foreach ($entities as $entityName => $entityConfig) {
                $fields = $entityConfig['fields'] ?? [];
                foreach ($fields as $fieldName => $config) {
                    if ($this->isMatch($normalizedHeader, $fieldName, $config['aliases'] ?? [])) {
                        // Ensure entity name starts with a capital letter
                        $entityPrefix = ucfirst($entityName);
                        $this->createProposal($session, $column, $entityPrefix . '.' . $fieldName);
                    }
                }
            }
        }

        $session->setSchemaMappingsGeneratedAt(new \DateTimeImmutable());
        $session->setStatus(\App\Enum\ImportSessionStatus::ReviewReady);
        $this->entityManager->flush();
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim(str_replace([' ', '_', '-'], '', $header)));
    }

    private function isMatch(string $normalizedHeader, string $fieldName, array $aliases): bool
    {
        $normalizedFieldName = $this->normalizeHeader($fieldName);
        if ($normalizedHeader === $normalizedFieldName) {
            return true;
        }

        foreach ($aliases as $alias) {
            if ($normalizedHeader === $this->normalizeHeader($alias)) {
                return true;
            }
        }

        return false;
    }

    private function createProposal(ImportSession $session, \App\Entity\ImportColumn $column, string $targetField): void
    {
        // Check if proposal already exists
        $existing = $this->entityManager->getRepository(\App\Entity\SchemaFieldMappingProposal::class)->findOneBy([
            'session' => $session,
            'column' => $column,
            'targetField' => $targetField,
        ]);

        if ($existing) {
            return;
        }

        $proposal = new \App\Entity\SchemaFieldMappingProposal();
        $proposal->setSession($session);
        $proposal->setColumn($column);
        $proposal->setTargetField($targetField);
        $proposal->setDecisionStatus('undecided');
        $proposal->setSource('generated');

        $this->entityManager->persist($proposal);
    }
}
