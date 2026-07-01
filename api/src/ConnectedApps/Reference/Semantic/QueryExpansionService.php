<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Semantic;

use App\Lookup\ExternalLookupService;
use Psr\Log\LoggerInterface;

/**
 * Expands a search query with ontology synonyms / related labels (EFO, VT, MP, MA)
 * via {@see ExternalLookupService} so federated search can match resources that use
 * different vocabulary for the same concept (e.g. "blood sugar" → "blood glucose
 * level"). Network calls are wrapped in try/catch and the original query is always
 * preserved, so expansion can only ever ADD terms, never remove or replace them.
 *
 * Guarded by a feature flag in {@see \App\ConnectedApps\Reference\ReferenceSearchService};
 * when off (the default) this service is never called and search is byte-for-byte
 * unchanged. Expansion terms are capped to keep latency + token/HTTP cost bounded.
 */
final readonly class QueryExpansionService
{
    /** OLS ontologies queried for related labels, in priority order. */
    private const array ONTOLOGIES = ['ols_efo', 'ols_vt', 'ols_mp', 'ols_ma'];
    /** Hard cap on ADDED terms (the original is always kept, separately). */
    private const int MAX_EXPANSION_TERMS = 4;
    /** Per-ontology cap so one ontology can't crowd out the others. */
    private const int MAX_PER_ONTOLOGY = 2;

    public function __construct(
        private ExternalLookupService $lookupService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<string> the original query first, then up to MAX_EXPANSION_TERMS
     *                      distinct related labels (deduped, case-insensitively)
     */
    public function expand(string $query): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        $terms = [$query];
        $seen = [mb_strtolower($query) => true];

        foreach (self::ONTOLOGIES as $ontology) {
            if (\count($terms) - 1 >= self::MAX_EXPANSION_TERMS) {
                break;
            }

            foreach ($this->labelsFrom($ontology, $query) as $label) {
                $key = mb_strtolower($label);
                if (isset($seen[$key]) || '' === trim($label)) {
                    continue;
                }

                $seen[$key] = true;
                $terms[] = $label;

                if (\count($terms) - 1 >= self::MAX_EXPANSION_TERMS) {
                    break;
                }
            }
        }

        return $terms;
    }

    /**
     * @return list<string>
     */
    private function labelsFrom(string $ontology, string $query): array
    {
        try {
            $hits = $this->lookupService->search($ontology, $query);
        } catch (\Throwable $e) {
            // Network / upstream errors must never fail search — log and skip.
            $this->logger->warning('Query expansion lookup failed', [
                'ontology' => $ontology,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $labels = [];
        foreach (\array_slice($hits, 0, self::MAX_PER_ONTOLOGY) as $hit) {
            $label = trim($hit['label']);
            if ('' !== $label) {
                $labels[] = $label;
            }
        }

        return $labels;
    }
}
