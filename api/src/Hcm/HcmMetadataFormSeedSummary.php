<?php

declare(strict_types=1);

namespace App\Hcm;

/**
 * Immutable summary returned by {@see HcmMetadataFormSeeder::seed()} describing
 * what was materialised (or skipped) for an account.
 */
final readonly class HcmMetadataFormSeedSummary
{
    public function __construct(
        public bool $created,
        public int $cdes,
        public int $sections,
        public int $fields,
    ) {
    }

    public static function skipped(): self
    {
        return new self(false, 0, 0, 0);
    }
}
