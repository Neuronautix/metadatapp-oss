<?php

declare(strict_types=1);

namespace App\Demo\Sensors;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\McpTool;
use App\Demo\Sensors\State\SensorDemoProvider;

#[McpTool(
    name: 'get_live_sensor_alarm_status',
    description: 'Return the active live sensor alarms derived by Metadatapp.',
    uriTemplate: '/demo/sensors/alarm-status',
    provider: SensorDemoProvider::class,
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
#[ApiResource(
    shortName: 'DemoSensorAlarmStatus',
    provider: SensorDemoProvider::class,
    operations: [
        new Get(uriTemplate: '/demo/sensors/alarm-status'),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class SensorAlarmStatusView
{
    /**
     * @param array{isFresh: bool, ageSeconds: int|null}                $freshness
     * @param list<array{code: string, level: string, message: string}> $activeAlarms
     */
    public function __construct(
        public bool $ok,
        public bool $enabled,
        public bool $upstreamReachable,
        public array $freshness,
        public string $alarmLevel,
        public array $activeAlarms,
        public ?string $message,
    ) {
    }
}
