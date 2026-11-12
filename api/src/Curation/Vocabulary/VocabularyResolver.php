<?php

declare(strict_types=1);

namespace App\Curation\Vocabulary;

use App\Curation\Registry\SchemaRegistry;
use Doctrine\ORM\EntityManagerInterface;

final readonly class VocabularyResolver
{
    public function __construct(
        private SchemaRegistry $schemaRegistry,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(string $adapterName, string $value): ?string
    {
        $adapter = $this->schemaRegistry->getVocabularyAdapter($adapterName);
        if (!$adapter) {
            return null;
        }

        return match ($adapter['type']) {
            'enum' => $this->resolveEnum($adapter, $value),
            'entity' => $this->resolveEntity($adapter, $value),
            default => null,
        };
    }

    private function resolveEnum(array $adapter, string $value): ?string
    {
        $class = $adapter['class'];
        $synonyms = $adapter['synonyms'] ?? [];
        $normalizedInput = strtolower(trim($value));

        // 1. Check direct match (value or name)
        foreach ($class::cases() as $case) {
            if (strtolower($case->value) === $normalizedInput || strtolower($case->name) === $normalizedInput) {
                return $case->value;
            }
        }

        // 2. Check synonyms
        foreach ($synonyms as $synonym => $actualValue) {
            if (strtolower((string) $synonym) === $normalizedInput) {
                return $actualValue;
            }
        }

        return null;
    }

    private function resolveEntity(array $adapter, string $value): ?string
    {
        $class = $adapter['class'];
        $normalizedInput = trim($value);

        /** @phpstan-ignore-next-line */
        $repository = $this->entityManager->getRepository($class);

        // Try common identifier fields
        foreach (['name', 'externalId', 'label', 'id'] as $field) {
            if (!property_exists($class, $field)) {
                continue;
            }

            try {
                $entity = $repository->findOneBy([$field => $normalizedInput]);
                if ($entity instanceof $class && method_exists($entity, 'getId')) {
                    return (string) $entity->getId();
                }
            } catch (\Exception) {
                // If Doctrine fails to convert (e.g. string to UUID), just skip this field
                continue;
            }
        }

        return null;
    }
}
