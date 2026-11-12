<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Tecniplast\Operations;

final class TecniplastOperationsDataProvider
{
    private const array CONDITION_METRICS = ['temperature', 'humidity', 'pressure', 'co2', 'light', 'noise'];
    private const array METRIC_UNITS = [
        'temperature' => 'C',
        'humidity' => '%',
        'pressure' => 'hPa',
        'co2' => 'ppm',
        'light' => 'lux',
        'noise' => 'dB',
    ];
    private const array DEFAULT_THRESHOLDS = [
        'temperature' => ['min' => 20, 'max' => 26],
        'humidity' => ['min' => 40, 'max' => 60],
        'pressure' => ['min' => 990, 'max' => 1030],
        'co2' => ['min' => 400, 'max' => 1200],
        'light' => ['min' => 20, 'max' => 140],
        'noise' => ['min' => 30, 'max' => 60],
    ];
    private static array $thresholdOverrides = [];

    public function getFacilities(?string $status = null): array
    {
        $facilities = $this->facilities();

        if (null !== $status && '' !== $status) {
            $facilities = array_values(array_filter($facilities, static fn (array $facility): bool => $facility['status'] === $status));
        }

        return array_map(fn (array $facility): array => [
            ...$facility,
            'stats' => $this->computeFacilityStats($facility['id']),
        ], $facilities);
    }

    public function getFacility(string $id): ?array
    {
        foreach ($this->facilities() as $facility) {
            if ($facility['id'] === $id) {
                return [
                    ...$facility,
                    'stats' => $this->computeFacilityStats($facility['id']),
                ];
            }
        }

        return null;
    }

    public function getRooms(?string $facilityId = null): array
    {
        return $this->filterBy($this->rooms(), 'facilityId', $facilityId);
    }

    public function getRoom(string $id): ?array
    {
        return $this->findById($this->rooms(), $id);
    }

    public function getRacks(?string $roomId = null): array
    {
        return $this->filterBy($this->racks(), 'roomId', $roomId);
    }

    public function getRack(string $id): ?array
    {
        return $this->findById($this->racks(), $id);
    }

    public function getCages(?string $rackId = null): array
    {
        return $this->filterBy($this->cages(), 'rackId', $rackId);
    }

    public function getCage(string $id): ?array
    {
        return $this->findById($this->cages(), $id);
    }

