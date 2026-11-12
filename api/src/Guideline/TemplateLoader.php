<?php

declare(strict_types=1);

namespace App\Guideline;

use App\Guideline\Schema\GuidelineTemplate;
use App\Guideline\Schema\RequiredColumn;
use App\Guideline\Schema\RequiredMetadata;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads and parses the guideline template YAML files (§2/§3), validates their
 * shape, and resolves `conforms_to` recursively with a child-wins merge (§3.1).
 *
 * Hard errors (per §9): a missing parent, a cyclic `conforms_to`, or a
 * structurally invalid template (missing id, unknown severity, …). Resolved
 * templates are cached in-memory keyed by id.
 */
final class TemplateLoader
{
    private const array VALID_SEVERITIES = ['high', 'medium', 'low'];

    /** @var array<string, GuidelineTemplate>|null raw (unmerged) templates keyed by id */
    private ?array $raw = null;

    /** @var array<string, GuidelineTemplate> resolved (merged) templates keyed by id */
    private array $resolvedCache = [];

    public function __construct(
        private readonly string $guidelinesDir,
    ) {
    }

    /**
     * @return list<string>
     */
    public function templateIds(): array
    {
        return array_keys($this->rawTemplates());
    }

    /**
     * Resolve a single template id to its fully merged form (§3).
     */
    public function load(string $templateId): GuidelineTemplate
    {
        return $this->resolve($templateId, []);
    }

    /**
     * @return list<GuidelineTemplate> every resolved template
     */
    public function loadAll(): array
    {
        return array_map(
            fn (string $id): GuidelineTemplate => $this->load($id),
            $this->templateIds(),
        );
    }

    public function has(string $templateId): bool
    {
        return isset($this->rawTemplates()[$templateId]);
    }

    /**
     * @param list<string> $stack ids currently being resolved (cycle detection)
     */
    private function resolve(string $templateId, array $stack): GuidelineTemplate
    {
        if (isset($this->resolvedCache[$templateId])) {
            return $this->resolvedCache[$templateId];
        }

        $raw = $this->rawTemplates();
        if (!isset($raw[$templateId])) {
            throw new \RuntimeException(\sprintf('Guideline template "%s" does not exist.', $templateId));
        }

        if (\in_array($templateId, $stack, true)) {
            throw new \RuntimeException(\sprintf('Cyclic conforms_to detected: %s -> %s.', implode(' -> ', $stack), $templateId));
        }

        $template = $raw[$templateId];

        // Start from the child's own fields (child wins on id collision).
        $columns = [];
        $columnIds = [];
        foreach ($template->requiredColumns as $col) {
            $columns[] = $col;
            $columnIds[$col->id] = true;
        }

        $metadata = [];
        $metadataIds = [];
        foreach ($template->requiredMetadata as $meta) {
            $metadata[] = $meta;
            $metadataIds[$meta->id] = true;
        }

        foreach ($template->conformsTo as $parentId) {
            $parent = $this->resolve($parentId, [...$stack, $templateId]);

            foreach ($parent->requiredColumns as $col) {
                if (!isset($columnIds[$col->id])) {
                    $columns[] = $col;
                    $columnIds[$col->id] = true;
                }
            }
            foreach ($parent->requiredMetadata as $meta) {
                if (!isset($metadataIds[$meta->id])) {
                    $metadata[] = $meta;
                    $metadataIds[$meta->id] = true;
                }
            }
        }

        $resolved = $template->withMergedFields($columns, $metadata);
        $this->resolvedCache[$templateId] = $resolved;

        return $resolved;
    }

    /**
     * @return array<string, GuidelineTemplate>
     */
    private function rawTemplates(): array
    {
        if (null !== $this->raw) {
            return $this->raw;
        }

        $files = glob(rtrim($this->guidelinesDir, '/') . '/*.yaml');
        if (false === $files) {
            $files = [];
        }

        $raw = [];
        foreach ($files as $file) {
            $template = $this->parseFile($file);
            if (isset($raw[$template->id])) {
                throw new \RuntimeException(\sprintf('Duplicate guideline template id "%s".', $template->id));
            }
            $raw[$template->id] = $template;
        }

        // Validate that every declared parent exists (missing parent is a hard error, §3.1).
        foreach ($raw as $template) {
            foreach ($template->conformsTo as $parentId) {
                if (!isset($raw[$parentId])) {
                    throw new \RuntimeException(\sprintf('Guideline template "%s" conforms_to unknown parent "%s".', $template->id, $parentId));
                }
            }
        }

        return $this->raw = $raw;
    }

