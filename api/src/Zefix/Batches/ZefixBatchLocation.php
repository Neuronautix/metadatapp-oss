<?php

declare(strict_types=1);

namespace App\Zefix\Batches;

final readonly class ZefixBatchLocation
{
    public function __construct(
        public string $locationId,
        public string $facility,
        public string $roomID,
        public string $roomName,
        public string $systemID,
        public string $rack,
        public string $tank,
    ) {
    }
}
