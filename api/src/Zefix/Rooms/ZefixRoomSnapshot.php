<?php

declare(strict_types=1);

namespace App\Zefix\Rooms;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Zefix\Rooms\State\ZefixRoomProvider;

#[ApiResource(
    shortName: 'ZefixRoomSnapshot',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/zefix/rooms',
            provider: ZefixRoomProvider::class,
            output: self::class,
        ),
        new Get(
            uriTemplate: '/zefix/rooms/{roomID}',
            provider: ZefixRoomProvider::class,
            output: self::class,
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixRoomSnapshot
{
    /**
     * @param list<array{timestamp: string, temperature: float, pH: float}> $temperatureTrend
     * @param list<array{timestamp: string, temperature: float, pH: float}> $pHTrend
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $roomID,
        public string $roomName,
        public string $facility,
        public string $lightCycle,
        public float $roomTemperature,
        public float $humidity,
        public float $noiseLevel,
        public int $mortality30d,
        public int $breedingEvents30d,
        public array $temperatureTrend,
        public array $pHTrend,
        public ?string $latestObservationAt = null,
    ) {
    }
}
