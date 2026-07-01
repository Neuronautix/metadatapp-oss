<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Standardization;

use App\CurateGpt\Client\CurateGptClientInterface;
use App\CurateGpt\Client\Dto\CurateGptCandidateDto;
use App\CurateGpt\Client\Dto\CurateGptSearchRequestDto;
use App\Lookup\ExternalLookupService;
use Psr\Log\LoggerInterface;

/**
 * Gathers candidate ontology terms for a single reference result by querying both
 * the deterministic OLS lookups ({@see ExternalLookupService}) and the CurateGPT
 * knowledge-graph search, merging and de-duplicating by IRI. The merged candidates
 * are fed to the model so it only has to *select and justify* a canonical term
 * rather than invent IRIs.
 */
final readonly class OntologyGroundingService
{
    private const int CURATE_GPT_LIMIT = 5;

    /**
     * Which OLS ontologies to consult per reference type. Measures/procedures map
     * best to VT (Vertebrate Trait), MP (Mammalian Phenotype) and EFO; strains are
     * grounded via CurateGPT's NCBITaxon collection only.
     *
     * @var array<string, list<array{source: string, ontology: string}>>
     */
    private const array OLS_BY_TYPE = [
        'measure' => [
            ['source' => 'ols_vt', 'ontology' => 'vt'],
            ['source' => 'ols_mp', 'ontology' => 'mp'],
            ['source' => 'ols_efo', 'ontology' => 'efo'],
        ],
        'procedure' => [
            ['source' => 'ols_mp', 'ontology' => 'mp'],
            ['source' => 'ols_efo', 'ontology' => 'efo'],
        ],
        'pipeline' => [
            ['source' => 'ols_efo', 'ontology' => 'efo'],
        ],
        'cde_element' => [
            ['source' => 'ols_efo', 'ontology' => 'efo'],
        ],
        'protocol' => [
            ['source' => 'ols_efo', 'ontology' => 'efo'],
        ],
    ];

    /**
     * @var list<array{source: string, ontology: string}>
     */
    private const array OLS_DEFAULT = [
        ['source' => 'ols_efo', 'ontology' => 'efo'],
    ];

    public function __construct(
        private ExternalLookupService $lookup,
        private CurateGptClientInterface $curateGpt,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $item a ReferenceResult-shaped payload
     *
     * @return list<array{iri: string, label: string, ontology: string, score: float, source: string}>
     */
    public function candidatesFor(array $item): array
    {
        $query = trim((string) ($item['title'] ?? ''));
        if ('' === $query) {
            return [];
        }

        $type = mb_strtolower(trim((string) ($item['type'] ?? '')));
        $candidates = [];

        foreach (self::OLS_BY_TYPE[$type] ?? self::OLS_DEFAULT as $ols) {
            foreach ($this->safeOlsSearch($ols['source'], $query) as $rank => $result) {
                $iri = trim((string) ($result['externalId'] ?? ''));
                if ('' === $iri) {
                    continue;
                }
                $candidates[] = [
                    'iri' => $iri,
                    'label' => (string) $result['label'],
                    'ontology' => $ols['ontology'],
                    // OLS returns best-match-first but no numeric score; rank-decay it.
                    'score' => round(max(0.1, 1.0 - ($rank * 0.1)), 3),
                    'source' => 'ols',
                ];
            }
        }

        foreach ($this->safeCurateGptSearch($query) as $candidate) {
            $iri = $this->iriFromCurateId($candidate->id);
            if (null === $iri) {
                continue;
            }
            $candidates[] = [
                'iri' => $iri,
                'label' => $candidate->label,
                'ontology' => 'curategpt',
                'score' => round(max(0.0, min(1.0, $candidate->score)), 3),
                'source' => 'curategpt',
            ];
        }

        return $this->dedupeByIri($candidates);
    }

    /**
     * @return list<array{label: string, sublabel: ?string, value: string, externalId: ?string, scheme: string}>
     */
    private function safeOlsSearch(string $source, string $query): array
    {
        try {
            return $this->lookup->search($source, $query);
        } catch (\Throwable $e) {
            $this->logger->warning('Ontology grounding OLS lookup failed', [
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<CurateGptCandidateDto>
     */
    private function safeCurateGptSearch(string $query): array
    {
        try {
            return array_values($this->curateGpt->search(new CurateGptSearchRequestDto($query, self::CURATE_GPT_LIMIT)));
        } catch (\Throwable $e) {
            $this->logger->warning('Ontology grounding CurateGPT lookup failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * CurateGPT returns OBO-style ids ("VT:0000188"); resolve to a PURL IRI. Pass
     * through values that are already IRIs.
     */
    private function iriFromCurateId(string $id): ?string
    {
        $id = trim($id);
        if ('' === $id) {
            return null;
        }
        if (1 === preg_match('#^https?://#i', $id)) {
            return $id;
        }
        if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9]*:\d+$/', $id)) {
            return null;
        }

        return 'http://purl.obolibrary.org/obo/' . str_replace(':', '_', $id);
    }

    /**
     * Keep the highest-scoring candidate per IRI.
     *
     * @param list<array{iri: string, label: string, ontology: string, score: float, source: string}> $candidates
     *
     * @return list<array{iri: string, label: string, ontology: string, score: float, source: string}>
     */
    private function dedupeByIri(array $candidates): array
    {
        $byIri = [];
        foreach ($candidates as $candidate) {
            $iri = $candidate['iri'];
            if (!isset($byIri[$iri]) || $candidate['score'] > $byIri[$iri]['score']) {
                $byIri[$iri] = $candidate;
            }
        }

        $merged = array_values($byIri);
        usort($merged, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $merged;
    }
}
