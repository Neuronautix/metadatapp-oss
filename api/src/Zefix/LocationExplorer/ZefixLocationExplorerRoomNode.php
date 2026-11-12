<?php

declare(strict_types=1);

namespace App\Zefix\LocationExplorer;

final readonly class ZefixLocationExplorerRoomNode
{
    /**
     * @param list<ZefixLocationExplorerRackNode> $racks
     */
    public function __construct(
        public string $roomId,
        public string $name,
        public array $racks,
    ) {
    }
}
