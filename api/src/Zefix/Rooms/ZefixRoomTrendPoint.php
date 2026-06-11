<?php

declare(strict_types=1);

namespace App\Zefix\Rooms;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Zefix\Rooms\State\ZefixRoomProvider;

#[ApiResource(
    shortName: 'ZefixRoomTrendPoint',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/zefix/rooms/{roomID}/history',
            provider: ZefixRoomProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixRoomTrendPoint
{
    public function __construct(
        public string $timestamp,
        public float $temperature,
        public float $pH,
    ) {
    }
}
