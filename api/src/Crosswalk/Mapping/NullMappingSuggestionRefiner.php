<?php

declare(strict_types=1);

namespace App\Crosswalk\Mapping;

use App\Entity\ExtractedTemplateField;

/**
 * Default no-op refiner: returns the deterministic suggestions unchanged.
 */
final class NullMappingSuggestionRefiner implements MappingSuggestionRefinerInterface
{
    public function refine(array $suggestions, ExtractedTemplateField $field): array
    {
        return $suggestions;
    }
}
