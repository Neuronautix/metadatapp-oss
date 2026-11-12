<?php

declare(strict_types=1);

namespace App\Zefix\LocationExplorer;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Zefix\LocationExplorer\State\ZefixLocationExplorerProvider;

#[ApiResource(
    shortName: 'ZefixLocationExplorer',
    operations: [
        new Get(
            uriTemplate: '/zefix/location-explorer',
            provider: ZefixLocationExplorerProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixLocationExplorer
{
    /**
     * @param list<ZefixLocationExplorerPlateauNode> $plateaux
     */
    public function __construct(
        public string $facility,
        public array $plateaux,
    ) {
    }
}
