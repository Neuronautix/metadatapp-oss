<?php

declare(strict_types=1);

namespace App\Demo\Sensors;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\McpTool;
use App\Demo\Sensors\State\SensorDemoProvider;

#[McpTool(
    name: 'get_live_sensor_observation',
    description: 'Return the latest live sensor observation and freshness derived by Metadatapp.',
    uriTemplate: '/demo/sensors/latest',
    provider: SensorDemoProvider::class,
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
#[ApiResource(
    shortName: 'DemoSensorObservation',
    provider: SensorDemoProvider::class,
    operations: [
        new Get(uriTemplate: '/demo/sensors/latest'),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class SensorObservationView
{
    public function __construct(
        public bool $ok,
        public bool $enabled,
        public bool $upstreamReachable,
        public ?string $sensorId,
        public ?string $sourcePort,
        public ?string $schemaVersion,
        public ?string $observedAt,
        public ?float $temperatureC,
        public ?float $humidityPct,
        public ?float $pressureHpa,
        public ?string $message,
    ) {
    }
}
