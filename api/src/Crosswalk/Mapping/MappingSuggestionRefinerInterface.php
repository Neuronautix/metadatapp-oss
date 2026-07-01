<?php

declare(strict_types=1);

namespace App\Crosswalk\Mapping;

use App\Entity\ExtractedTemplateField;

/**
 * Optional hook for later AI assistance: re-rank / annotate deterministic
 * suggestions. The default implementation is a no-op so the deterministic engine
 * works without any LLM.
 */
interface MappingSuggestionRefinerInterface
{
    /**
     * @param list<MappingSuggestion> $suggestions
     *
     * @return list<MappingSuggestion>
     */
    public function refine(array $suggestions, ExtractedTemplateField $field): array;
}
