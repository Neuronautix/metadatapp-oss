<?php

declare(strict_types=1);

namespace App\Curation\Provider;

use App\CurateGpt\Client\CurateGptClientInterface;
use App\CurateGpt\Client\Dto\CurateGptCandidateDto;
use App\CurateGpt\Client\Dto\CurateGptSearchRequestDto;
use App\Curation\LLMCurationProvider;

final readonly class CurateGptOntologyProvider implements LLMCurationProvider
{
    public function __construct(
        private CurateGptClientInterface $client,
    ) {
    }

    public function curate(string $systemPrompt, array $payload): string
    {
        $suggestions = $this->buildDeterministicSuggestions($payload);
        $speciesSuggestion = $this->buildSpeciesOntologySuggestion($payload);
        if (null !== $speciesSuggestion) {
            $suggestions[] = $speciesSuggestion;
        }

        return json_encode(['suggestions' => $suggestions], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function buildDeterministicSuggestions(array $payload): array
    {
        $suggestions = [];

        $name = isset($payload['name']) && \is_string($payload['name']) ? $payload['name'] : '';
        $normalizedName = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        if ($normalizedName !== $name) {
            $suggestions[] = [
                'task_type' => 'value_normalization',
                'field_name' => 'name',
                'proposed_value' => $normalizedName,
                'confidence' => 0.98,
                'reason' => 'Trim redundant whitespace to keep subject labels normalized.',
                'warning_code' => 'normalized_free_text',
            ];
        }

        $strain = isset($payload['strain']) && \is_string($payload['strain']) ? $payload['strain'] : '';
        $normalizedStrain = trim(preg_replace('/\s+/', ' ', $strain) ?? $strain);
        if ('' !== $strain && $normalizedStrain !== $strain) {
            $suggestions[] = [
                'task_type' => 'value_normalization',
                'field_name' => 'strain',
                'proposed_value' => $normalizedStrain,
                'confidence' => 0.93,
                'reason' => 'Normalize strain labels so repeated records use the same canonical value.',
                'warning_code' => 'normalized_free_text',
            ];
        }

        $genotype = isset($payload['genotype']) && \is_string($payload['genotype']) ? $payload['genotype'] : null;
        if (null === $genotype || '' === trim($genotype)) {
            $suggestions[] = [
                'task_type' => 'missing_required_field',
                'field_name' => 'genotype',
                'proposed_value' => null,
                'confidence' => 0.42,
                'reason' => 'Genotype is missing. Curator input is required before the record is complete.',
                'warning_code' => 'missing_required_field',
            ];
        } else {
            $normalizedGenotype = strtoupper(trim(preg_replace('/\s+/', ' ', $genotype) ?? $genotype));
            if ($normalizedGenotype !== $genotype) {
                $suggestions[] = [
                    'task_type' => 'value_normalization',
                    'field_name' => 'genotype',
                    'proposed_value' => $normalizedGenotype,
                    'confidence' => 0.89,
                    'reason' => 'Normalize genotype formatting for downstream matching.',
                    'warning_code' => 'normalized_free_text',
                ];
            }
        }

        $sex = isset($payload['sex']) && \is_string($payload['sex']) ? strtolower($payload['sex']) : null;
        $isPregnant = $payload['isPregnant'] ?? ($payload['is_pregnant'] ?? null);
        if ('male' === $sex && true === $isPregnant) {
            $suggestions[] = [
                'task_type' => 'cross_field_consistency',
                'field_name' => 'isPregnant',
                'proposed_value' => false,
                'confidence' => 0.99,
                'reason' => 'Male subjects should not be marked as pregnant.',
                'warning_code' => 'cross_field_inconsistency',
            ];
        }

        return $suggestions;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildSpeciesOntologySuggestion(array $payload): ?array
    {
        $species = isset($payload['species']) && \is_string($payload['species']) ? trim($payload['species']) : '';
        if ('' === $species) {
            return null;
        }

        try {
            $candidates = $this->client->search(new CurateGptSearchRequestDto(
                query: $species,
                limit: 1,
                collection: 'ncbitaxon',
            ));
        } catch (\RuntimeException) {
            return null;
        }

        $candidate = $candidates[0] ?? null;
        if (!$candidate instanceof CurateGptCandidateDto || '' === $candidate->id || '' === $candidate->label) {
            return null;
        }

        return [
            'task_type' => 'ontology_candidate',
            'field_name' => 'species',
            'proposed_value' => strtolower($species),
            'ontology_id' => $this->normalizeOntologyId($candidate->id),
            'confidence' => $candidate->score > 0 ? $candidate->score : null,
            'reason' => 'Attach the best matching CurateGPT ontology candidate for curator review.',
            'warning_code' => 'ontology_candidate',
            'provider_metadata' => [
                'candidateId' => $candidate->id,
                'candidateLabel' => $candidate->label,
                'candidateMetadata' => $candidate->metadata,
            ],
        ];
    }

    /**
     * Normalize an ontology identifier from an OBO PURL to a CURIE when possible.
     */
    private function normalizeOntologyId(string $candidateId): string
    {
        // Convert OBO PURLs like "http://purl.obolibrary.org/obo/NCBITaxon_10090"
        // into CURIEs like "NCBITaxon:10090" for suggestion storage/display.
        if (preg_match('~obo/([A-Za-z][A-Za-z0-9]+)_([A-Za-z0-9]+)$~', $candidateId, $matches)) {
            return $matches[1] . ':' . $matches[2];
        }

        return $candidateId;
    }
}
