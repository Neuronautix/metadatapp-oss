<?php

declare(strict_types=1);

namespace App\Demo\Sensors\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Demo\Sensors\SensorAlarmStatusView;
use App\Demo\Sensors\SensorDemoService;
use App\Demo\Sensors\SensorHealthView;
use App\Demo\Sensors\SensorHistoryView;
use App\Demo\Sensors\SensorMetricView;
use App\Demo\Sensors\SensorObservationView;
use App\Demo\Sensors\SensorThresholdStatusView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<object>
 */
final readonly class SensorDemoProvider implements ProviderInterface
{
    public function __construct(
        private SensorDemoService $sensorDemoService,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $mcpData = \is_array($context['mcp_data'] ?? null) ? $context['mcp_data'] : [];

        return match ($operation->getClass()) {
            SensorHealthView::class => $this->createHealthView(),
            SensorObservationView::class => $this->createObservationView(),
            SensorThresholdStatusView::class => $this->createThresholdStatusView(),
            SensorAlarmStatusView::class => $this->createAlarmStatusView(),
            SensorMetricView::class => $this->createMetricView((string) ($uriVariables['metric'] ?? ($mcpData['metric'] ?? ($this->requestStack->getCurrentRequest()?->query->get('metric', '') ?? '')))),
            SensorHistoryView::class => $this->createHistoryView($mcpData),
            default => throw new \RuntimeException('Unsupported demo sensor resource.'),
        };
    }

    /**
     * @param array<string, mixed> $args
     */
    private function createHistoryView(array $args = []): SensorHistoryView
    {
        $request = $this->requestStack->getCurrentRequest();
        $rangeMinutes = $args['rangeMinutes'] ?? ($request?->query->get('rangeMinutes', '180') ?? 180);
        $maxPoints = $args['maxPoints'] ?? ($request?->query->get('maxPoints', '180') ?? 180);

        $rangeMinutes = max(15, min(720, (int) $rangeMinutes));
        $maxPoints = max(24, min(720, (int) $maxPoints));
        $payload = $this->sensorDemoService->getHistory($rangeMinutes, $maxPoints);

        return new SensorHistoryView(
            ok: $payload['ok'],
            enabled: $payload['enabled'],
            upstreamReachable: $payload['upstreamReachable'],
            id: 'demo-sensor-history',
            sensorId: $payload['sensorId'],
            sourcePort: $payload['sourcePort'],
            schemaVersion: $payload['schemaVersion'],
            observedAt: $payload['observedAt'],
            rangeMinutes: $payload['rangeMinutes'],
            maxPoints: $payload['maxPoints'],
            points: $payload['points'],
            message: $payload['message'],
        );
    }

    private function createHealthView(): SensorHealthView
    {
        $payload = $this->sensorDemoService->getHealth();

        return new SensorHealthView(
            ok: $payload['ok'],
            enabled: $payload['enabled'],
            upstreamReachable: $payload['upstreamReachable'],
            status: $payload['status'],
            latestObservationAvailable: $payload['latestObservationAvailable'],
            lastObservationAt: $payload['lastObservationAt'],
            freshnessAgeSeconds: $payload['freshnessAgeSeconds'],
            isFresh: $payload['isFresh'],
            message: $payload['message'],
        );
    }

    private function createObservationView(): SensorObservationView
    {
        $payload = $this->sensorDemoService->getLatestObservation();

        return new SensorObservationView(
            ok: $payload['ok'],
            enabled: $payload['enabled'],
            upstreamReachable: $payload['upstreamReachable'],
            sensorId: $payload['sensorId'],
            sourcePort: $payload['sourcePort'],
            schemaVersion: $payload['schemaVersion'],
            observedAt: $payload['observedAt'],
            temperatureC: $payload['temperatureC'],
            humidityPct: $payload['humidityPct'],
            pressureHpa: $payload['pressureHpa'],
            message: $payload['message'],
        );
    }

    private function createMetricView(string $metric): SensorMetricView
    {
        try {
            $payload = $this->sensorDemoService->getMetric($metric);
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        return new SensorMetricView(
            ok: $payload['ok'],
            enabled: $payload['enabled'],
            upstreamReachable: $payload['upstreamReachable'],
            metric: $payload['metric'],
            value: $payload['value'],
            unit: $payload['unit'],
            timestamp: $payload['timestamp'],
            freshness: $payload['freshness'],
            alarmLevel: $payload['alarmLevel'],
            activeAlarms: $payload['activeAlarms'],
            message: $payload['message'],
        );
    }

    private function createThresholdStatusView(): SensorThresholdStatusView
    {
        $payload = $this->sensorDemoService->getThresholdStatus();

        return new SensorThresholdStatusView(
            ok: $payload['ok'],
            enabled: $payload['enabled'],
            upstreamReachable: $payload['upstreamReachable'],
            observedAt: $payload['observedAt'],
            sensorId: $payload['sensorId'],
            sourcePort: $payload['sourcePort'],
            schemaVersion: $payload['schemaVersion'],
            hasActiveAlarms: $payload['hasActiveAlarms'],
            overallStatus: $payload['overallStatus'],
            thresholdStatus: $payload['thresholdStatus'],
            activeAlarms: $payload['activeAlarms'],
            message: $payload['message'],
        );
    }

    private function createAlarmStatusView(): SensorAlarmStatusView
    {
        $payload = $this->sensorDemoService->getAlarmStatus();

        return new SensorAlarmStatusView(
            ok: $payload['ok'],
            enabled: $payload['enabled'],
            upstreamReachable: $payload['upstreamReachable'],
            freshness: $payload['freshness'],
            alarmLevel: $payload['alarmLevel'],
            activeAlarms: $payload['activeAlarms'],
            message: $payload['message'],
        );
    }
}
