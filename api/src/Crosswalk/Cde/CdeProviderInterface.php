<?php

declare(strict_types=1);

namespace App\Crosswalk\Cde;

/**
 * A source of Common Data Elements and CDE Forms. NIH CDE is one provider among
 * several (local Metadatapp, custom JSON, etc.) — never the foundation.
 */
interface CdeProviderInterface
{
    /**
     * Stable provider key: 'metadatapp' | 'nih_cde' | 'custom_json'.
     */
    public function key(): string;

    /**
     * @return list<CdeCandidate>
     */
    public function searchCdes(string $query): array;

    public function getCde(string $externalId): ?CdeCandidate;

    /**
     * @return list<FormCandidate>
     */
    public function searchForms(string $query): array;

    public function getForm(string $externalId): ?FormCandidate;
}
