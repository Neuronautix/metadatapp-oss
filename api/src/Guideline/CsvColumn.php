<?php

declare(strict_types=1);

namespace App\Guideline;

/**
 * A single available CSV header column for column-tier validation (§6 Pass 1).
 *
 * `userUnit`/`unitGuess` carry an explicit/inferred unit so the engine can
 * decide between `satisfied` and `partial` for unit-required columns.
 */
final readonly class CsvColumn
{
    public function __construct(
        public string $name,
        public ?string $userUnit = null,
        public ?string $unitGuess = null,
    ) {
    }

    public function hasUnit(): bool
    {
        return (null !== $this->userUnit && '' !== trim($this->userUnit))
            || (null !== $this->unitGuess && '' !== trim($this->unitGuess));
    }
}