    private function parseFile(string $path): GuidelineTemplate
    {
        $data = Yaml::parseFile($path);
        if (!\is_array($data)) {
            throw new \RuntimeException(\sprintf('Guideline file "%s" is not a YAML mapping.', $path));
        }

        $id = $this->requireString($data, 'id', $path);
        $name = $this->requireString($data, 'name', $path);
        $version = isset($data['version']) ? (string) $data['version'] : '1.0';

        $conformsTo = [];
        foreach ($this->asList($data['conforms_to'] ?? []) as $parent) {
            $conformsTo[] = (string) $parent;
        }

        $ontology = [];
        if (isset($data['ontology']) && \is_array($data['ontology'])) {
            /** @var array{iri?: string, prefix?: string} $ontology */
            $ontology = $data['ontology'];
        }

        $fairBonus = null;
        if (isset($data['fair_bonus']) && \is_array($data['fair_bonus'])) {
            $fairBonus = [
                'dimension' => (string) ($data['fair_bonus']['dimension'] ?? 'R'),
                'max_points' => (int) ($data['fair_bonus']['max_points'] ?? 5),
            ];
        }

        $requiredColumns = $this->parseColumns($data['required_columns'] ?? [], $id, $path, true);
        $optionalColumns = $this->parseColumns($data['optional_columns'] ?? [], $id, $path, false);
        $requiredMetadata = $this->parseMetadata($data['required_metadata'] ?? [], $id, $path);

        return new GuidelineTemplate(
            id: $id,
            name: $name,
            version: $version,
            description: isset($data['description']) ? (string) $data['description'] : '',
            identifier: isset($data['identifier']) ? (string) $data['identifier'] : null,
            conformsTo: $conformsTo,
            ontology: $ontology,
            requiredColumns: $requiredColumns,
            optionalColumns: $optionalColumns,
            requiredMetadata: $requiredMetadata,
            fairBonus: $fairBonus,
            source: isset($data['source']) ? (string) $data['source'] : 'builtin',
        );
    }

    /**
     * @return list<RequiredColumn>
     */
    private function parseColumns(mixed $raw, string $templateId, string $path, bool $defaultRequired): array
    {
        $columns = [];
        foreach ($this->asList($raw) as $entry) {
            if (!\is_array($entry)) {
                throw new \RuntimeException(\sprintf('Invalid column entry in template "%s" (%s).', $templateId, $path));
            }
            $colId = $this->requireString($entry, 'id', $path);
            $patterns = [];
            foreach ($this->asList($entry['name_patterns'] ?? []) as $pattern) {
                $patterns[] = (string) $pattern;
            }
            if ([] === $patterns) {
                throw new \RuntimeException(\sprintf('Column "%s" in template "%s" has no name_patterns.', $colId, $templateId));
            }
            $severity = (string) ($entry['severity'] ?? 'medium');
            $this->assertSeverity($severity, $colId, $templateId);

            $columns[] = new RequiredColumn(
                id: $colId,
                namePatterns: $patterns,
                semanticType: isset($entry['semantic_type']) ? (string) $entry['semantic_type'] : null,
                role: isset($entry['role']) ? (string) $entry['role'] : null,
                arriveSection: isset($entry['arrive_section']) ? (string) $entry['arrive_section'] : null,
                unitRequired: (bool) ($entry['unit_required'] ?? false),
                unitColumn: isset($entry['unit_column']) ? (string) $entry['unit_column'] : null,
                required: (bool) ($entry['required'] ?? $defaultRequired),
                severity: $severity,
            );
        }

        return $columns;
    }

    /**
     * @return list<RequiredMetadata>
     */
    private function parseMetadata(mixed $raw, string $templateId, string $path): array
    {
        $metadata = [];
        foreach ($this->asList($raw) as $entry) {
            if (!\is_array($entry)) {
                throw new \RuntimeException(\sprintf('Invalid metadata entry in template "%s" (%s).', $templateId, $path));
            }
            $fieldId = $this->requireString($entry, 'id', $path);
            $crosswalk = [];
            foreach ($this->asList($entry['crosswalk'] ?? []) as $target) {
                $crosswalk[] = (string) $target;
            }
            $severity = (string) ($entry['severity'] ?? 'medium');
            $this->assertSeverity($severity, $fieldId, $templateId);

            $metadata[] = new RequiredMetadata(
                id: $fieldId,
                arriveSection: isset($entry['arrive_section']) ? (string) $entry['arrive_section'] : null,
                prepareSection: isset($entry['prepare_section']) ? (string) $entry['prepare_section'] : null,
                eqipdSection: isset($entry['eqipd_section']) ? (string) $entry['eqipd_section'] : null,
                guidance: isset($entry['guidance']) ? (string) $entry['guidance'] : null,
                prompt: isset($entry['prompt']) ? (string) $entry['prompt'] : null,
                crosswalk: $crosswalk,
                severity: $severity,
            );
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireString(array $data, string $key, string $path): string
    {
        if (!isset($data[$key]) || !\is_scalar($data[$key]) || '' === (string) $data[$key]) {
            throw new \RuntimeException(\sprintf('Missing required "%s" in guideline file "%s".', $key, $path));
        }

        return (string) $data[$key];
    }

    private function assertSeverity(string $severity, string $fieldId, string $templateId): void
    {
        if (!\in_array($severity, self::VALID_SEVERITIES, true)) {
            throw new \RuntimeException(\sprintf('Invalid severity "%s" on field "%s" in template "%s".', $severity, $fieldId, $templateId));
        }
    }

    /**
     * @return list<mixed>
     */
    private function asList(mixed $value): array
    {
        if (\is_array($value)) {
            return array_values($value);
        }

        return [];
    }
}
