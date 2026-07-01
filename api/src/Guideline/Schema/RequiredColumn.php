<?php

declare(strict_types=1);

namespace App\Guideline\Schema;

/**
 * The COLUMN-level compliance tier (MNMS uses this). §2.2.
 *
 * A required (or optional) CSV column, matched against header names via
 * {@see self::$namePatterns} (case-insensitive substring or regex). The
 * critical bridge to ARRIVE is {@see self::$arriveSection}: it maps a raw
 * data column to an ARRIVE reporting section.
 */
final readonly class RequiredColumn
{
    /**
     * @param list<string> $namePatterns substrings OR regexes matched against CSV header names
     */
    public function __construct(
        public string $id,
        public array $namePatterns,
        public ?string $semanticType = null,
        public ?string $role = null,
        public ?string $arriveSection = null,
        public bool $unitRequired = false,
        public ?string $unitColumn = null,
        public bool $required = true,
        public string $severity = 'medium',
    ) {
    }
}
