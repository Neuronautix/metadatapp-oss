<?php

declare(strict_types=1);

namespace App\Curation\Provider;

use App\Curation\LLMCurationProvider;

final readonly class MockLLMCurationProvider implements LLMCurationProvider
{
    public function __construct(
        private string $mode = 'normal',
    ) {
    }

    public function curate(string $systemPrompt, array $payload): string
    {
        if ('invalid_json' === $this->mode) {
            return 'not valid json';
        }

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

        $species = isset($payload['species']) && \is_string($payload['species']) ? strtolower($payload['species']) : '';
        $ontologyId = $payload['ontologyCandidates']['species'][$species] ?? null;
        if (\is_string($ontologyId) && '' !== $species) {
            $suggestions[] = [
                'task_type' => 'ontology_candidate',
                'field_name' => 'species',
                'proposed_value' => $species,
                'ontology_id' => $ontologyId,
                'confidence' => 0.9,
                'reason' => 'Attach the best matching species ontology candidate for curator review.',
                'warning_code' => 'ontology_candidate',
            ];
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

        return json_encode(['suggestions' => $suggestions], \JSON_THROW_ON_ERROR);
    }
}