    public function getAlarms(array $filters): array
    {
        $alarms = $this->alarms();

        if ($facilityId = $this->normalizeFilter($filters['facilityId'] ?? null)) {
            $alarms = array_values(array_filter($alarms, fn (array $alarm): bool => $this->facilityForScope($alarm['scopeType'], $alarm['scopeId']) === $facilityId));
        }

        foreach (['status', 'severity', 'scopeType', 'scopeId', 'sensorType'] as $field) {
            $value = $this->normalizeFilter($filters[$field] ?? null);
            if (null === $value) {
                continue;
            }

            $alarms = array_values(array_filter($alarms, static fn (array $alarm): bool => ($alarm[$field] ?? null) === $value));
        }

        if ($query = $this->normalizeFilter($filters['q'] ?? null)) {
            $needle = mb_strtolower($query);
            $alarms = array_values(array_filter($alarms, static function (array $alarm) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $alarm['scopeLabel'],
                    $alarm['message'],
                    $alarm['sensorType'],
                    $alarm['severity'],
                    $alarm['status'],
                ]));

                return str_contains($haystack, $needle);
            }));
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = max(1, min(500, (int) ($filters['pageSize'] ?? 25)));
        $offset = ($page - 1) * $pageSize;

        return [
            'data' => array_values(\array_slice($alarms, $offset, $pageSize)),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => \count($alarms),
        ];
    }

    public function getAlarm(string $id): ?array
    {
        return $this->findById($this->alarms(), $id);
    }

    public function getSensors(array $filters): array
    {
        $sensors = $this->sensors();

        foreach (['scopeType', 'scopeId', 'metric', 'status'] as $field) {
            $value = $this->normalizeFilter($filters[$field] ?? null);
            if (null === $value) {
                continue;
            }

            $sensors = array_values(array_filter($sensors, static fn (array $sensor): bool => ($sensor[$field] ?? null) === $value));
        }

        if ($facilityId = $this->normalizeFilter($filters['facilityId'] ?? null)) {
            $sensors = array_values(array_filter($sensors, fn (array $sensor): bool => $this->facilityForScope($sensor['scopeType'], $sensor['scopeId']) === $facilityId));
        }

        return $sensors;
    }

    public function getConditionSnapshot(string $scopeType, string $scopeId, ?string $at = null): ?array
    {
        $thresholds = $this->getConditionThresholds($scopeType, $scopeId);
        if ([] === $thresholds) {
            return null;
        }

        $collectedAt = $this->normalizeDateTime($at) ?? gmdate(\DATE_ATOM);
        $timestamp = strtotime($collectedAt) ?: time();
        $metrics = array_map(fn (array $threshold): array => $this->buildSnapshotMetric($threshold, $scopeId, $timestamp), $thresholds);

        return [
            'scopeType' => $scopeType,
            'scopeId' => $scopeId,
            'collectedAt' => $collectedAt,
            'at' => $collectedAt,
            'mode' => 'reality',
            'thresholds' => $thresholds,
            'alarms' => array_values(array_filter($this->alarms(), static fn (array $alarm): bool => $alarm['scopeType'] === $scopeType && $alarm['scopeId'] === $scopeId)),
            'metrics' => $metrics,
        ];
    }

    public function getConditionReadings(string $scopeType, string $scopeId, string $metric, string $from, string $to, ?string $interval = null): ?array
    {
        if (!$this->isKnownScope($scopeType, $scopeId) || !\in_array($metric, self::CONDITION_METRICS, true)) {
            return null;
        }

        $fromTimestamp = strtotime($from);
        $toTimestamp = strtotime($to);
        if (false === $fromTimestamp || false === $toTimestamp || $fromTimestamp > $toTimestamp) {
            return null;
        }

        $points = [];
        for ($timestamp = $fromTimestamp; $timestamp <= $toTimestamp; $timestamp += $this->parseIntervalSeconds($interval)) {
            $points[] = [
                'at' => gmdate(\DATE_ATOM, $timestamp),
                'value' => $this->metricValue($metric, $scopeId, $timestamp),
            ];
        }

        return [
            'scopeType' => $scopeType,
            'scopeId' => $scopeId,
            'metric' => $metric,
            'unit' => self::METRIC_UNITS[$metric],
            'points' => $points,
        ];
    }

    public function getConditionThresholds(string $scopeType, string $scopeId): array
    {
        if (!$this->isKnownScope($scopeType, $scopeId)) {
            return [];
        }

        $key = $this->thresholdKey($scopeType, $scopeId);
        if (isset(self::$thresholdOverrides[$key])) {
            return self::$thresholdOverrides[$key];
        }

        return array_map(static fn (string $metric): array => [
            'metric' => $metric,
            'min' => self::DEFAULT_THRESHOLDS[$metric]['min'],
            'max' => self::DEFAULT_THRESHOLDS[$metric]['max'],
            'unit' => self::METRIC_UNITS[$metric],
        ], self::CONDITION_METRICS);
    }

    public function normalizeConditionThresholds(array $thresholds): array
    {
        $normalized = [];
        foreach ($thresholds as $threshold) {
            if (!\is_array($threshold)) {
                continue;
            }

            $metric = $this->normalizeFilter($threshold['metric'] ?? null);
            if (null === $metric || !\in_array($metric, self::CONDITION_METRICS, true)) {
                continue;
            }

            $defaults = self::DEFAULT_THRESHOLDS[$metric];
            $normalized[] = [
                'metric' => $metric,
                'min' => is_numeric($threshold['min'] ?? null) ? (float) $threshold['min'] : $defaults['min'],
                'max' => is_numeric($threshold['max'] ?? null) ? (float) $threshold['max'] : $defaults['max'],
                'unit' => self::METRIC_UNITS[$metric],
            ];
        }

        return $normalized;
    }

    public function updateConditionThresholds(string $scopeType, string $scopeId, array $thresholds): array
    {
        $normalized = $this->normalizeConditionThresholds($thresholds);
        self::$thresholdOverrides[$this->thresholdKey($scopeType, $scopeId)] = $normalized;

        return $normalized;
    }

    public function getSensor(string $id): ?array
    {
        return $this->findById($this->sensors(), $id);
    }

    private function buildSnapshotMetric(array $threshold, string $scopeId, int $timestamp): array
    {
        $metric = (string) $threshold['metric'];
        $value = $this->metricValue($metric, $scopeId, $timestamp);

        return [
            ...$threshold,
            'value' => $value,
            'status' => $value < $threshold['min'] || $value > $threshold['max'] ? 'out-of-range' : 'ok',
        ];
    }

    private function metricValue(string $metric, string $scopeId, int $timestamp): float
    {
        $defaults = [
            'temperature' => ['base' => 23, 'range' => 2.4],
            'humidity' => ['base' => 52, 'range' => 8],
            'pressure' => ['base' => 1008, 'range' => 10],
            'co2' => ['base' => 620, 'range' => 180],
            'light' => ['base' => 90, 'range' => 50],
            'noise' => ['base' => 45, 'range' => 12],
        ][$metric];

        $seed = $this->seedValue($scopeId . '-' . $metric);
        $wave = sin(($timestamp * 1000) / 3600000 + ($seed % 10));
        $noise = cos(($timestamp * 1000) / 1800000 + ($seed % 7));
        $value = $defaults['base'] + $defaults['range'] * $wave + ($defaults['range'] / 3) * $noise;

        if (\in_array($scopeId, $this->outOfRangeScopeIds(), true)) {
            if ('temperature' === $metric) {
                $value += 3.5;
            }
            if ('humidity' === $metric) {
                $value -= 8;
            }
        }

        return round($value, 2);
    }

    private function seedValue(string $seed): int
    {
        $value = 0;
        foreach (str_split($seed) as $index => $character) {
            $value += \ord($character) * ($index + 1);
        }

        return $value;
    }

    private function parseIntervalSeconds(?string $interval): int
    {
        $interval = $this->normalizeFilter($interval);
        if (null === $interval) {
            return 1800;
        }

        if (str_ends_with($interval, 'm')) {
            return max(60, (int) substr($interval, 0, -1) * 60);
        }
        if (str_ends_with($interval, 'h')) {
            return max(60, (int) substr($interval, 0, -1) * 3600);
        }
        if (str_ends_with($interval, 'd')) {
            return max(60, (int) substr($interval, 0, -1) * 86400);
        }

        return max(60, (int) $interval * 60);
    }

    private function normalizeDateTime(?string $value): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $timestamp = strtotime($value);

        return false === $timestamp ? null : gmdate(\DATE_ATOM, $timestamp);
    }

    private function isKnownScope(string $scopeType, string $scopeId): bool
    {
        return match ($scopeType) {
            'room' => null !== $this->getRoom($scopeId),
            'rack' => null !== $this->getRack($scopeId),
            'cage' => null !== $this->getCage($scopeId),
            default => false,
        };
    }

    private function thresholdKey(string $scopeType, string $scopeId): string
    {
        return $scopeType . ':' . $scopeId;
    }

    private function computeFacilityStats(string $facilityId): array
    {
        $activeAlarms = \count(array_filter($this->alarms(), fn (array $alarm): bool => 'active' === $alarm['status'] && $this->facilityForScope($alarm['scopeType'], $alarm['scopeId']) === $facilityId));
        $sensorsOffline = \count(array_filter($this->sensors(), fn (array $sensor): bool => 'offline' === $sensor['status'] && $this->facilityForScope($sensor['scopeType'], $sensor['scopeId']) === $facilityId));
        $tasksDue = \count(array_filter($this->workOrders(), static fn (array $workOrder): bool => $workOrder['facilityId'] === $facilityId && 'done' !== $workOrder['status']));
        $cagesOutOfRange = \count(array_filter($this->cages(), fn (array $cage): bool => \in_array($cage['id'], $this->outOfRangeCageIds(), true) && $this->facilityForScope('cage', $cage['id']) === $facilityId));

        return [
            'activeAlarms' => $activeAlarms,
            'cagesOutOfRange' => $cagesOutOfRange,
            'tasksDue' => $tasksDue,
            'sensorsOffline' => $sensorsOffline,
        ];
    }

    private function facilityForScope(string $scopeType, string $scopeId): ?string
    {
        if ('room' === $scopeType) {
            return $this->getRoom($scopeId)['facilityId'] ?? null;
        }

        if ('rack' === $scopeType) {
            $rack = $this->getRack($scopeId);

            return null !== $rack ? ($this->getRoom($rack['roomId'])['facilityId'] ?? null) : null;
        }

        if ('cage' === $scopeType) {
            $cage = $this->getCage($scopeId);
            if (null === $cage) {
                return null;
            }

            $rack = $this->getRack($cage['rackId']);

            return null !== $rack ? ($this->getRoom($rack['roomId'])['facilityId'] ?? null) : null;
        }

        return null;
    }

    private function normalizeFilter(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function filterBy(array $items, string $field, ?string $value): array
    {
        if (null === $value || '' === $value) {
            return $items;
        }

        return array_values(array_filter($items, static fn (array $item): bool => ($item[$field] ?? null) === $value));
    }

    private function findById(array $items, string $id): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    private function facilities(): array
    {
        return [
            ['id' => 'FAC-NE-01', 'name' => 'North Campus Vivarium', 'location' => 'Geneva, CH', 'timezone' => 'Europe/Zurich', 'status' => 'active', 'roomCount' => 3],
            ['id' => 'FAC-WC-02', 'name' => 'West Clinical Barrier', 'location' => 'Lausanne, CH', 'timezone' => 'Europe/Zurich', 'status' => 'maintenance', 'roomCount' => 3],
        ];
    }

    private function rooms(): array
    {
        return [
            ['id' => 'RM-101', 'facilityId' => 'FAC-NE-01', 'name' => 'Room 101', 'roomType' => 'barrier', 'rackCount' => 3, 'status' => 'operational'],
            ['id' => 'RM-102', 'facilityId' => 'FAC-NE-01', 'name' => 'Room 102', 'roomType' => 'standard', 'rackCount' => 2, 'status' => 'operational'],
            ['id' => 'RM-201', 'facilityId' => 'FAC-NE-01', 'name' => 'Room 201', 'roomType' => 'quarantine', 'rackCount' => 1, 'status' => 'maintenance'],
            ['id' => 'RM-301', 'facilityId' => 'FAC-WC-02', 'name' => 'Room 301', 'roomType' => 'barrier', 'rackCount' => 2, 'status' => 'operational'],
            ['id' => 'RM-302', 'facilityId' => 'FAC-WC-02', 'name' => 'Room 302', 'roomType' => 'standard', 'rackCount' => 2, 'status' => 'operational'],
            ['id' => 'RM-401', 'facilityId' => 'FAC-WC-02', 'name' => 'Room 401', 'roomType' => 'standard', 'rackCount' => 1, 'status' => 'operational'],
        ];
    }

    private function racks(): array
    {
        return [
            ['id' => 'RACK-101-A', 'roomId' => 'RM-101', 'label' => 'Rack 101-A', 'row' => 'A', 'position' => '01', 'cageCount' => 4, 'status' => 'operational'],
            ['id' => 'RACK-101-B', 'roomId' => 'RM-101', 'label' => 'Rack 101-B', 'row' => 'B', 'position' => '02', 'cageCount' => 4, 'status' => 'operational'],
            ['id' => 'RACK-101-C', 'roomId' => 'RM-101', 'label' => 'Rack 101-C', 'row' => 'C', 'position' => '03', 'cageCount' => 2, 'status' => 'maintenance'],
            ['id' => 'RACK-102-A', 'roomId' => 'RM-102', 'label' => 'Rack 102-A', 'row' => 'A', 'position' => '01', 'cageCount' => 3, 'status' => 'operational'],
            ['id' => 'RACK-102-B', 'roomId' => 'RM-102', 'label' => 'Rack 102-B', 'row' => 'B', 'position' => '02', 'cageCount' => 2, 'status' => 'operational'],
            ['id' => 'RACK-201-A', 'roomId' => 'RM-201', 'label' => 'Rack 201-A', 'row' => 'A', 'position' => '01', 'cageCount' => 1, 'status' => 'maintenance'],
            ['id' => 'RACK-301-A', 'roomId' => 'RM-301', 'label' => 'Rack 301-A', 'row' => 'A', 'position' => '01', 'cageCount' => 2, 'status' => 'operational'],
            ['id' => 'RACK-301-B', 'roomId' => 'RM-301', 'label' => 'Rack 301-B', 'row' => 'B', 'position' => '02', 'cageCount' => 2, 'status' => 'operational'],
            ['id' => 'RACK-302-A', 'roomId' => 'RM-302', 'label' => 'Rack 302-A', 'row' => 'A', 'position' => '01', 'cageCount' => 3, 'status' => 'operational'],
            ['id' => 'RACK-302-B', 'roomId' => 'RM-302', 'label' => 'Rack 302-B', 'row' => 'B', 'position' => '02', 'cageCount' => 1, 'status' => 'operational'],
            ['id' => 'RACK-401-A', 'roomId' => 'RM-401', 'label' => 'Rack 401-A', 'row' => 'A', 'position' => '01', 'cageCount' => 2, 'status' => 'operational'],
        ];
    }

    private function cages(): array
    {
        return [
            ['id' => 'CAGE-101-A1', 'rackId' => 'RACK-101-A', 'label' => 'A1', 'status' => 'occupied', 'occupancy' => 4, 'species' => 'Mouse', 'lastCleanedAt' => '2026-03-05T08:00:00Z'],
            ['id' => 'CAGE-101-A2', 'rackId' => 'RACK-101-A', 'label' => 'A2', 'status' => 'occupied', 'occupancy' => 5, 'species' => 'Mouse', 'lastCleanedAt' => '2026-03-06T08:00:00Z'],
            ['id' => 'CAGE-101-B1', 'rackId' => 'RACK-101-B', 'label' => 'B1', 'status' => 'quarantine', 'occupancy' => 2, 'species' => 'Mouse', 'lastCleanedAt' => '2026-03-02T08:00:00Z'],
            ['id' => 'CAGE-101-C1', 'rackId' => 'RACK-101-C', 'label' => 'C1', 'status' => 'cleaning', 'occupancy' => 0, 'species' => 'Mouse', 'lastCleanedAt' => '2026-03-10T08:00:00Z'],
            ['id' => 'CAGE-102-A1', 'rackId' => 'RACK-102-A', 'label' => 'A1', 'status' => 'occupied', 'occupancy' => 3, 'species' => 'Rat', 'lastCleanedAt' => '2026-03-08T08:00:00Z'],
            ['id' => 'CAGE-102-B1', 'rackId' => 'RACK-102-B', 'label' => 'B1', 'status' => 'empty', 'occupancy' => 0, 'species' => 'Rat', 'lastCleanedAt' => '2026-03-12T08:00:00Z'],
            ['id' => 'CAGE-201-A1', 'rackId' => 'RACK-201-A', 'label' => 'A1', 'status' => 'occupied', 'occupancy' => 2, 'species' => 'Mouse', 'lastCleanedAt' => '2026-03-04T08:00:00Z'],
            ['id' => 'CAGE-301-A1', 'rackId' => 'RACK-301-A', 'label' => 'A1', 'status' => 'occupied', 'occupancy' => 6, 'species' => 'Mouse', 'lastCleanedAt' => '2026-03-07T08:00:00Z'],
            ['id' => 'CAGE-302-A1', 'rackId' => 'RACK-302-A', 'label' => 'A1', 'status' => 'occupied', 'occupancy' => 4, 'species' => 'Mouse', 'lastCleanedAt' => '2026-03-09T08:00:00Z'],
            ['id' => 'CAGE-401-A1', 'rackId' => 'RACK-401-A', 'label' => 'A1', 'status' => 'occupied', 'occupancy' => 1, 'species' => 'Mouse', 'lastCleanedAt' => '2026-03-11T08:00:00Z'],
        ];
    }

    private function alarms(): array
    {
        return [
            ['id' => 'ALARM-001', 'severity' => 'critical', 'status' => 'active', 'scopeType' => 'room', 'scopeId' => 'RM-201', 'scopeLabel' => 'Room 201', 'sensorType' => 'temperature', 'message' => 'Temperature exceeded quarantine threshold.', 'createdAt' => '2026-03-19T07:14:00Z', 'updatedAt' => '2026-03-19T07:16:00Z', 'correlationId' => 'corr-room-201-temp'],
            ['id' => 'ALARM-002', 'severity' => 'high', 'status' => 'active', 'scopeType' => 'cage', 'scopeId' => 'CAGE-101-B1', 'scopeLabel' => 'Cage B1', 'sensorType' => 'humidity', 'message' => 'Humidity drift detected in quarantine cage.', 'createdAt' => '2026-03-19T08:05:00Z', 'updatedAt' => '2026-03-19T08:06:00Z', 'correlationId' => 'corr-cage-101b1-humidity'],
            ['id' => 'ALARM-003', 'severity' => 'medium', 'status' => 'acknowledged', 'scopeType' => 'rack', 'scopeId' => 'RACK-301-B', 'scopeLabel' => 'Rack 301-B', 'sensorType' => 'co2', 'message' => 'CO2 levels fluctuating above expected variance.', 'createdAt' => '2026-03-18T16:40:00Z', 'updatedAt' => '2026-03-18T17:00:00Z', 'acknowledgedAt' => '2026-03-18T17:00:00Z', 'assignedTo' => 'Sofia Marin', 'correlationId' => 'corr-rack-301b-co2'],
        ];
    }

    private function sensors(): array
    {
        return [
            ['id' => 'SENSOR-001', 'scopeType' => 'room', 'scopeId' => 'RM-101', 'scopeLabel' => 'Room 101', 'metric' => 'temperature', 'status' => 'online', 'lastSeen' => '2026-03-19T09:55:00Z', 'battery' => 92, 'calibrationDueAt' => '2026-06-01T00:00:00Z'],
            ['id' => 'SENSOR-002', 'scopeType' => 'cage', 'scopeId' => 'CAGE-101-B1', 'scopeLabel' => 'Cage B1', 'metric' => 'humidity', 'status' => 'offline', 'lastSeen' => '2026-03-19T07:50:00Z', 'battery' => 14, 'calibrationDueAt' => '2026-05-15T00:00:00Z'],
            ['id' => 'SENSOR-003', 'scopeType' => 'rack', 'scopeId' => 'RACK-301-B', 'scopeLabel' => 'Rack 301-B', 'metric' => 'co2', 'status' => 'online', 'lastSeen' => '2026-03-19T09:58:00Z', 'battery' => 76, 'calibrationDueAt' => '2026-07-10T00:00:00Z'],
            ['id' => 'SENSOR-004', 'scopeType' => 'room', 'scopeId' => 'RM-201', 'scopeLabel' => 'Room 201', 'metric' => 'temperature', 'status' => 'offline', 'lastSeen' => '2026-03-19T07:10:00Z', 'battery' => 8, 'calibrationDueAt' => '2026-04-20T00:00:00Z'],
        ];
    }

    private function workOrders(): array
    {
        return [
            ['id' => 'WO-001', 'facilityId' => 'FAC-NE-01', 'status' => 'open'],
            ['id' => 'WO-002', 'facilityId' => 'FAC-NE-01', 'status' => 'in-progress'],
            ['id' => 'WO-003', 'facilityId' => 'FAC-WC-02', 'status' => 'done'],
        ];
    }

    private function outOfRangeCageIds(): array
    {
        return ['CAGE-101-B1', 'CAGE-201-A1'];
    }

    private function outOfRangeScopeIds(): array
    {
        return ['RM-201', 'RACK-301-B', ...$this->outOfRangeCageIds()];
    }
}
