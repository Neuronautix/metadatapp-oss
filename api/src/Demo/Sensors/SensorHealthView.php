<?php

declare(strict_types=1);

namespace App\Demo\Sensors;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\McpTool;
use App\Demo\Sensors\State\SensorDemoProvider;

#[McpTool(
    name: 'get_live_sensor_health',
    description: 'Return the live Sovereign Sensor Agent health state exposed through Metadatapp.',
    uriTemplate: '/demo/sensors/health',
    provider: SensorDemoProvider::class,
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
#[ApiResource(
    shortName: 'DemoSensorHealth',
    provider: SensorDemoProvider::class,
    operations: [
        new Get(uriTemplate: '/demo/sensors/health'),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class SensorHealthView
{
    public function __construct(
        public bool $ok,
        public bool $enabled,
        public bool $upstreamReachable,
        public string $status,
        public bool $latestObservationAvailable,
        public ?string $lastObservationAt,
        public ?int $freshnessAgeSeconds,
        public bool $isFresh,
        public ?string $message,
    ) {
    }
}
