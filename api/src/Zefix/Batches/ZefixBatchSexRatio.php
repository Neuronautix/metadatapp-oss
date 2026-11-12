<?php

declare(strict_types=1);

namespace App\Zefix\Batches;

final readonly class ZefixBatchSexRatio
{
    public function __construct(
        public int $male,
        public int $female,
        public int $unknown,
    ) {
    }
}
