<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crosswalk;

use App\Crosswalk\Validation\CrosswalkValidator;
use App\Entity\CanonicalForm;
use App\Entity\CommonDataElement;
use App\Entity\ExternalTemplate;
use App\Entity\ExtractedTemplateField;
use App\Entity\FieldCrosswalk;
use App\Entity\TemplateFormCrosswalk;
use App\Enum\CdeSource;
use App\Enum\MappingStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CrosswalkValidatorTest extends TestCase
{
    #[Test]
    public function unmappedFieldProducesAnUnmappedFieldIssue(): void
    {
        $template = (new ExternalTemplate())->setTitle('Demo');
        $template->addExtractedField(
            (new ExtractedTemplateField())->setLabel('orphanField')->setDatatype('string'),
        );

        $crosswalk = $this->crosswalk($template);

        $report = (new CrosswalkValidator())->validate($crosswalk)->toArray();

        self::assertSame(1, $report['counts']['fieldsExtracted']);
        self::assertSame(0, $report['counts']['fieldsMappedToCdes']);
        self::assertSame(1, $report['counts']['unmappedFields']);
        self::assertContains('UNMAPPED_FIELD', $this->issueCodes($report));
    }

    #[Test]
    public function numericFieldWithoutAUnitProducesAMissingUnitIssue(): void
    {
        $template = (new ExternalTemplate())->setTitle('Demo');
        $field = (new ExtractedTemplateField())
            ->setLabel('molecularWeight')
            ->setDatatype('decimal'); // no unit
        $template->addExtractedField($field);

        $crosswalk = $this->crosswalk($template);

        $report = (new CrosswalkValidator())->validate($crosswalk)->toArray();

        self::assertSame(1, $report['counts']['numericFields']);
        self::assertSame(0, $report['counts']['numericFieldsWithUnits']);
        self::assertContains('MISSING_UNIT', $this->issueCodes($report));
    }

    #[Test]
    public function mappedAcceptedFieldWithUnitYieldsAHighCompletenessScore(): void
    {
        $template = (new ExternalTemplate())->setTitle('Demo');
        $field = (new ExtractedTemplateField())
            ->setLabel('molecularWeight')
            ->setDatatype('decimal')
            ->setUnit('g/mol');
        $template->addExtractedField($field);

        $cde = (new CommonDataElement())
            ->setSource(CdeSource::METADATAPP)
            ->setLabel('Molecular weight')
            ->setDatatype('decimal')
            ->setUnitConstraint('g/mol')
            ->setLocalKey('molecular_weight');

        $crosswalk = $this->crosswalk($template);
        $crosswalk->addFieldCrosswalk(
            (new FieldCrosswalk())
                ->setCde($cde)
                ->setElnField($field)
                ->setMappingStatus(MappingStatus::ACCEPTED),
        );

        $report = (new CrosswalkValidator())->validate($crosswalk)->toArray();

        self::assertSame(1, $report['counts']['fieldsMappedToCdes']);
        self::assertSame(0, $report['counts']['unmappedFields']);

        $score = $report['semanticCompletenessScore'];
        self::assertIsInt($score);
        self::assertGreaterThanOrEqual(0, $score);
        self::assertLessThanOrEqual(100, $score);
        // Fully mapped + unit-covered + reviewed should be a strong score.
        self::assertGreaterThanOrEqual(80, $score);

        self::assertNotContains('UNMAPPED_FIELD', $this->issueCodes($report));
        self::assertNotContains('MISSING_UNIT', $this->issueCodes($report));
    }

    #[Test]
    public function suggestedMappingIsFlaggedAsNotReviewed(): void
    {
        $template = (new ExternalTemplate())->setTitle('Demo');
        $field = (new ExtractedTemplateField())->setLabel('sampleId')->setDatatype('string');
        $template->addExtractedField($field);

        $cde = (new CommonDataElement())
            ->setSource(CdeSource::METADATAPP)
            ->setLabel('Sample identifier')
            ->setDatatype('string')
            ->setLocalKey('sample_identifier');

        $crosswalk = $this->crosswalk($template);
        $crosswalk->addFieldCrosswalk(
            (new FieldCrosswalk())
                ->setCde($cde)
                ->setElnField($field)
                ->setMappingStatus(MappingStatus::SUGGESTED),
        );

        $report = (new CrosswalkValidator())->validate($crosswalk)->toArray();

        self::assertSame(1, $report['counts']['fieldsRequiringReview']);
        self::assertContains('MAPPING_NOT_REVIEWED', $this->issueCodes($report));
    }

    private function crosswalk(ExternalTemplate $template): TemplateFormCrosswalk
    {
        $form = (new CanonicalForm())->setTitle('Demo form')->setDomain('chemistry');

        return (new TemplateFormCrosswalk())
            ->setExternalTemplate($template)
            ->setCanonicalForm($form)
            ->setMappingStatus(MappingStatus::SUGGESTED);
    }

    /**
     * @param array<string, mixed> $report
     *
     * @return list<string>
     */
    private function issueCodes(array $report): array
    {
        $codes = [];
        foreach ($report['issues'] as $issue) {
            $codes[] = $issue['code'];
        }

        return $codes;
    }
}
