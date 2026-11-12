<?php

declare(strict_types=1);

namespace App\Crosswalk\Mapping;

use App\Crosswalk\Cde\CdeCandidate;
use App\Entity\CommonDataElement;
use App\Entity\ExtractedTemplateField;
use App\Entity\TemplateFormCrosswalk;
use App\Enum\CdeStatus;
use App\Enum\CrosswalkMappingType;

/**
 * Deterministic, explainable first-pass mapping engine (NO LLM). Combines weighted
 * signals — label match, propertyID/IRI match, ontology overlap, datatype and unit
 * compatibility — into a confidence in [0,1], a mapping type, and human-readable
 * reasons/risks. An optional {@see MappingSuggestionRefinerInterface} can re-rank.
 *
 * Calibration anchor: ELN "molecularWeight" (g/mol, numeric) -> CDE "Molecular
 * weight" (g/mol, numeric) yields EXACT at confidence ~0.94.
 */
final class MappingSuggestionEngine
{
    private const W_EXACT_LABEL = 0.70;
    private const W_CLOSE_LABEL = 0.42;
    private const W_TOKEN_OVERLAP = 0.30;
    private const W_PROPERTY_ID = 0.30;
    private const W_ONTOLOGY = 0.25;
    private const W_DATATYPE = 0.12;
    private const W_UNIT = 0.12;

    public function __construct(
        private readonly MappingSuggestionRefinerInterface $refiner = new NullMappingSuggestionRefiner(),
    ) {
    }

    /**
     * Score an extracted field against a set of CDE candidates.
     *
     * @param iterable<CommonDataElement|CdeCandidate> $cdeCandidates
     *
     * @return list<MappingSuggestion>
     */
    public function suggestForField(ExtractedTemplateField $field, iterable $cdeCandidates): array
    {
        $suggestions = [];
        foreach ($cdeCandidates as $cde) {
            $suggestion = $this->score($field, $cde);
            if (null !== $suggestion) {
                $suggestions[] = $suggestion;
            }
        }

        usort($suggestions, static fn (MappingSuggestion $a, MappingSuggestion $b): int => $b->confidence <=> $a->confidence);

        return $this->refiner->refine($suggestions, $field);
    }

    /**
     * Suggest mappings for every extracted field of the crosswalk's template
     * against the CDEs already referenced by its field crosswalks (plus any CDEs
     * shared via the canonical form fields' source references is out of scope —
     * callers pass candidates via suggestForField for richer sets).
     *
     * @return array<string, list<MappingSuggestion>> keyed by extracted field id
     */
    public function suggestForCrosswalk(TemplateFormCrosswalk $crosswalk): array
    {
        $candidates = [];
        foreach ($crosswalk->getFieldCrosswalks() as $fieldCrosswalk) {
            $cde = $fieldCrosswalk->getCde();
            if (null !== $cde) {
                $key = $cde->getId()?->toRfc4122() ?? spl_object_hash($cde);
                $candidates[$key] = $cde;
            }
        }
        $candidateList = array_values($candidates);

        $out = [];
        foreach ($crosswalk->getExternalTemplate()->getExtractedFields() as $field) {
            $key = $field->getId()?->toRfc4122() ?? spl_object_hash($field);
            $out[$key] = $this->suggestForField($field, $candidateList);
        }

        return $out;
    }

