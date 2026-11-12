<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crosswalk;

use App\Crosswalk\Cde\CdeCandidate;
use App\Crosswalk\Mapping\MappingSuggestionEngine;
use App\Entity\ExtractedTemplateField;
use App\Enum\CrosswalkMappingType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MappingSuggestionEngineTest extends TestCase
{
    #[Test]
    public function molecularWeightMatchesMolecularWeightCdeAsExactAtHighConfidence(): void
    {
        $engine = new MappingSuggestionEngine();

        $field = (new ExtractedTemplateField())
            ->setLabel('molecularWeight')
            ->setPropertyId('molecularWeight')
            ->setDatatype('decimal')
            ->setUnit('g/mol');

        $cde = new CdeCandidate(
            source: 'metadatapp',
            label: 'Molecular weight',
            externalId: null,
            datatype: 'decimal',
            unitConstraint: 'g/mol',
            localKey: 'molecular_weight',
        );

        $suggestions = $engine->suggestForField($field, [$cde]);

        self::assertCount(1, $suggestions);
        $best = $suggestions[0];

        self::assertSame(CrosswalkMappingType::EXACT, $best->mappingType);
        // exact label 0.70 + datatype 0.12 + unit 0.12 ⇒ ~0.94.
        self::assertEqualsWithDelta(0.94, $best->confidence, 0.02);
        self::assertGreaterThanOrEqual(0.9, $best->confidence);

        $reasons = implode(' | ', $best->reasons);
        self::assertStringContainsStringIgnoringCase('exact normalized label match', $reasons);
        self::assertStringContainsStringIgnoringCase('unit', $reasons);
    }

    #[Test]
    public function suggestionsAreSortedByConfidenceDescending(): void
    {
        $engine = new MappingSuggestionEngine();

        $field = (new ExtractedTemplateField())
            ->setLabel('molecularWeight')
            ->setDatatype('decimal')
            ->setUnit('g/mol');

        $exact = new CdeCandidate(
            source: 'metadatapp',
            label: 'Molecular weight',
            datatype: 'decimal',
            unitConstraint: 'g/mol',
            localKey: 'molecular_weight',
        );
        $weak = new CdeCandidate(
            source: 'metadatapp',
            label: 'Molecular descriptor weighting factor',
            localKey: 'molecular_descriptor',
        );

        $suggestions = $engine->suggestForField($field, [$weak, $exact]);

        self::assertGreaterThanOrEqual(2, \count($suggestions));
        self::assertSame('Molecular weight', $this->candidateLabel($suggestions[0]->cde));
        // Descending order is guaranteed.
        for ($i = 1, $n = \count($suggestions); $i < $n; ++$i) {
            self::assertLessThanOrEqual($suggestions[$i - 1]->confidence, $suggestions[$i]->confidence);
        }
    }

    #[Test]
    public function unitMismatchIsReportedAsARisk(): void
    {
        $engine = new MappingSuggestionEngine();

        $field = (new ExtractedTemplateField())
            ->setLabel('molecularWeight')
            ->setDatatype('decimal')
            ->setUnit('kg/mol');

        $cde = new CdeCandidate(
            source: 'metadatapp',
            label: 'Molecular weight',
            datatype: 'decimal',
            unitConstraint: 'g/mol',
            localKey: 'molecular_weight',
        );

        $best = $engine->suggestForField($field, [$cde])[0];

        self::assertNotEmpty($best->risks);
        self::assertStringContainsStringIgnoringCase('unit mismatch', implode(' | ', $best->risks));
    }

    private function candidateLabel(CdeCandidate $cde): string
    {
        return $cde->label;
    }
}
