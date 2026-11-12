<?php

declare(strict_types=1);

namespace App\Zefix\Batches;

final readonly class ZefixBatchTimelinePoint
{
    public function __construct(
        public string $id,
        public string $title,
        public string $subtitle,
        public string $timestamp,
        public string $color,
    ) {
    }
}
