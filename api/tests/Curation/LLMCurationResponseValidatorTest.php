<?php

declare(strict_types=1);

namespace App\Tests\Curation;

use App\Curation\LLMCurationResponseValidator;
use App\Enum\CurationTaskType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LLMCurationResponseValidatorTest extends TestCase
{
    #[Test]
    public function it_validates_strict_json_suggestions(): void
    {
        $validator = new LLMCurationResponseValidator();

        $suggestions = $validator->validate(json_encode([
            'suggestions' => [
                [
                    'task_type' => 'value_normalization',
                    'field_name' => 'name',
                    'proposed_value' => 'Mouse 001',
                    'confidence' => 0.98,
                    'reason' => 'Normalize whitespace.',
                    'warning_code' => 'normalized_free_text',
                ],
                [
                    'task_type' => 'ontology_candidate',
                    'field_name' => 'species',
                    'proposed_value' => 'mouse',
                    'ontology_id' => 'NCBITaxon:10090',
                    'confidence' => 0.9,
                    'reason' => 'Mapped to canonical species ontology.',
                    'provider_metadata' => [
                        'candidateId' => 'http://purl.obolibrary.org/obo/NCBITaxon_10090',
                        'candidateLabel' => 'Mus musculus',
                    ],
                ],
            ],
        ], \JSON_THROW_ON_ERROR));

        $this->assertCount(2, $suggestions);
        $this->assertSame(CurationTaskType::ValueNormalization, $suggestions[0]->taskType);
        $this->assertSame('NCBITaxon:10090', $suggestions[1]->ontologyId);
        $this->assertSame('Mus musculus', $suggestions[1]->providerMetadata['candidateLabel']);
    }

    #[Test]
    public function it_rejects_invalid_llm_output(): void
    {
        $validator = new LLMCurationResponseValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LLM response must be valid JSON.');

        $validator->validate('free text only');
    }

    #[Test]
    public function it_rejects_missing_proposed_value(): void
    {
        $validator = new LLMCurationResponseValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Suggestion 0 is missing "proposed_value".');

        $validator->validate(json_encode([
            'suggestions' => [[
                'task_type' => 'value_normalization',
                'field_name' => 'name',
                'reason' => 'Missing proposed value.',
            ]],
        ], \JSON_THROW_ON_ERROR));
    }
}
