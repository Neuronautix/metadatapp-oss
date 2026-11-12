<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Schema;

use App\AI\Gateway\ModelGatewayRegistry;
use App\AI\Gateway\ModelResponse;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Runtime\AssistantTask;
use App\AI\Runtime\AssistantTaskMapper;
use App\ConnectedApps\Reference\ReferenceResult;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates "AI Compare Schemas": extracts structured field lists from each
 * selected Reference Hub result (templates, schemas, guidelines), sends them to
 * the configured model gateway to produce a harmonized field-group mapping, and
 * assembles a {@see SchemaComparisonResult}.
 *
 * When the assistant is disabled or the gateway errors, degrades to a deterministic
 * label-similarity grouping so the Compare Schemas view still works (`aiAvailable: false`).
 */
final readonly class SchemaComparisonService
{
    private const int CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private AiFeatureFlags $featureFlags,
        private AssistantTaskMapper $taskMapper,
        private ModelGatewayRegistry $gatewayRegistry,
        private SchemaFieldExtractorService $extractor,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<ReferenceResult> $items
     */
    public function compare(array $items): SchemaComparisonResult
    {
        // Filter to schema-type items only
        $schemaItems = array_values(array_filter(
            $items,
            static fn (ReferenceResult $r): bool => \in_array($r->type, ['template', 'schema', 'guideline'], true),
        ));

        $warnings = [];
        if (\count($schemaItems) !== \count($items)) {
            $warnings[] = 'Some non-schema items were excluded from comparison.';
        }

        if ([] === $schemaItems) {
            return new SchemaComparisonResult(fieldGroups: [], aiAvailable: false, warnings: ['No schema or template items to compare.']);
        }

        $extractedSchemas = $this->extractor->extractAll($schemaItems);

        if (!$this->featureFlags->isAssistantEnabled()
            || !$this->featureFlags->allowsTask(AssistantTask::COMPARE_SCHEMAS)
            || !$this->gatewayRegistry->hasConfiguredGateway()
        ) {
            $warnings[] = 'AI schema comparison is unavailable; showing a label-based grouping.';

            return $this->deterministicComparison($extractedSchemas, $warnings);
        }

        $cacheItem = $this->cache->getItem($this->cacheKey($extractedSchemas));
        if ($cacheItem->isHit() && $cacheItem->get() instanceof SchemaComparisonResult) {
            return $cacheItem->get();
        }

        $mapped = $this->taskMapper->mapCompareSchemas($extractedSchemas);

        try {
            $response = $this->gatewayRegistry->getDefaultGateway()->invoke($mapped->request);
        } catch (\Throwable $e) {
            $this->logger->warning('Schema comparison gateway failed', ['error' => $e->getMessage()]);
            $warnings[] = 'AI provider error; showing a label-based grouping.';

            return $this->deterministicComparison($extractedSchemas, $warnings);
        }

        $trace = $mapped->trace->withProvider($response->provider, $response->model);
        $fieldGroups = $this->parseFieldGroups($response);
        if ([] === $fieldGroups) {
            $fieldGroups = $this->deterministicComparison($extractedSchemas, $warnings)->fieldGroups;
        }

        $result = new SchemaComparisonResult(
            fieldGroups: $fieldGroups,
            aiAvailable: $response->available,
            warnings: [...$warnings, ...$response->warnings],
            usage: $this->usageFrom($response),
        );

        $this->logger->info('AI schema comparison completed', [
            'trace' => $trace->toLogContext(),
            'field_group_count' => \count($fieldGroups),
            'warning_count' => \count($result->warnings),
        ]);

        $cacheItem->set($result)->expiresAfter(self::CACHE_TTL_SECONDS);
        $this->cache->save($cacheItem);

        return $result;
    }

    /**
     * Deterministic fallback: group fields by normalized label across schemas.
     *
     * @param list<array{sourceRef: string, sourceName: string, type: string, fields: list<array<string, mixed>>}> $extractedSchemas
     * @param list<string>                                                                                         $warnings
     */
    private function deterministicComparison(array $extractedSchemas, array $warnings): SchemaComparisonResult
    {
        /** @var list<array{groupId: string, canonicalLabel: string, canonicalDatatype: string|null, canonicalUnit: string|null, members: list<FieldGroupMember>, conflicts: list<array<string, mixed>>}> $groups */
        $groups = [];
        $groupIndex = 0;

        foreach ($extractedSchemas as $schema) {
            foreach ($schema['fields'] as $field) {
                $normalizedLabel = strtolower(preg_replace('/\s+/', '', (string) ($field['label'] ?? '')) ?? '');
                $found = false;
                foreach ($groups as &$group) {
                    $groupNorm = strtolower(preg_replace('/\s+/', '', $group['canonicalLabel']) ?? '');
                    if ('' !== $normalizedLabel && $groupNorm === $normalizedLabel) {
                        $group['members'][] = new FieldGroupMember(
                            sourceRef: (string) ($field['sourceRef'] ?? ''),
                            mappingType: 'close',
                            label: (string) ($field['label'] ?? ''),
                            evidence: 'Label similarity match',
                        );
                        $found = true;
                        break;
                    }
                }
                unset($group);
                if (!$found) {
                    $groups[] = [
                        'groupId' => 'fg-' . ++$groupIndex,
                        'canonicalLabel' => (string) ($field['label'] ?? ''),
                        'canonicalDatatype' => isset($field['datatype']) && '' !== $field['datatype'] ? (string) $field['datatype'] : null,
                        'canonicalUnit' => isset($field['unit']) && '' !== $field['unit'] ? (string) $field['unit'] : null,
                        'members' => [new FieldGroupMember(
                            sourceRef: (string) ($field['sourceRef'] ?? ''),
                            mappingType: 'exact',
                            label: (string) ($field['label'] ?? ''),
                            evidence: 'Primary occurrence',
                        )],
                        'conflicts' => [],
                    ];
                }
            }
        }

        $fieldGroups = array_map(
            static fn (array $g): FieldGroup => new FieldGroup(
                groupId: $g['groupId'],
                canonicalLabel: $g['canonicalLabel'],
                canonicalDatatype: $g['canonicalDatatype'],
                canonicalUnit: $g['canonicalUnit'],
                confidence: 0.5,
                members: $g['members'],
                conflicts: $g['conflicts'],
            ),
            $groups,
        );

        return new SchemaComparisonResult(fieldGroups: $fieldGroups, aiAvailable: false, warnings: $warnings);
    }

    /**
     * Parse the model's structured output into FieldGroup objects.
     *
     * @return list<FieldGroup>
     */
    private function parseFieldGroups(ModelResponse $response): array
    {
        $structured = $response->output['structured_output'] ?? null;
        $raw = \is_array($structured) ? ($structured['fieldGroups'] ?? null) : null;
        if (!\is_array($raw)) {
            return [];
        }

        $groups = [];
        foreach ($raw as $g) {
            if (!\is_array($g)) {
                continue;
            }
            $members = [];
            foreach ((array) ($g['members'] ?? []) as $m) {
                if (!\is_array($m)) {
                    continue;
                }
                $members[] = new FieldGroupMember(
                    sourceRef: (string) ($m['sourceRef'] ?? ''),
                    mappingType: (string) ($m['mappingType'] ?? 'related'),
                    label: (string) ($m['label'] ?? ''),
                    evidence: (string) ($m['evidence'] ?? ''),
                );
            }
            $canonicalDatatype = isset($g['canonicalDatatype']) && '' !== $g['canonicalDatatype'] ? (string) $g['canonicalDatatype'] : null;
            $canonicalUnit = isset($g['canonicalUnit']) && '' !== $g['canonicalUnit'] ? (string) $g['canonicalUnit'] : null;
            $groups[] = new FieldGroup(
                groupId: (string) ($g['groupId'] ?? ''),
                canonicalLabel: (string) ($g['canonicalLabel'] ?? ''),
                canonicalDatatype: $canonicalDatatype,
                canonicalUnit: $canonicalUnit,
                confidence: (float) ($g['confidence'] ?? 0.0),
                members: $members,
                conflicts: array_values(array_filter((array) ($g['conflicts'] ?? []), 'is_array')),
            );
        }

        return $groups;
    }

    /**
     * @param list<array{sourceRef: string, sourceName: string, type: string, fields: list<array<string, mixed>>}> $extractedSchemas
     */
    private function cacheKey(array $extractedSchemas): string
    {
        $sourceRefs = array_column($extractedSchemas, 'sourceRef');
        sort($sourceRefs);

        return 'schema_comparison.' . hash('sha256', json_encode([
            'sourceRefs' => $sourceRefs,
            'schemas' => $extractedSchemas,
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function usageFrom(ModelResponse $response): array
    {
        $usage = ['provider' => $response->provider, 'model' => $response->model];
        if (isset($response->output['usage']) && \is_array($response->output['usage'])) {
            $usage += $response->output['usage'];
        }

        return $usage;
    }
}
