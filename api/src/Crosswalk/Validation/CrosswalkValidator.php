<?php

declare(strict_types=1);

namespace App\Crosswalk\Validation;

use App\Entity\CommonDataElement;
use App\Entity\TemplateFormCrosswalk;
use App\Enum\CdeStatus;
use App\Enum\MappingStatus;

/**
 * Validates a crosswalk's semantic completeness: are extracted fields mapped to
 * CDEs, do numeric fields carry units, do categorical fields carry controlled
 * values, have mappings been reviewed? Produces a {@see ValidationReport}.
 */
final class CrosswalkValidator
{
    private const NUMERIC_TYPES = ['integer', 'decimal', 'number', 'float', 'double', 'numeric', 'int'];

    public function validate(TemplateFormCrosswalk $crosswalk): ValidationReport
    {
        $template = $crosswalk->getExternalTemplate();
        $extracted = $template->getExtractedFields();

        $fieldsExtracted = $extracted->count();
        $issues = [];

        // Index accepted/suggested field crosswalks by extracted field id.
        $byField = [];
        foreach ($crosswalk->getFieldCrosswalks() as $fc) {
            $elnField = $fc->getElnField();
            $key = null !== $elnField ? ($elnField->getId()?->toRfc4122() ?? spl_object_hash($elnField)) : null;
            if (null !== $key) {
                $byField[$key] = $fc;
            }
        }

        $mappedToCdes = 0;
        $numericFields = 0;
        $numericWithUnits = 0;
        $categoricalFields = 0;
        $categoricalWithControlled = 0;
        $fieldsRequiringReview = 0;

        foreach ($extracted as $field) {
            $key = $field->getId()?->toRfc4122() ?? spl_object_hash($field);
            $fc = $byField[$key] ?? null;
            $ref = $field->getLabel();

            $cde = $fc?->getCde();
            $isMapped = null !== $fc && null !== $cde && MappingStatus::REJECTED !== $fc->getMappingStatus();

            if ($isMapped) {
                ++$mappedToCdes;
            } else {
                $issues[] = $this->issue('UNMAPPED_FIELD', 'warning', \sprintf('Field "%s" is not mapped to any CDE.', $ref), $ref);
            }

            $datatype = strtolower((string) $field->getDatatype());
            if ('' === $datatype) {
                $issues[] = $this->issue('MISSING_DATATYPE', 'info', \sprintf('Field "%s" has no datatype.', $ref), $ref);
            }

            $isNumeric = $this->isNumeric($datatype) || (null !== $cde && $this->isNumeric(strtolower((string) $cde->getDatatype())));
            if ($isNumeric) {
                ++$numericFields;
                $hasUnit = null !== $field->getUnit() && '' !== trim($field->getUnit());
                if ($hasUnit) {
                    ++$numericWithUnits;
                } else {
                    $issues[] = $this->issue('MISSING_UNIT', 'warning', \sprintf('Numeric field "%s" has no unit.', $ref), $ref);
                }

                if ($hasUnit && null !== $cde) {
                    $cdeUnit = $cde->getUnitConstraint();
                    if (null !== $cdeUnit && '' !== $cdeUnit && $this->normalizeUnit($field->getUnit()) !== $this->normalizeUnit($cdeUnit)) {
                        $issues[] = $this->issue('INVALID_UNIT', 'warning', \sprintf('Field "%s" unit "%s" differs from CDE unit "%s".', $ref, (string) $field->getUnit(), $cdeUnit), $ref);
                    }
                }
            }

            $isCategorical = null !== $cde && [] !== $cde->getPermissibleValues();
            if ($isCategorical) {
                ++$categoricalFields;
                ++$categoricalWithControlled;
                $this->checkPermissibleValue($field->getExampleValue(), $cde, $ref, $issues);
            } elseif (null !== $field->getExampleValue() && 'string' === $datatype && null !== $cde && [] === $cde->getPermissibleValues()) {
                // categorical-looking ELN field but CDE has no controlled values
                ++$categoricalFields;
            }

            if (null !== $fc) {
                if (MappingStatus::SUGGESTED === $fc->getMappingStatus()) {
                    ++$fieldsRequiringReview;
                    $issues[] = $this->issue('MAPPING_NOT_REVIEWED', 'info', \sprintf('Mapping for field "%s" has not been human-reviewed.', $ref), $ref);
                }
                if (null !== $cde && CdeStatus::DEPRECATED === $cde->getStatus()) {
                    $issues[] = $this->issue('DEPRECATED_CDE_USED', 'warning', \sprintf('Field "%s" maps to a deprecated CDE.', $ref), $ref);
                }
            }
        }

        $unmappedFields = $fieldsExtracted - $mappedToCdes;

        if (null === $crosswalk->getExternalForm() && [] === $crosswalk->getCanonicalForm()->getFields()->toArray() && 0 === $mappedToCdes) {
            $issues[] = $this->issue('NO_MATCHING_CDE_FORM', 'info', 'No CDE Form or mapped CDEs are associated with this crosswalk yet.', null);
        }

        $score = $this->score(
            $fieldsExtracted,
            $mappedToCdes,
            $numericFields,
            $numericWithUnits,
            $categoricalFields,
            $categoricalWithControlled,
            $crosswalk,
        );

        return new ValidationReport(
            fieldsExtracted: $fieldsExtracted,
            fieldsMappedToCdes: $mappedToCdes,
            unmappedFields: $unmappedFields,
            numericFields: $numericFields,
            numericFieldsWithUnits: $numericWithUnits,
            categoricalFields: $categoricalFields,
            categoricalFieldsWithControlledValues: $categoricalWithControlled,
            fieldsRequiringReview: $fieldsRequiringReview,
            issues: $issues,
            semanticCompletenessScore: $score,
        );
    }

