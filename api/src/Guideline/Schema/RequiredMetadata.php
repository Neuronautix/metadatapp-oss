<?php

declare(strict_types=1);

namespace App\Guideline\Schema;

/**
 * The DATASET-level compliance tier (ARRIVE, PREPARE, EQIPD use this). §2.3.
 *
 * The three `*_section` keys are the heart of standardization: a single field
 * may declare membership in ARRIVE *and* PREPARE *and* EQIPD simultaneously,
 * which is how one filled value reports against multiple standards at once.
 *
 * The {@see self::$crosswalk} list names sibling field ids that — if filled —
 * auto-satisfy this field (directional; only the declaring field is upgraded).
 *
 * Field-id prefix convention (load-bearing, §2): ARRIVE ids are unprefixed,
 * PREPARE ids carry `prepare_`, EQIPD ids carry `eqipd_`.
 */
final readonly class RequiredMetadata
{
    /**
     * @param list<string> $crosswalk sibling field_ids that, if filled, auto-satisfy this field
     */
    public function __construct(
        public string $id,
        public ?string $arriveSection = null,
        public ?string $prepareSection = null,
        public ?string $eqipdSection = null,
        public ?string $guidance = null,
        public ?string $prompt = null,
        public array $crosswalk = [],
        public string $severity = 'medium',
    ) {
    }
}
