<?php

declare(strict_types=1);

namespace App\Zefix\Lines;

final readonly class ZefixLineBatchSexRatio
{
    public function __construct(
        public int $male,
        public int $female,
        public int $unknown,
    ) {
    }
}
