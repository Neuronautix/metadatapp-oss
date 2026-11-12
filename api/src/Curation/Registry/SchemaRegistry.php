<?php

declare(strict_types=1);

namespace App\Curation\Registry;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

final readonly class SchemaRegistry
{
    private array $config;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ) {
        $path = $projectDir . '/config/curation_registry.yaml';
        $this->config = Yaml::parseFile($path);
    }

    public function getAllEntities(): array
    {
        return $this->config['curation'] ?? [];
    }

    public function getSubjectFields(): array
    {
        return $this->config['curation']['subject']['fields'] ?? [];
    }

    public function getSubjectField(string $fieldName): ?array
    {
        return $this->config['curation']['subject']['fields'][$fieldName] ?? null;
    }

    public function getResolverOnlyFields(): array
    {
        return array_filter(
            $this->getSubjectFields(),
            static fn (array $field) => 'resolver-only' === ($field['role'] ?? '')
        );
    }

    public function getPatchableFields(): array
    {
        return array_filter(
            $this->getSubjectFields(),
            static fn (array $field) => 'patchable' === ($field['role'] ?? '')
        );
    }

    public function getVocabularyAdapter(string $adapterName): ?array
    {
        return $this->config['vocabulary_adapters'][$adapterName] ?? null;
    }
}
