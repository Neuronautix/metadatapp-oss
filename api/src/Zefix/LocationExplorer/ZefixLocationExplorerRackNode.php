<?php

declare(strict_types=1);

namespace App\Zefix\LocationExplorer;

final readonly class ZefixLocationExplorerRackNode
{
    /**
     * @param list<ZefixLocationExplorerPoolNode> $pools
     */
    public function __construct(
        public string $rackId,
        public string $code,
        public ?string $systemId,
        public array $pools,
    ) {
    }
}
