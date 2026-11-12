<?php

declare(strict_types=1);

namespace App\Zefix\Lines;

final readonly class ZefixLineBatchView
{
    /**
     * @param list<array<string, mixed>> $events
     */
    public function __construct(
        public string $batchID,
        public string $lineID,
        public string $birthDate,
        public ZefixLineBatchSexRatio $sexRatio,
        public int $initialCount,
        public int $individualCount,
        public string $locationId,
        public string $systemID,
        public string $roomID,
        public array $events,
        public string $lineName,
        public string $roomName,
        public string $tankLocation,
        public int $ageDays,
        public string $ageLabel,
        public int $mortalityCount,
    ) {
    }
}