    private function score(ExtractedTemplateField $field, CommonDataElement|CdeCandidate $cde): ?MappingSuggestion
    {
        $reasons = [];
        $risks = [];
        $confidence = 0.0;
        $type = CrosswalkMappingType::RELATED;

        $fieldLabel = $this->normalize($field->getLabel());
        $cdeLabel = $this->normalize($this->cdeLabel($cde));

        // --- Label signals -------------------------------------------------
        if ('' !== $fieldLabel && $fieldLabel === $cdeLabel) {
            $confidence += self::W_EXACT_LABEL;
            $type = CrosswalkMappingType::EXACT;
            $reasons[] = 'exact normalized label match';
        } else {
            $tokenScore = $this->tokenOverlap($fieldLabel, $cdeLabel);
            if ($tokenScore >= 0.999) {
                // Same tokens, different order/spacing.
                $confidence += self::W_CLOSE_LABEL;
                $type = CrosswalkMappingType::CLOSE;
                $reasons[] = 'normalized label match (token-equal)';
            } elseif ($tokenScore > 0.0) {
                $confidence += self::W_TOKEN_OVERLAP * $tokenScore;
                $type = CrosswalkMappingType::RELATED;
                $reasons[] = \sprintf('partial label token overlap (%.0f%%)', $tokenScore * 100);
            }
        }

        // --- propertyID / IRI match ---------------------------------------
        $propertyId = $field->getPropertyId();
        if (null !== $propertyId && '' !== $propertyId && $this->propertyMatches($propertyId, $cde)) {
            $confidence += self::W_PROPERTY_ID;
            $reasons[] = 'propertyID matches CDE identifier';
            if (CrosswalkMappingType::EXACT !== $type) {
                $type = CrosswalkMappingType::CLOSE;
            }
        }

        // --- ontology term overlap ----------------------------------------
        if ($this->ontologyOverlap($field, $cde)) {
            $confidence += self::W_ONTOLOGY;
            $reasons[] = 'shared ontology term IRI';
        }

        // --- datatype compatibility ---------------------------------------
        $fieldType = $this->normalizeDatatype($field->getDatatype());
        $cdeType = $this->normalizeDatatype($this->cdeDatatype($cde));
        if (null !== $fieldType && null !== $cdeType) {
            if ($this->datatypeCompatible($fieldType, $cdeType)) {
                $confidence += self::W_DATATYPE;
                $reasons[] = \sprintf('compatible datatype %s', $cdeType);
            } else {
                $risks[] = \sprintf('datatype mismatch: ELN %s vs CDE %s', $fieldType, $cdeType);
                $confidence = min($confidence, 0.6);
            }
        }

        // --- unit compatibility -------------------------------------------
        $fieldUnit = $this->normalizeUnit($field->getUnit());
        $cdeUnit = $this->normalizeUnit($this->cdeUnit($cde));
        if (null !== $cdeUnit) {
            if (null !== $fieldUnit && $fieldUnit === $cdeUnit) {
                $confidence += self::W_UNIT;
                $reasons[] = \sprintf('compatible unit %s', $this->cdeUnit($cde));
            } elseif (null !== $fieldUnit) {
                $risks[] = \sprintf('unit mismatch: ELN %s vs CDE %s', $field->getUnit(), $this->cdeUnit($cde));
            } else {
                $risks[] = \sprintf('CDE expects unit %s but ELN field has none', $this->cdeUnit($cde));
            }
        }

        // --- deprecation risk ---------------------------------------------
        if ($this->isDeprecated($cde)) {
            $risks[] = 'CDE is deprecated';
        }

        if ([] === $reasons) {
            return null;
        }

        $confidence = max(0.0, min(1.0, $confidence));

        return new MappingSuggestion($cde, $confidence, $reasons, $risks, $type);
    }

    private function cdeLabel(CommonDataElement|CdeCandidate $cde): string
    {
        return $cde instanceof CommonDataElement ? $cde->getLabel() : $cde->label;
    }

    private function cdeDatatype(CommonDataElement|CdeCandidate $cde): ?string
    {
        return $cde instanceof CommonDataElement ? $cde->getDatatype() : $cde->datatype;
    }

    private function cdeUnit(CommonDataElement|CdeCandidate $cde): ?string
    {
        return $cde instanceof CommonDataElement ? $cde->getUnitConstraint() : $cde->unitConstraint;
    }

