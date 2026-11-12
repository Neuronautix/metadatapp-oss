<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Standardization;

use App\AI\Gateway\ModelGatewayRegistry;
use App\AI\Gateway\ModelResponse;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Runtime\AssistantTask;
use App\AI\Runtime\AssistantTaskMapper;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates "AI Compare & Link": grounds each selected reference result against
 * canonical ontology terms, asks the configured model gateway to cluster the
 * cross-source equivalents and pick one canonical term per cluster, and assembles
 * a {@see StandardizationResult}.
 *
 * Mirrors {@see \App\AI\Runtime\AiAssistantService::chat()} for governance: it is a
 * model *read* gated by {@see AiFeatureFlags}; when the assistant is disabled, no
 * provider is configured, or the gateway errors, it degrades to a deterministic
 * grouping so the Compare view still works (`aiAvailable: false`).
 */
final readonly class ReferenceStandardizationService
{
    /** Default budget cap: standardizing more than this in one call wastes tokens. */
    private const int DEFAULT_MAX_ITEMS = 8;
    private const int CACHE_TTL_SECONDS = 3600;
    /** Cap on harmonized fields per cluster, to keep the response bounded. */
    private const int MAX_HARMONIZED_FIELDS = 30;

    public function __construct(
        private AiFeatureFlags $featureFlags,
        private AssistantTaskMapper $taskMapper,
        private ModelGatewayRegistry $gatewayRegistry,
        private OntologyGroundingService $grounding,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        /** Override the per-call item budget (e.g. the eval harness over a larger golden set). */
        private int $maxItems = self::DEFAULT_MAX_ITEMS,
    ) {
    }

    /**
     * @param list<mixed> $items federated ReferenceResult payloads (raw decoded JSON)
     */
    public function standardize(array $items): StandardizationResult
    {
        $items = array_values(array_filter($items, '\is_array'));
        $warnings = [];

        if (\count($items) > $this->maxItems) {
            $warnings[] = \sprintf('Only the first %d references were standardized (received %d).', $this->maxItems, \count($items));
            $items = \array_slice($items, 0, $this->maxItems);
        }

        if ([] === $items) {
            return new StandardizationResult([], false, $warnings);
        }

        if (!$this->featureFlags->isAssistantEnabled()
            || !$this->featureFlags->allowsTask(AssistantTask::STANDARDIZE_REFERENCES)
            || !$this->gatewayRegistry->hasConfiguredGateway()
        ) {
            $warnings[] = 'AI standardization is unavailable; showing a heuristic grouping.';

            return new StandardizationResult($this->fallbackClusters($items), false, $warnings);
        }

        $grounded = [];
        foreach ($items as $index => $item) {
            $grounded[$this->referenceId($item, $index)] = $this->grounding->candidatesFor($item);
        }

        $cacheItem = $this->cache->getItem($this->cacheKey($items, $grounded));
        if ($cacheItem->isHit() && $cacheItem->get() instanceof StandardizationResult) {
            return $cacheItem->get();
        }

        $mapped = $this->taskMapper->mapStandardize($items, $grounded);

        try {
            $response = $this->gatewayRegistry->getDefaultGateway()->invoke($mapped->request);
        } catch (\Throwable $e) {
            $this->logger->warning('Reference standardization gateway failed', ['error' => $e->getMessage()]);
            $warnings[] = 'AI provider error; showing a heuristic grouping.';

            return new StandardizationResult($this->fallbackClusters($items), false, $warnings);
        }

        $trace = $mapped->trace->withProvider($response->provider, $response->model);
        $clusters = $this->parseClusters($response, $items);
        if ([] === $clusters) {
            $clusters = $this->fallbackClusters($items);
        }

        $result = new StandardizationResult(
            clusters: $clusters,
            aiAvailable: $response->available,
            warnings: [...$warnings, ...$response->warnings],
            provenance: $response->provenance,
            usage: $this->usageFrom($response),
        );

        $this->logger->info('AI action completed', [
            'trace' => $trace->toLogContext(),
            'cluster_count' => \count($clusters),
            'warning_count' => \count($result->warnings),
        ]);

        $cacheItem->set($result)->expiresAfter(self::CACHE_TTL_SECONDS);
        $this->cache->save($cacheItem);

        return $result;
    }

    /**
     * Read clusters from the model's structured output, keeping only members that
     * were actually submitted.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return list<ReferenceCluster>
     */
    private function parseClusters(ModelResponse $response, array $items): array
    {
        $structured = $response->output['structured_output'] ?? null;
        $raw = \is_array($structured) ? ($structured['clusters'] ?? null) : null;
        if (!\is_array($raw)) {
            return [];
        }

        $knownIds = [];
        foreach ($items as $index => $item) {
            $knownIds[$this->referenceId($item, $index)] = true;
        }

        $clusters = [];
        foreach ($raw as $position => $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $members = array_values(array_filter(
                array_map(static fn (mixed $id): string => trim((string) $id), \is_array($entry['memberRefIds'] ?? null) ? $entry['memberRefIds'] : []),
                static fn (string $id): bool => isset($knownIds[$id]),
            ));
            if ([] === $members) {
                continue;
            }

            $canonical = \is_array($entry['canonicalTerm'] ?? null) ? CanonicalTerm::fromArray($entry['canonicalTerm']) : null;

            $harmonized = $this->harmonizeFields($members, $items);
            $harmonized = $this->mergeModelHarmonization($harmonized, $entry['harmonizedFields'] ?? null);

            $clusters[] = new ReferenceCluster(
                clusterId: trim((string) ($entry['clusterId'] ?? '')) ?: 'cluster-' . ($position + 1),
                canonicalTerm: $canonical,
                memberRefIds: $members,
                evidence: $this->stringList($entry['evidence'] ?? []),
                proposedRecord: \is_array($entry['proposedRecord'] ?? null) ? $entry['proposedRecord'] : $this->proposedRecordFor($members, $items),
                confidence: null !== $canonical ? $canonical->confidence : 0.0,
                harmonizedFields: $harmonized,
            );
        }

        return $clusters;
    }

    /**
     * Deterministic grouping used when AI is unavailable or returns nothing: cluster
     * by normalized title so identical concepts still collapse, otherwise singletons.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return list<ReferenceCluster>
     */
    private function fallbackClusters(array $items): array
    {
        $groups = [];
        foreach ($items as $index => $item) {
            $signature = $this->normalizeTitle((string) ($item['title'] ?? '')) ?: ('ref-' . $index);
            $groups[$signature][] = $this->referenceId($item, $index);
        }

        $clusters = [];
        $position = 0;
        foreach ($groups as $members) {
            ++$position;
            $clusters[] = new ReferenceCluster(
                clusterId: 'cluster-' . $position,
                canonicalTerm: null,
                memberRefIds: $members,
                evidence: ['Grouped by normalized title (AI unavailable).'],
                proposedRecord: $this->proposedRecordFor($members, $items),
                confidence: 0.0,
                harmonizedFields: $this->harmonizeFields($members, $items),
            );
        }

        return $clusters;
    }

    /**
     * Build a standardized record from the first member, carrying its member ids.
     *
     * @param list<string>               $members
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function proposedRecordFor(array $members, array $items): array
    {
        $first = null;
        foreach ($items as $index => $item) {
            if (\in_array($this->referenceId($item, $index), $members, true)) {
                $first = $item;
                break;
            }
        }

        return [
            'title' => (string) ($first['title'] ?? ''),
            'type' => (string) ($first['type'] ?? ''),
            'description' => $first['description'] ?? null,
            'memberRefIds' => $members,
        ];
    }

    /**
     * Reconcile each metadata field across the cluster's sources: the chosen value is
     * the most common one; if the sources disagree the field is flagged as a conflict
     * and every distinct per-source value is preserved (nothing is silently dropped).
     *
     * @param list<string>               $members
     * @param list<array<string, mixed>> $items
     *
     * @return list<HarmonizedField>
     */
    private function harmonizeFields(array $members, array $items): array
    {
        /** @var array<string, list<array{source: string, value: string}>> $byField */
        $byField = [];
        foreach ($items as $index => $item) {
            if (!\in_array($this->referenceId($item, $index), $members, true)) {
                continue;
            }
            $source = trim((string) ($item['sourceName'] ?? $item['source'] ?? 'unknown'));
            foreach ($this->scalarFields($item) as $field => $value) {
                $byField[$field][] = ['source' => $source, 'value' => $value];
            }
        }

        $harmonized = [];
        foreach ($byField as $field => $entries) {
            $values = array_map(static fn (array $e): string => $e['value'], $entries);
            $distinct = array_values(array_unique($values));

            $harmonized[] = new HarmonizedField(
                field: $field,
                value: $this->mostCommon($values),
                conflict: \count($distinct) > 1,
                sourceValues: $this->distinctSourceValues($entries),
            );

            if (\count($harmonized) >= self::MAX_HARMONIZED_FIELDS) {
                break;
            }
        }

        return $harmonized;
    }

    /**
     * Apply the model's per-field overrides (chosen value + rationale) onto the
     * deterministic harmonization, keeping the deterministic conflict flag and the
     * per-source values intact.
     *
     * @param list<HarmonizedField> $harmonized
     *
     * @return list<HarmonizedField>
     */
    private function mergeModelHarmonization(array $harmonized, mixed $modelFields): array
    {
        if (!\is_array($modelFields) || [] === $harmonized) {
            return $harmonized;
        }

        $overrides = [];
        foreach ($modelFields as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $field = trim((string) ($entry['field'] ?? ''));
            if ('' === $field) {
                continue;
            }
            $overrides[$field] = [
                'value' => isset($entry['value']) && \is_scalar($entry['value']) ? trim((string) $entry['value']) : null,
                'rationale' => isset($entry['rationale']) && \is_scalar($entry['rationale']) ? trim((string) $entry['rationale']) : null,
            ];
        }

        return array_map(static function (HarmonizedField $f) use ($overrides): HarmonizedField {
            $override = $overrides[$f->field] ?? null;
            if (null === $override) {
                return $f;
            }

            return new HarmonizedField(
                field: $f->field,
                value: null !== $override['value'] && '' !== $override['value'] ? $override['value'] : $f->value,
                conflict: $f->conflict,
                sourceValues: $f->sourceValues,
                rationale: $override['rationale'],
            );
        }, $harmonized);
    }

    /**
     * Scalar, non-empty fields of a reference: its description plus identifiers/raw.
     *
     * @param array<string, mixed> $item
     *
     * @return array<string, string>
     */
    private function scalarFields(array $item): array
    {
        $fields = [];
        $description = $item['description'] ?? null;
        if (\is_string($description) && '' !== trim($description)) {
            $fields['description'] = trim($description);
        }

        foreach (['identifiers', 'raw'] as $bag) {
            $data = $item[$bag] ?? null;
            if (!\is_array($data)) {
                continue;
            }
            foreach ($data as $key => $value) {
                if (\is_scalar($value) && '' !== trim((string) $value)) {
                    $fields[(string) $key] = trim((string) $value);
                }
            }
        }

        return $fields;
    }

    /**
     * @param list<string> $values
     */
    private function mostCommon(array $values): string
    {
        $counts = array_count_values($values);
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * Distinct (source, value) pairs, preserving order — so a field shows which
     * source contributed which value without repeating identical pairs.
     *
     * @param list<array{source: string, value: string}> $entries
     *
     * @return list<array{source: string, value: string}>
     */
    private function distinctSourceValues(array $entries): array
    {
        $seen = [];
        $out = [];
        foreach ($entries as $entry) {
            $key = $entry['source'] . "\0" . $entry['value'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function referenceId(array $item, int $index): string
    {
        $id = trim((string) ($item['id'] ?? ''));

        return '' !== $id ? $id : 'item-' . $index;
    }

    private function normalizeTitle(string $title): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(trim($title))) ?? '';

        return trim($normalized);
    }

    /**
     * @param list<array<string, mixed>>                                                                             $items
     * @param array<string, list<array{iri: string, label: string, ontology: string, score: float, source: string}>> $grounded
     */
    private function cacheKey(array $items, array $grounded): string
    {
        $ids = array_map(fn (array $item, int $index): string => $this->referenceId($item, $index), $items, array_keys($items));
        sort($ids);

        return 'reference_standardization.' . hash('sha256', json_encode([
            'ids' => $ids,
            'items' => $items,
            'grounded' => $grounded,
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

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $entry): string => trim((string) (\is_scalar($entry) ? $entry : '')), $value),
            static fn (string $entry): bool => '' !== $entry,
        ));
    }
}
