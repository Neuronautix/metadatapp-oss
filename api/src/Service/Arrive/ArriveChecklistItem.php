<?php

declare(strict_types=1);

namespace App\Service\Arrive;

final readonly class ArriveChecklistItem
{
    public function __construct(
        public int $id,
        public string $title,
        public string $guidance,
        public string $value,
        public bool $essential,
    ) {
    }

    public function isMissing(): bool
    {
        return 'Missing' === $this->value;
    }
}
