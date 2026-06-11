<?php

declare(strict_types=1);

namespace App\Zefix\LocationExplorer;

final readonly class ZefixLocationExplorerPoolNode
{
    public function __construct(
        public string $poolId,
        public string $position,
        public int $capacity,
        public string $status,
        public bool $occupied,
        public ?ZefixLocationExplorerBatchNode $batch,
    ) {
    }
}
