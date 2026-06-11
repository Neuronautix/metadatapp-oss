<?php

declare(strict_types=1);

namespace App\Zefix\Systems;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Zefix\Systems\State\ZefixSystemProvider;

#[ApiResource(
    shortName: 'ZefixSystemSnapshot',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/zefix/systems',
            provider: ZefixSystemProvider::class,
            output: self::class,
        ),
        new Get(
            uriTemplate: '/zefix/systems/{systemID}',
            provider: ZefixSystemProvider::class,
            output: self::class,
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixSystemSnapshot
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $systemID,
        public string $manufacturer,
        public string $model,
        public string $roomID,
        public string $roomName,
        public float $waterTemperature,
        public float $pH,
        public float $conductivity,
        public float $oxygen,
        public float $waterFlow,
        public string $filtrationStatus,
        public int $activeBatches,
        public int $fishCount,
        public int $activeAlerts,
        public ?string $latestObservationAt = null,
    ) {
    }
}
