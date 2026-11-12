<?php

declare(strict_types=1);

namespace App\Demo\Sensors;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use App\Demo\Sensors\State\SensorDemoProvider;

#[McpTool(
    name: 'get_live_sensor_metric',
    description: 'Return the latest live value for a specific sensor metric (temperature, humidity, pressure).',
    uriTemplate: '/demo/sensors/metric/{metric}',
    uriVariables: ['metric' => new Link()],
    provider: SensorDemoProvider::class,
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
    parameters: new Parameters([
        'metric' => new QueryParameter(
            key: 'metric',
            description: 'Which metric to read.',
            schema: ['type' => 'string', 'enum' => ['temperature', 'humidity', 'pressure']],
            required: true,
        ),
    ]),
)]
#[ApiResource(
    shortName: 'DemoSensorMetric',
    provider: SensorDemoProvider::class,
    operations: [
        new Get(uriTemplate: '/demo/sensors/metric/{metric}'),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class SensorMetricView
{
    /**
     * @param array{isFresh: bool, ageSeconds: int|null}                $freshness
     * @param list<array{code: string, level: string, message: string}> $activeAlarms
     */
    public function __construct(
        public bool $ok,
        public bool $enabled,
        public bool $upstreamReachable,
        public string $metric,
        public ?float $value,
        public string $unit,
        public ?string $timestamp,
        public array $freshness,
        public string $alarmLevel,
        public array $activeAlarms,
        public ?string $message,
    ) {
    }
}
