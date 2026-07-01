<?php

declare(strict_types=1);

namespace App\Demo\Sensors;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\McpTool;
use App\Demo\Sensors\State\SensorDemoProvider;

#[McpTool(
    name: 'get_live_sensor_threshold_status',
    description: 'Return the normalized live threshold status reported through Metadatapp.',
    uriTemplate: '/demo/sensors/threshold-status',
    provider: SensorDemoProvider::class,
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
#[ApiResource(
    shortName: 'DemoSensorThresholdStatus',
    provider: SensorDemoProvider::class,
    operations: [
        new Get(uriTemplate: '/demo/sensors/threshold-status'),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class SensorThresholdStatusView
{
    /**
     * @param array<string, array{available: bool, value: float|null, unit: string, status: string, alarm: bool}> $thresholdStatus
     * @param list<array{code: string, level: string, message: string}>                                           $activeAlarms
     */
    public function __construct(
        public bool $ok,
        public bool $enabled,
        public bool $upstreamReachable,
        public ?string $observedAt,
        public ?string $sensorId,
        public ?string $sourcePort,
        public ?string $schemaVersion,
        public bool $hasActiveAlarms,
        public string $overallStatus,
        public array $thresholdStatus,
        public array $activeAlarms,
        public ?string $message,
    ) {
    }
}
