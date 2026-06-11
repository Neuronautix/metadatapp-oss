<?php

declare(strict_types=1);

namespace App\Demo\Sensors;

use App\Service\SensorAgentClient;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class SensorDemoService
{
    private const METRICS = ['temperature', 'humidity', 'pressure'];
    private const METRIC_PRECISION = 2;
    private const HEALTH_STATUSES = ['ready', 'stale', 'waiting_for_data'];
    private const HISTORY_CACHE_KEY = 'demo_sensor_history.v1';
    private const HISTORY_SEED_POINTS = 72;
    private const HISTORY_SEED_INTERVAL_SECONDS = 300;
    private const HISTORY_RETENTION_MINUTES = 720;

    public function __construct(
        private SensorAgentClient $sensorAgentClient,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     sensorId: string|null,
     *     sourcePort: string|null,
     *     schemaVersion: string|null,
     *     observedAt: string|null,
     *     rangeMinutes: int,
     *     maxPoints: int,
     *     points: list<array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}>,
     *     message: string|null
     * }
     */
    public function getHistory(int $rangeMinutes = 180, int $maxPoints = 180): array
    {
        $rangeMinutes = max(15, min(self::HISTORY_RETENTION_MINUTES, $rangeMinutes));
        $maxPoints = max(24, min(720, $maxPoints));

        $latest = $this->getLatestObservation();
        $history = $this->loadHistory();

        if ($latest['ok'] && null !== $latest['observedAt']) {
            if ([] === $history) {
                $history = $this->seedHistory($latest);
            }

            $history = $this->appendObservation($history, [
                'observedAt' => $latest['observedAt'],
                'temperatureC' => $latest['temperatureC'],
                'humidityPct' => $latest['humidityPct'],
                'pressureHpa' => $latest['pressureHpa'],
            ]);

            $this->saveHistory($history);
        }

        $filteredHistory = $this->filterHistory($history, $rangeMinutes, $maxPoints);

        return [
            'ok' => [] !== $filteredHistory,
            'enabled' => $latest['enabled'],
            'upstreamReachable' => $latest['upstreamReachable'],
            'sensorId' => $latest['sensorId'],
            'sourcePort' => $latest['sourcePort'],
            'schemaVersion' => $latest['schemaVersion'],
            'observedAt' => $latest['observedAt'] ?? ($filteredHistory[array_key_last($filteredHistory)]['observedAt'] ?? null),
            'rangeMinutes' => $rangeMinutes,
            'maxPoints' => $maxPoints,
            'points' => $filteredHistory,
            'message' => $latest['message'],
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     status: string,
     *     latestObservationAvailable: bool,
     *     lastObservationAt: string|null,
     *     freshnessAgeSeconds: int|null,
     *     isFresh: bool,
     *     message: string|null
     * }
     */
    public function getHealth(): array
    {
        if (!$this->sensorAgentClient->isEnabled()) {
            return $this->disabledHealthState();
        }

        try {
            $health = $this->normalizeHealthPayload($this->sensorAgentClient->getHealth());

            return [
                'ok' => true,
                'enabled' => true,
                'upstreamReachable' => true,
                'status' => $health['status'],
                'latestObservationAvailable' => $health['latestObservationAvailable'],
                'lastObservationAt' => $health['lastObservationAt'],
                'freshnessAgeSeconds' => $health['freshnessAgeSeconds'],
                'isFresh' => $health['isFresh'],
                'message' => $this->resolveHealthMessage($health),
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('Live sensor health could not be assembled.', [
                'error' => $exception->getMessage(),
            ]);

            return $this->unreachableHealthState();
        }
    }

    /**
     * @return array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     sensorId: string|null,
     *     sourcePort: string|null,
     *     schemaVersion: string|null,
     *     observedAt: string|null,
     *     temperatureC: float|null,
     *     humidityPct: float|null,
     *     pressureHpa: float|null,
     *     message: string|null
     * }
     */
    public function getLatestObservation(): array
    {
        $health = $this->getHealth();
        if (true !== $health['enabled'] || true !== $health['upstreamReachable']) {
            return $this->emptyObservationState(
                enabled: $health['enabled'],
                upstreamReachable: $health['upstreamReachable'],
                message: $health['message'],
            );
        }

        if (true !== $health['latestObservationAvailable']) {
            return $this->emptyObservationState(
                enabled: true,
                upstreamReachable: true,
                message: $health['message'],
            );
        }

        try {
            $observation = $this->normalizeObservationPayload($this->sensorAgentClient->getLatestObservation());

            return [
                'ok' => true,
                'enabled' => true,
                'upstreamReachable' => true,
                'sensorId' => $observation['sensorId'],
                'sourcePort' => $observation['sourcePort'],
                'schemaVersion' => $observation['schemaVersion'],
                'observedAt' => $observation['observedAt'],
                'temperatureC' => $observation['temperatureC'],
                'humidityPct' => $observation['humidityPct'],
                'pressureHpa' => $observation['pressureHpa'],
                'message' => $health['message'],
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('Live sensor observation could not be assembled.', [
                'error' => $exception->getMessage(),
            ]);

            return $this->emptyObservationState(
                enabled: true,
                upstreamReachable: false,
                message: 'Live sensor data is currently unavailable.',
            );
        }
    }

    /**
     * @return array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     metric: string,
     *     value: float|null,
     *     unit: string,
     *     timestamp: string|null,
     *     freshness: array{isFresh: bool, ageSeconds: int|null},
     *     alarmLevel: string,
     *     activeAlarms: list<array{code: string, level: string, message: string}>,
     *     message: string|null
     * }
     */
    public function getMetric(string $metric): array
    {
        $metric = $this->assertMetric($metric);
        $health = $this->getHealth();
        $alarmStatus = $this->buildAlarmStatus($health, null, null);

        if (true !== $health['enabled'] || true !== $health['upstreamReachable']) {
            return [
                'ok' => false,
                'enabled' => $health['enabled'],
                'upstreamReachable' => $health['upstreamReachable'],
                'metric' => $metric,
                'value' => null,
                'unit' => $this->unitForMetric($metric),
                'timestamp' => $health['lastObservationAt'],
                'freshness' => $this->freshnessFromHealth($health),
                'alarmLevel' => $alarmStatus['alarmLevel'],
                'activeAlarms' => $alarmStatus['activeAlarms'],
                'message' => $health['message'],
            ];
        }

        try {
            $latest = $this->getLatestObservation();
            $rawValue = match ($metric) {
                'temperature' => $latest['temperatureC'],
                'humidity' => $latest['humidityPct'],
                'pressure' => $latest['pressureHpa'],
                default => throw new \InvalidArgumentException(\sprintf('Unsupported sensor metric "%s".', $metric)),
            };
            $value = is_numeric($rawValue) ? round((float) $rawValue, self::METRIC_PRECISION) : null;

            return [
                'ok' => $latest['ok'],
                'enabled' => true,
                'upstreamReachable' => true,
                'metric' => $metric,
                'value' => $value,
                'unit' => $this->unitForMetric($metric),
                'timestamp' => $latest['observedAt'],
                'freshness' => $this->freshnessFromHealth($health),
                'alarmLevel' => $alarmStatus['alarmLevel'],
                'activeAlarms' => $alarmStatus['activeAlarms'],
                'message' => $latest['message'],
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('Live sensor metric could not be assembled.', [
                'metric' => $metric,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'enabled' => true,
                'upstreamReachable' => false,
                'metric' => $metric,
                'value' => null,
                'unit' => $this->unitForMetric($metric),
                'timestamp' => null,
                'freshness' => ['isFresh' => false, 'ageSeconds' => null],
                'alarmLevel' => 'critical',
                'activeAlarms' => [[
                    'code' => 'upstream_unreachable',
                    'level' => 'critical',
                    'message' => 'Live sensor upstream is unreachable.',
                ]],
                'message' => 'Live sensor data is currently unavailable.',
            ];
        }
    }

    /**
     * @return array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     observedAt: string|null,
     *     sensorId: string|null,
     *     sourcePort: string|null,
     *     schemaVersion: string|null,
     *     hasActiveAlarms: bool,
     *     overallStatus: string,
     *     activeAlarms: list<array{code: string, level: string, message: string}>,
     *     thresholdStatus: array<string, array{available: bool, value: float|null, unit: string, status: string, alarm: bool}>,
     *     message: string|null
     * }
     */
    public function getThresholdStatus(): array
    {
        $health = $this->getHealth();
        if (true !== $health['enabled'] || true !== $health['upstreamReachable']) {
            return $this->emptyThresholdState(
                enabled: $health['enabled'],
                upstreamReachable: $health['upstreamReachable'],
                message: $health['message'],
            );
        }

        if (true !== $health['latestObservationAvailable']) {
            return $this->emptyThresholdState(
                enabled: true,
                upstreamReachable: true,
                message: $health['message'],
                observedAt: $health['lastObservationAt'],
            );
        }

        try {
            $thresholdStatus = $this->normalizeThresholdStatusPayload($this->sensorAgentClient->getThresholdStatus());

            return [
                'ok' => true,
                'enabled' => true,
                'upstreamReachable' => true,
                'observedAt' => $thresholdStatus['observedAt'],
                'sensorId' => $thresholdStatus['sensorId'],
                'sourcePort' => $thresholdStatus['sourcePort'],
                'schemaVersion' => $thresholdStatus['schemaVersion'],
                'hasActiveAlarms' => $thresholdStatus['hasActiveAlarms'],
                'overallStatus' => $thresholdStatus['overallStatus'],
                'activeAlarms' => $thresholdStatus['activeAlarms'],
                'thresholdStatus' => $thresholdStatus['thresholdStatus'],
                'message' => $health['message'],
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('Live sensor threshold status could not be assembled.', [
                'error' => $exception->getMessage(),
            ]);

            return $this->emptyThresholdState(
                enabled: true,
                upstreamReachable: false,
                message: 'Live sensor data is currently unavailable.',
            );
        }
    }

    /**
     * @return array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     freshness: array{isFresh: bool, ageSeconds: int|null},
     *     alarmLevel: string,
     *     activeAlarms: list<array{code: string, level: string, message: string}>,
     *     message: string|null
     * }
     */
    public function getAlarmStatus(): array
    {
        $health = $this->getHealth();
        $threshold = $this->getThresholdStatus();
        $alarmStatus = $this->buildAlarmStatus($health, $threshold['overallStatus'], $threshold['activeAlarms']);

        return [
            'ok' => $health['ok'] && $threshold['ok'],
            'enabled' => $health['enabled'],
            'upstreamReachable' => $health['upstreamReachable'],
            'freshness' => $this->freshnessFromHealth($health),
            'alarmLevel' => $alarmStatus['alarmLevel'],
            'activeAlarms' => $alarmStatus['activeAlarms'],
            'message' => $health['message'] ?? $threshold['message'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     status: string,
     *     latestObservationAvailable: bool,
     *     lastObservationAt: string|null,
     *     freshnessAgeSeconds: int|null,
     *     isFresh: bool
     * }
     */
    private function normalizeHealthPayload(array $payload): array
    {
        $lastObservationAt = $this->extractNullableString($payload, 'lastObservationAt', 'observedAt', 'timestamp');
        $status = $this->extractHealthStatus($payload, $lastObservationAt);
        $latestObservationAvailable = (bool) ($payload['latestObservationAvailable'] ?? ('waiting_for_data' !== $status));
        $freshnessAgeSeconds = $this->extractNullableInt($payload, 'freshnessAgeSeconds');

        if (null === $freshnessAgeSeconds && null !== $lastObservationAt) {
            $freshnessAgeSeconds = max(0, $this->clock->now()->getTimestamp() - (new \DateTimeImmutable($lastObservationAt))->getTimestamp());
        }

        return [
            'status' => $status,
            'latestObservationAvailable' => $latestObservationAvailable,
            'lastObservationAt' => $lastObservationAt,
            'freshnessAgeSeconds' => $freshnessAgeSeconds,
            'isFresh' => isset($payload['isFresh']) ? (bool) $payload['isFresh'] : 'ready' === $status,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     sensorId: string|null,
     *     sourcePort: string|null,
     *     schemaVersion: string|null,
     *     observedAt: string,
     *     temperatureC: float,
     *     humidityPct: float,
     *     pressureHpa: float|null
     * }
     */
    private function normalizeObservationPayload(array $payload): array
    {
        return [
            'sensorId' => $this->extractNullableString($payload, 'sensorId'),
            'sourcePort' => $this->extractNullableString($payload, 'sourcePort'),
            'schemaVersion' => $this->extractNullableString($payload, 'schemaVersion'),
            'observedAt' => $this->extractRequiredString($payload, 'observedAt', 'timestamp'),
            'temperatureC' => $this->extractFloat($payload, 'temperatureC', 'temperature', 'temp'),
            'humidityPct' => $this->extractFloat($payload, 'humidityPct', 'humidity'),
            'pressureHpa' => $this->extractOptionalFloat($payload, 'pressureHpa', 'pressure'),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     observedAt: string|null,
     *     sensorId: string|null,
     *     sourcePort: string|null,
     *     schemaVersion: string|null,
     *     hasActiveAlarms: bool,
     *     overallStatus: string,
     *     activeAlarms: list<array{code: string, level: string, message: string}>,
     *     thresholdStatus: array<string, array{available: bool, value: float|null, unit: string, status: string, alarm: bool}>
     * }
     */
    private function normalizeThresholdStatusPayload(array $payload): array
    {
        $thresholdPayload = $payload['thresholdStatus'] ?? $payload;
        $thresholdPayload = \is_array($thresholdPayload) ? $thresholdPayload : [];

        $thresholdStatus = [];
        foreach (self::METRICS as $metric) {
            $thresholdStatus[$metric] = $this->normalizeThresholdMetric($metric, $thresholdPayload[$metric] ?? null);
        }

        $activeAlarms = $this->normalizeActiveAlarms($payload['activeAlarms'] ?? []);
        if ([] === $activeAlarms) {
            foreach ($thresholdStatus as $metric => $metricStatus) {
                if (true === $metricStatus['alarm']) {
                    $activeAlarms[] = [
                        'code' => $metric . '_alarm',
                        'level' => 'warning',
                        'message' => \sprintf('%s threshold is in alarm state.', ucfirst($metric)),
                    ];
                }
            }
        }

        $overallStatus = $this->extractNullableString($payload, 'overallStatus') ?? $this->deriveOverallStatus($thresholdStatus, $activeAlarms);

        return [
            'observedAt' => $this->extractNullableString($payload, 'observedAt'),
            'sensorId' => $this->extractNullableString($payload, 'sensorId'),
            'sourcePort' => $this->extractNullableString($payload, 'sourcePort'),
            'schemaVersion' => $this->extractNullableString($payload, 'schemaVersion'),
            'hasActiveAlarms' => (bool) ($payload['hasActiveAlarms'] ?? ([] !== $activeAlarms)),
            'overallStatus' => $overallStatus,
            'activeAlarms' => $activeAlarms,
            'thresholdStatus' => $thresholdStatus,
        ];
    }

    /**
     * @return array{available: bool, value: float|null, unit: string, status: string, alarm: bool}
     */
    private function normalizeThresholdMetric(string $metric, mixed $entry): array
    {
        $defaultUnits = [
            'temperature' => 'C',
            'humidity' => '%',
            'pressure' => 'hPa',
        ];

        if (!\is_array($entry)) {
            return [
                'available' => false,
                'value' => null,
                'unit' => $defaultUnits[$metric],
                'status' => 'unavailable',
                'alarm' => false,
            ];
        }

        return [
            'available' => (bool) ($entry['available'] ?? false),
            'value' => $this->extractOptionalFloat($entry, 'value'),
            'unit' => $this->extractNullableString($entry, 'unit') ?? $defaultUnits[$metric],
            'status' => strtolower((string) ($entry['status'] ?? 'unknown')),
            'alarm' => (bool) ($entry['alarm'] ?? false),
        ];
    }

    /**
     * @return list<array{code: string, level: string, message: string}>
     */
    private function normalizeActiveAlarms(mixed $rawActiveAlarms): array
    {
        if (!\is_array($rawActiveAlarms)) {
            return [];
        }

        $normalized = [];
        foreach ($rawActiveAlarms as $index => $alarm) {
            if (\is_string($alarm) && '' !== trim($alarm)) {
                $normalized[] = [
                    'code' => 'alarm_' . $index,
                    'level' => 'warning',
                    'message' => $alarm,
                ];
                continue;
            }

            if (!\is_array($alarm)) {
                continue;
            }

            $message = $this->extractNullableString($alarm, 'message', 'label', 'code') ?? 'Sensor alarm is active.';
            $normalized[] = [
                'code' => $this->extractNullableString($alarm, 'code') ?? 'alarm_' . $index,
                'level' => $this->normalizeAlarmLevel($this->extractNullableString($alarm, 'level', 'severity') ?? 'warning'),
                'message' => $message,
            ];
        }

        return $normalized;
    }

    /**
     * @param array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     status: string,
     *     latestObservationAvailable: bool,
     *     lastObservationAt: string|null,
     *     freshnessAgeSeconds: int|null,
     *     isFresh: bool,
     *     message: string|null
     * } $health
     * @param list<array{code: string, level: string, message: string}>|null $thresholdAlarms
     *
     * @return array{
     *     alarmLevel: string,
     *     activeAlarms: list<array{code: string, level: string, message: string}>
     * }
     */
    private function buildAlarmStatus(array $health, ?string $overallStatus, ?array $thresholdAlarms): array
    {
        if (true !== $health['upstreamReachable']) {
            return [
                'alarmLevel' => 'critical',
                'activeAlarms' => [[
                    'code' => 'upstream_unreachable',
                    'level' => 'critical',
                    'message' => 'Live sensor upstream is unreachable.',
                ]],
            ];
        }

        $activeAlarms = $thresholdAlarms ?? [];

        if ('waiting_for_data' === $health['status']) {
            $activeAlarms[] = [
                'code' => 'waiting_for_data',
                'level' => 'warning',
                'message' => 'Waiting for the first sensor observation.',
            ];
        }

        if ('stale' === $health['status']) {
            $age = $health['freshnessAgeSeconds'];
            $activeAlarms[] = [
                'code' => 'stale_data',
                'level' => 'warning',
                'message' => \is_int($age)
                    ? \sprintf('Latest sensor data is stale (%d seconds old).', $age)
                    : 'Latest sensor data is stale.',
            ];
        }

        $alarmLevel = 'ok';
        if ('critical' === $overallStatus || $this->containsAlarmLevel($activeAlarms, 'critical')) {
            $alarmLevel = 'critical';
        } elseif (
            'warning' === $overallStatus
            || 'stale' === $health['status']
            || 'waiting_for_data' === $health['status']
            || [] !== $activeAlarms
        ) {
            $alarmLevel = 'warning';
        }

        return [
            'alarmLevel' => $alarmLevel,
            'activeAlarms' => $activeAlarms,
        ];
    }

    /**
     * @param list<array{code: string, level: string, message: string}> $alarms
     */
    private function containsAlarmLevel(array $alarms, string $level): bool
    {
        foreach ($alarms as $alarm) {
            if ($level === $alarm['level']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     status: string,
     *     latestObservationAvailable: bool,
     *     lastObservationAt: string|null,
     *     freshnessAgeSeconds: int|null,
     *     isFresh: bool,
     *     message: string|null
     * } $health
     *
     * @return array{isFresh: bool, ageSeconds: int|null}
     */
    private function freshnessFromHealth(array $health): array
    {
        return [
            'isFresh' => $health['isFresh'],
            'ageSeconds' => $health['freshnessAgeSeconds'],
        ];
    }

    /**
     * @param array{
     *     status: string,
     *     latestObservationAvailable: bool,
     *     lastObservationAt: string|null,
     *     freshnessAgeSeconds: int|null,
     *     isFresh: bool
     * } $health
     */
    private function resolveHealthMessage(array $health): ?string
    {
        return match ($health['status']) {
            'waiting_for_data' => 'Waiting for the first observation from the Raspberry Pi sensor.',
            'stale' => \is_int($health['freshnessAgeSeconds'])
                ? \sprintf('Latest sensor data is stale (%d seconds old).', $health['freshnessAgeSeconds'])
                : 'Latest sensor data is stale.',
            default => null,
        };
    }

    /**
     * @param array<string, array{available: bool, value: float|null, unit: string, status: string, alarm: bool}> $thresholdStatus
     * @param list<array{code: string, level: string, message: string}>                                           $activeAlarms
     */
    private function deriveOverallStatus(array $thresholdStatus, array $activeAlarms): string
    {
        foreach ($thresholdStatus as $metricStatus) {
            if ('critical' === $metricStatus['status']) {
                return 'critical';
            }

            if (true === $metricStatus['alarm'] || 'warning' === $metricStatus['status']) {
                return 'warning';
            }
        }

        return [] === $activeAlarms ? 'normal' : 'warning';
    }

    private function unitForMetric(string $metric): string
    {
        return match ($metric) {
            'temperature' => 'C',
            'humidity' => '%',
            'pressure' => 'hPa',
            default => throw new \InvalidArgumentException(\sprintf('Unsupported sensor metric "%s".', $metric)),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractHealthStatus(array $payload, ?string $lastObservationAt): string
    {
        $status = strtolower((string) ($payload['status'] ?? ''));
        if (\in_array($status, self::HEALTH_STATUSES, true)) {
            return $status;
        }

        if (false === ($payload['latestObservationAvailable'] ?? true)) {
            return 'waiting_for_data';
        }

        return null === $lastObservationAt ? 'waiting_for_data' : 'ready';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractRequiredString(array $payload, string ...$keys): string
    {
        $value = $this->extractNullableString($payload, ...$keys);
        if (null === $value) {
            throw new \RuntimeException(\sprintf('Missing string value for %s.', implode(', ', $keys)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractNullableString(array $payload, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (\is_string($value) && '' !== trim($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractNullableInt(array $payload, string ...$keys): ?int
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_numeric($value)) {
                return (int) round((float) $value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractFloat(array $payload, string ...$keys): float
    {
        $value = $this->extractOptionalFloat($payload, ...$keys);
        if (null === $value) {
            throw new \RuntimeException(\sprintf('Missing numeric value for %s.', implode(', ', $keys)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractOptionalFloat(array $payload, string ...$keys): ?float
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (\is_array($value) && \array_key_exists('value', $value)) {
                $value = $value['value'];
            }

            if (is_numeric($value)) {
                return round((float) $value, self::METRIC_PRECISION);
            }
        }

        return null;
    }

    private function normalizeAlarmLevel(string $level): string
    {
        $level = strtolower($level);

        return match ($level) {
            'critical', 'warning' => $level,
            default => 'warning',
        };
    }

    private function assertMetric(string $metric): string
    {
        if (!\in_array($metric, self::METRICS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported sensor metric "%s".', $metric));
        }

        return $metric;
    }

    /**
     * @return array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     status: string,
     *     latestObservationAvailable: bool,
     *     lastObservationAt: string|null,
     *     freshnessAgeSeconds: int|null,
     *     isFresh: bool,
     *     message: string
     * }
     */
    private function disabledHealthState(): array
    {
        return [
            'ok' => false,
            'enabled' => false,
            'upstreamReachable' => false,
            'status' => 'waiting_for_data',
            'latestObservationAvailable' => false,
            'lastObservationAt' => null,
            'freshnessAgeSeconds' => null,
            'isFresh' => false,
            'message' => 'Sovereign Sensor Agent integration is disabled.',
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     status: string,
     *     latestObservationAvailable: bool,
     *     lastObservationAt: string|null,
     *     freshnessAgeSeconds: int|null,
     *     isFresh: bool,
     *     message: string
     * }
     */
    private function unreachableHealthState(): array
    {
        return [
            'ok' => false,
            'enabled' => true,
            'upstreamReachable' => false,
            'status' => 'stale',
            'latestObservationAvailable' => false,
            'lastObservationAt' => null,
            'freshnessAgeSeconds' => null,
            'isFresh' => false,
            'message' => 'Live sensor data is currently unavailable.',
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     sensorId: string|null,
     *     sourcePort: string|null,
     *     schemaVersion: string|null,
     *     observedAt: string|null,
     *     temperatureC: float|null,
     *     humidityPct: float|null,
     *     pressureHpa: float|null,
     *     message: string|null
     * }
     */
    private function emptyObservationState(bool $enabled, bool $upstreamReachable, ?string $message): array
    {
        return [
            'ok' => false,
            'enabled' => $enabled,
            'upstreamReachable' => $upstreamReachable,
            'sensorId' => null,
            'sourcePort' => null,
            'schemaVersion' => null,
            'observedAt' => null,
            'temperatureC' => null,
            'humidityPct' => null,
            'pressureHpa' => null,
            'message' => $message,
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     enabled: bool,
     *     upstreamReachable: bool,
     *     observedAt: string|null,
     *     sensorId: string|null,
     *     sourcePort: string|null,
     *     schemaVersion: string|null,
     *     hasActiveAlarms: bool,
     *     overallStatus: string,
     *     activeAlarms: list<array{code: string, level: string, message: string}>,
     *     thresholdStatus: array<string, array{available: bool, value: float|null, unit: string, status: string, alarm: bool}>,
     *     message: string|null
     * }
     */
    private function emptyThresholdState(bool $enabled, bool $upstreamReachable, ?string $message, ?string $observedAt = null): array
    {
        return [
            'ok' => false,
            'enabled' => $enabled,
            'upstreamReachable' => $upstreamReachable,
            'observedAt' => $observedAt,
            'sensorId' => null,
            'sourcePort' => null,
            'schemaVersion' => null,
            'hasActiveAlarms' => false,
            'overallStatus' => 'normal',
            'activeAlarms' => [],
            'thresholdStatus' => [
                'temperature' => ['available' => false, 'value' => null, 'unit' => 'C', 'status' => 'unavailable', 'alarm' => false],
                'humidity' => ['available' => false, 'value' => null, 'unit' => '%', 'status' => 'unavailable', 'alarm' => false],
                'pressure' => ['available' => false, 'value' => null, 'unit' => 'hPa', 'status' => 'unavailable', 'alarm' => false],
            ],
            'message' => $message,
        ];
    }

    /**
     * @return list<array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}>
     */
    private function loadHistory(): array
    {
        $item = $this->cache->getItem(self::HISTORY_CACHE_KEY);
        $value = $item->get();

        if (!\is_array($value)) {
            return [];
        }

        $history = [];
        foreach ($value as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $observedAt = $entry['observedAt'] ?? null;
            if (!\is_string($observedAt) || '' === $observedAt) {
                continue;
            }

            $history[] = [
                'observedAt' => $observedAt,
                'temperatureC' => $this->normalizeHistoryMetric($entry['temperatureC'] ?? null),
                'humidityPct' => $this->normalizeHistoryMetric($entry['humidityPct'] ?? null),
                'pressureHpa' => $this->normalizeHistoryMetric($entry['pressureHpa'] ?? null),
            ];
        }

        return $history;
    }

    private function normalizeHistoryMetric(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, self::METRIC_PRECISION);
    }

    /**
     * @param list<array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}> $history
     */
    private function saveHistory(array $history): void
    {
        $item = $this->cache->getItem(self::HISTORY_CACHE_KEY);
        $item->set($history);
        $this->cache->save($item);
    }

    /**
     * @param array{observedAt: string|null, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null} $latest
     *
     * @return list<array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}>
     */
    private function seedHistory(array $latest): array
    {
        if (null === $latest['observedAt']) {
            return [];
        }

        $end = new \DateTimeImmutable($latest['observedAt']);
        $history = [];

        for ($index = self::HISTORY_SEED_POINTS - 1; $index >= 1; --$index) {
            $timestamp = $end->modify(\sprintf('-%d seconds', $index * self::HISTORY_SEED_INTERVAL_SECONDS));
            $history[] = [
                'observedAt' => $timestamp->format(\DATE_ATOM),
                'temperatureC' => $this->seedMetricValue($latest['temperatureC'], $index, 0.6),
                'humidityPct' => $this->seedMetricValue($latest['humidityPct'], $index, 2.5),
                'pressureHpa' => $this->seedMetricValue($latest['pressureHpa'], $index, 1.8),
            ];
        }

        return $history;
    }

    private function seedMetricValue(?float $currentValue, int $index, float $amplitude): ?float
    {
        if (null === $currentValue) {
            return null;
        }

        $wave = sin($index / 4) * $amplitude;
        $drift = cos($index / 6) * ($amplitude / 3);

        return round($currentValue - $wave - $drift, self::METRIC_PRECISION);
    }

    /**
     * @param list<array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}> $history
     * @param array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}       $observation
     *
     * @return list<array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}>
     */
    private function appendObservation(array $history, array $observation): array
    {
        foreach ($history as $index => $existing) {
            if ($existing['observedAt'] === $observation['observedAt']) {
                $history[$index] = $observation;

                usort($history, static fn (array $left, array $right): int => strcmp($left['observedAt'], $right['observedAt']));

                return $history;
            }
        }

        $history[] = $observation;
        usort($history, static fn (array $left, array $right): int => strcmp($left['observedAt'], $right['observedAt']));

        return $this->trimHistory($history);
    }

    /**
     * @param list<array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}> $history
     *
     * @return list<array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}>
     */
    private function trimHistory(array $history): array
    {
        $cutoff = $this->clock->now()->modify(\sprintf('-%d minutes', self::HISTORY_RETENTION_MINUTES));
        $trimmed = array_values(array_filter(
            $history,
            static fn (array $point): bool => new \DateTimeImmutable($point['observedAt']) >= $cutoff,
        ));

        if (\count($trimmed) <= 720) {
            return $trimmed;
        }

        return \array_slice($trimmed, -720);
    }

    /**
     * @param list<array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}> $history
     *
     * @return list<array{observedAt: string, temperatureC: float|null, humidityPct: float|null, pressureHpa: float|null}>
     */
    private function filterHistory(array $history, int $rangeMinutes, int $maxPoints): array
    {
        $cutoff = $this->clock->now()->modify(\sprintf('-%d minutes', $rangeMinutes));
        $filtered = array_values(array_filter(
            $history,
            static fn (array $point): bool => new \DateTimeImmutable($point['observedAt']) >= $cutoff,
        ));

        if (\count($filtered) > $maxPoints) {
            $filtered = \array_slice($filtered, -$maxPoints);
        }

        return $filtered;
    }
}
