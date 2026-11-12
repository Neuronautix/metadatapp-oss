<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Standardization;

/**
 * One metadata field reconciled across the sources in a cluster: the chosen value,
 * whether the sources genuinely conflicted, the per-source raw values (so nothing
 * is silently dropped), and an optional model rationale for the choice.
 */
final readonly class HarmonizedField
{
    /**
     * @param list<array{source: string, value: string}> $sourceValues the distinct per-source values
     */
    public function __construct(
        public string $field,
        public string $value,
        public bool $conflict,
        public array $sourceValues,
        public ?string $rationale = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'value' => $this->value,
            'conflict' => $this->conflict,
            'sourceValues' => $this->sourceValues,
            'rationale' => $this->rationale,
        ];
    }
}
