<?php

declare(strict_types=1);

namespace App\Zefix\Batches;

final readonly class ZefixBatchMortalityEvent
{
    public function __construct(
        public string $id,
        public string $type,
        public string $date,
        public int $number,
        public string $reason,
        public ?string $notes,
        public string $recorder,
    ) {
    }
}
