<?php

declare(strict_types=1);

namespace App\Zefix\LocationExplorer;

final readonly class ZefixLocationExplorerBatchNode
{
    public function __construct(
        public string $batchId,
        public string $lineId,
        public string $lineName,
        public string $birthDate,
        public int $totalPopulation,
        public string $lifecycleStatus,
    ) {
    }
}
