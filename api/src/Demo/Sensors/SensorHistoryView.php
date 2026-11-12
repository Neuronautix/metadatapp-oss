<?php

declare(strict_types=1);

namespace App\Demo\Sensors;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use App\Demo\Sensors\State\SensorDemoProvider;

#[McpTool(
    name: 'get_live_sensor_history',
    description: 'Return rolling live sensor history for temperature, humidity, and pressure as captured through Metadatapp.',
    uriTemplate: '/demo/sensors/history',
    provider: SensorDemoProvider::class,
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
    parameters: new Parameters([
        'rangeMinutes' => new QueryParameter(
            key: 'rangeMinutes',
            description: 'History window in minutes.',
            schema: ['type' => 'integer', 'default' => 180],
        ),
        'maxPoints' => new QueryParameter(
            key: 'maxPoints',
            description: 'Maximum number of points to return.',
            schema: ['type' => 'integer', 'default' => 180],
        ),
    ]),
)]
#[ApiResource(
    shortName: 'DemoSensorHistory',
    provider: SensorDemoProvider::class,
    operations: [
        new Get(
            uriTemplate: '/demo/sensors/history',
            parameters: [
                'rangeMinutes' => new QueryParameter(schema: ['type' => 'integer', 'default' => 180]),
                'maxPoints' => new QueryParameter(schema: ['type' => 'integer', 'default' => 180]),
            ],
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class SensorHistoryView
{
    /**
     * @param list<array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}> $points
     */
    public function __construct(
        public bool $ok,
        public bool $enabled,
        public bool $upstreamReachable,
        #[ApiProperty(identifier: true)]
        public string $id,
        public ?string $sensorId,
        public ?string $sourcePort,
        public ?string $schemaVersion,
        public ?string $observedAt,
        public int $rangeMinutes,
        public int $maxPoints,
        public array $points,
        public ?string $message,
    ) {
    }
}