    /**
     * @param list<array{code: string, severity: string, message: string, fieldRef?: string}> $issues
     */
    private function checkPermissibleValue(?string $value, CommonDataElement $cde, string $ref, array &$issues): void
    {
        if (null === $value || '' === trim($value)) {
            return;
        }

        $allowed = [];
        foreach ($cde->getPermissibleValues() as $pv) {
            if (\is_array($pv) && isset($pv['value']) && \is_string($pv['value'])) {
                $allowed[] = strtolower(trim($pv['value']));
            }
        }

        if ([] !== $allowed && !\in_array(strtolower(trim($value)), $allowed, true)) {
            $issues[] = $this->issue('VALUE_NOT_PERMISSIBLE', 'warning', \sprintf('Field "%s" value "%s" is not in the CDE permissible values.', $ref, $value), $ref);
        }
    }

    private function score(
        int $fieldsExtracted,
        int $mapped,
        int $numericFields,
        int $numericWithUnits,
        int $categoricalFields,
        int $categoricalWithControlled,
        TemplateFormCrosswalk $crosswalk,
    ): int {
        if (0 === $fieldsExtracted) {
            return 0;
        }

        $mappedRatio = $mapped / $fieldsExtracted;
        $unitCoverage = $numericFields > 0 ? $numericWithUnits / $numericFields : 1.0;
        $controlledCoverage = $categoricalFields > 0 ? $categoricalWithControlled / $categoricalFields : 1.0;

        $reviewed = 0;
        $mappedCount = 0;
        foreach ($crosswalk->getFieldCrosswalks() as $fc) {
            if (null !== $fc->getCde()) {
                ++$mappedCount;
                if (MappingStatus::ACCEPTED === $fc->getMappingStatus()) {
                    ++$reviewed;
                }
            }
        }
        $reviewRatio = $mappedCount > 0 ? $reviewed / $mappedCount : 1.0;

        $weighted = 0.45 * $mappedRatio
            + 0.20 * $unitCoverage
            + 0.15 * $controlledCoverage
            + 0.20 * $reviewRatio;

        return (int) round(100 * $weighted);
    }

    private function isNumeric(string $datatype): bool
    {
        foreach (self::NUMERIC_TYPES as $type) {
            if (str_contains($datatype, $type)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeUnit(?string $unit): ?string
    {
        if (null === $unit) {
            return null;
        }

        return strtolower(str_replace([' ', '·'], ['', '/'], trim($unit)));
    }

    /**
     * @return array{code: string, severity: string, message: string, fieldRef?: string}
     */
    private function issue(string $code, string $severity, string $message, ?string $fieldRef): array
    {
        $issue = ['code' => $code, 'severity' => $severity, 'message' => $message];
        if (null !== $fieldRef) {
            $issue['fieldRef'] = $fieldRef;
        }

        /** @var array{code: string, severity: string, message: string, fieldRef?: string} $issue */
        return $issue;
    }
}
