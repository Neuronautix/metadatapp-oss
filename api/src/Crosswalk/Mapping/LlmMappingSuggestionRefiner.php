<?php

declare(strict_types=1);

namespace App\Crosswalk\Mapping;

use App\AI\Gateway\ModelGatewayRegistry;
use App\AI\Gateway\ModelResponse;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Runtime\AssistantTask;
use App\AI\Runtime\AssistantTaskMapper;
use App\Crosswalk\Cde\CdeCandidate;
use App\Entity\CommonDataElement;
use App\Entity\ExtractedTemplateField;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * LLM refiner for the deterministic {@see MappingSuggestionEngine}: asks the
 * configured model gateway to re-rank the top candidate CDE mappings for a field
 * and attach human-readable evidence, leaving the deterministic engine untouched.
 *
 * Governance, mirroring the rest of the AI layer: gated by {@see AiFeatureFlags}
 * and a configured gateway. When AI is disabled, the task is not allowed, the
 * gateway errors, or the model returns nothing usable, it returns the
 * deterministic suggestions unchanged — so swapping the DI alias from
 * {@see NullMappingSuggestionRefiner} is safe by default (off → identical no-op).
 */
final readonly class LlmMappingSuggestionRefiner implements MappingSuggestionRefinerInterface
{
    /** Token budget: only the strongest deterministic candidates are sent to the model. */
    private const int MAX_CANDIDATES = 10;
    private const int CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private AiFeatureFlags $featureFlags,
        private AssistantTaskMapper $taskMapper,
        private ModelGatewayRegistry $gatewayRegistry,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function refine(array $suggestions, ExtractedTemplateField $field): array
    {
        // Nothing to re-rank, or AI is unavailable: behave exactly like the no-op refiner.
        if (\count($suggestions) < 2
            || !$this->featureFlags->isAssistantEnabled()
            || !$this->featureFlags->allowsTask(AssistantTask::REFINE_CROSSWALK_MAPPING)
            || !$this->gatewayRegistry->hasConfiguredGateway()
        ) {
            return $suggestions;
        }

        $considered = \array_slice($suggestions, 0, self::MAX_CANDIDATES);
        $tail = \array_slice($suggestions, self::MAX_CANDIDATES);

        $payload = $this->buildPayload($field, $considered);
        $cacheItem = $this->cache->getItem($this->cacheKey($payload));
        if ($cacheItem->isHit()) {
            $cached = $this->normalizeRankings($cacheItem->get(), \count($considered));
            if ([] !== $cached) {
                return [...$this->applyRankings($cached, $considered), ...$tail];
            }
        }

        $mapped = $this->taskMapper->mapRefineCrosswalkMapping($payload);

        try {
            $response = $this->gatewayRegistry->getDefaultGateway()->invoke($mapped->request);
        } catch (\Throwable $e) {
            $this->logger->warning('Crosswalk mapping refiner gateway failed', ['error' => $e->getMessage()]);

            return $suggestions;
        }

        $rankings = $this->normalizeRankings($this->rankingsFrom($response), \count($considered));
        if ([] === $rankings) {
            return $suggestions;
        }

        $cacheItem->set($rankings)->expiresAfter(self::CACHE_TTL_SECONDS);
        $this->cache->save($cacheItem);

        $trace = $mapped->trace->withProvider($response->provider, $response->model);
        $this->logger->info('AI action completed', [
            'trace' => $trace->toLogContext(),
            'candidate_count' => \count($considered),
        ]);

        return [...$this->applyRankings($rankings, $considered), ...$tail];
    }

    /**
     * Rebuild the suggestion list in the model's ranked order, adopting its
     * confidence (clamped) and appending its evidence to the deterministic reasons.
     * Candidates the model omitted keep their deterministic order at the end, so no
     * suggestion is ever dropped.
     *
     * @param list<array{ref: int, confidence: ?float, evidence: list<string>}> $rankings
     * @param list<MappingSuggestion>                                           $considered
     *
     * @return list<MappingSuggestion>
     */
    private function applyRankings(array $rankings, array $considered): array
    {
        $ordered = [];
        $used = [];
        foreach ($rankings as $ranking) {
            $ref = $ranking['ref'];
            if (!isset($considered[$ref]) || isset($used[$ref])) {
                continue;
            }
            $used[$ref] = true;
            $ordered[] = $this->withRefinement($considered[$ref], $ranking['confidence'], $ranking['evidence']);
        }

        foreach ($considered as $ref => $suggestion) {
            if (!isset($used[$ref])) {
                $ordered[] = $suggestion;
            }
        }

        return $ordered;
    }

    /**
     * @param list<string> $evidence
     */
    private function withRefinement(MappingSuggestion $suggestion, ?float $confidence, array $evidence): MappingSuggestion
    {
        $reasons = $suggestion->reasons;
        foreach ($evidence as $line) {
            $reasons[] = 'AI: ' . $line;
        }

        return new MappingSuggestion(
            cde: $suggestion->cde,
            confidence: null !== $confidence ? max(0.0, min(1.0, $confidence)) : $suggestion->confidence,
            reasons: $reasons,
            risks: $suggestion->risks,
            mappingType: $suggestion->mappingType,
        );
    }

    /**
     * @param list<MappingSuggestion> $considered
     *
     * @return array<string, mixed>
     */
    private function buildPayload(ExtractedTemplateField $field, array $considered): array
    {
        return [
            'field' => [
                'label' => $field->getLabel(),
                'propertyId' => $field->getPropertyId(),
                'datatype' => $field->getDatatype(),
                'unit' => $field->getUnit(),
                'ontologyTerms' => $field->getOntologyTerms(),
            ],
            'candidates' => array_map(
                fn (int $ref, MappingSuggestion $s): array => [
                    'ref' => $ref,
                    'label' => $this->cdeLabel($s->cde),
                    'confidence' => $s->confidence,
                    'mappingType' => $s->mappingType->value,
                    'reasons' => $s->reasons,
                    'risks' => $s->risks,
                ],
                array_keys($considered),
                $considered,
            ),
        ];
    }

    private function rankingsFrom(ModelResponse $response): mixed
    {
        $structured = $response->output['structured_output'] ?? null;

        return \is_array($structured) ? ($structured['rankings'] ?? null) : null;
    }

    /**
     * @return list<array{ref: int, confidence: ?float, evidence: list<string>}>
     */
    private function normalizeRankings(mixed $raw, int $candidateCount): array
    {
        if (!\is_array($raw)) {
            return [];
        }

        $rankings = [];
        foreach ($raw as $entry) {
            if (!\is_array($entry) || !isset($entry['ref']) || !is_numeric($entry['ref'])) {
                continue;
            }
            $ref = (int) $entry['ref'];
            if ($ref < 0 || $ref >= $candidateCount) {
                continue;
            }
            $rankings[] = [
                'ref' => $ref,
                'confidence' => isset($entry['confidence']) && is_numeric($entry['confidence']) ? (float) $entry['confidence'] : null,
                'evidence' => $this->stringList($entry['evidence'] ?? []),
            ];
        }

        return $rankings;
    }

    private function cdeLabel(CommonDataElement|CdeCandidate $cde): string
    {
        return $cde instanceof CommonDataElement ? $cde->getLabel() : $cde->label;
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
            array_map(static fn (mixed $entry): string => \is_scalar($entry) ? trim((string) $entry) : '', $value),
            static fn (string $entry): bool => '' !== $entry,
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function cacheKey(array $payload): string
    {
        return 'crosswalk_mapping_refine.' . hash('sha256', json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