    private function isDeprecated(CommonDataElement|CdeCandidate $cde): bool
    {
        if ($cde instanceof CommonDataElement) {
            return CdeStatus::DEPRECATED === $cde->getStatus();
        }

        return 'deprecated' === strtolower((string) $cde->status);
    }

    private function propertyMatches(string $propertyId, CommonDataElement|CdeCandidate $cde): bool
    {
        $externalId = $cde instanceof CommonDataElement ? $cde->getExternalId() : $cde->externalId;
        $externalUrl = $cde instanceof CommonDataElement ? $cde->getExternalUrl() : $cde->externalUrl;
        $localKey = $cde instanceof CommonDataElement ? $cde->getLocalKey() : $cde->localKey;

        $needle = strtolower(trim($propertyId));
        foreach ([$externalId, $externalUrl, $localKey] as $candidate) {
            if (null !== $candidate && '' !== $candidate && strtolower(trim($candidate)) === $needle) {
                return true;
            }
        }

        // propertyID often looks like "prefix:localKey".
        if (null !== $localKey && str_ends_with($needle, ':' . strtolower($localKey))) {
            return true;
        }

        return false;
    }

    private function ontologyOverlap(ExtractedTemplateField $field, CommonDataElement|CdeCandidate $cde): bool
    {
        $fieldIris = $this->iris($field->getOntologyTerms());
        $cdeIris = $this->iris($cde instanceof CommonDataElement ? $cde->getOntologyMappings() : $cde->ontologyMappings);

        return [] !== array_intersect($fieldIris, $cdeIris);
    }

    /**
     * @param array<int|string, mixed> $terms
     *
     * @return list<string>
     */
    private function iris(array $terms): array
    {
        $out = [];
        foreach ($terms as $term) {
            if (\is_array($term) && isset($term['iri']) && \is_string($term['iri'])) {
                $out[] = strtolower(trim($term['iri']));
            } elseif (\is_string($term)) {
                $out[] = strtolower(trim($term));
            }
        }

        return $out;
    }

    private function normalize(string $value): string
    {
        // Split camelCase / PascalCase into words first.
        $spaced = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $value) ?? $value;
        $spaced = strtolower($spaced);
        $spaced = preg_replace('/[^a-z0-9]+/', ' ', $spaced) ?? $spaced;

        return trim((string) preg_replace('/\s+/', ' ', $spaced));
    }

    private function tokenOverlap(string $a, string $b): float
    {
        $ta = array_filter(explode(' ', $a));
        $tb = array_filter(explode(' ', $b));
        if ([] === $ta || [] === $tb) {
            return 0.0;
        }

        $intersection = array_intersect($ta, $tb);
        $union = array_unique(array_merge($ta, $tb));

        return \count($intersection) / max(1, \count($union));
    }

    private function normalizeDatatype(?string $datatype): ?string
    {
        if (null === $datatype || '' === $datatype) {
            return null;
        }

        $d = strtolower($datatype);
        if (str_contains($d, 'int')) {
            return 'integer';
        }
        if (str_contains($d, 'float') || str_contains($d, 'decimal') || str_contains($d, 'double') || str_contains($d, 'number') || str_contains($d, 'numeric')) {
            return 'decimal';
        }
        if (str_contains($d, 'bool')) {
            return 'boolean';
        }
        if (str_contains($d, 'date') || str_contains($d, 'time')) {
            return 'date';
        }
        if (str_contains($d, 'value list') || str_contains($d, 'enum') || str_contains($d, 'categor')) {
            return 'string';
        }

        return 'string';
    }

    private function datatypeCompatible(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $numeric = ['integer', 'decimal'];

        return \in_array($a, $numeric, true) && \in_array($b, $numeric, true);
    }

    private function normalizeUnit(?string $unit): ?string
    {
        if (null === $unit || '' === trim($unit)) {
            return null;
        }

        return strtolower(str_replace([' ', '·'], ['', '/'], trim($unit)));
    }
}
