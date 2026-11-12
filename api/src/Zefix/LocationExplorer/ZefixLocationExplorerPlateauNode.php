<?php

declare(strict_types=1);

namespace App\Zefix\LocationExplorer;

final readonly class ZefixLocationExplorerPlateauNode
{
    /**
     * @param list<ZefixLocationExplorerRoomNode> $rooms
     */
    public function __construct(
        public string $plateauId,
        public string $name,
        public string $code,
        public array $rooms,
    ) {
    }
}
