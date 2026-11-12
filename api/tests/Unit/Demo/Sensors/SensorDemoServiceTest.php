<?php

declare(strict_types=1);

namespace App\Tests\Unit\Demo\Sensors;

use App\Demo\Sensors\SensorDemoService;
use App\Service\SensorAgentClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SensorDemoServiceTest extends TestCase
{
    #[Test]
    public function healthAndThresholdsAreNormalizedForTheUi(): void
    {
        $sensorAgentClient = new SensorAgentClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'ok' => true,
                    'status' => 'stale',
                    'latestObservationAvailable' => true,
                    'lastObservationAt' => '2026-04-01T12:00:00Z',
                    'freshnessAgeSeconds' => 45,
                    'isFresh' => false,
                ], \JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    'ok' => true,
                    'status' => 'stale',
                    'latestObservationAvailable' => true,
                    'lastObservationAt' => '2026-04-01T12:00:00Z',
                    'freshnessAgeSeconds' => 45,
                    'isFresh' => false,
                ], \JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    'ok' => true,
                    'action' => 'get_latest',
                    'schemaVersion' => 'sensor-observation-v1',
                    'sensorId' => 'arduino-ttyACM0',
                    'sourcePort' => '/dev/ttyACM0',
                    'observedAt' => '2026-04-01T12:00:00Z',
                    'temperatureC' => 23.4,
                    'humidityPct' => 51.2,
                ], \JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    'ok' => true,
                    'status' => 'stale',
                    'latestObservationAvailable' => true,
                    'lastObservationAt' => '2026-04-01T12:00:00Z',
                    'freshnessAgeSeconds' => 45,
                    'isFresh' => false,
                ], \JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    'ok' => true,
                    'action' => 'get_threshold_status',
                    'observedAt' => '2026-04-01T12:00:00Z',
                    'sensorId' => 'arduino-ttyACM0',
                    'sourcePort' => '/dev/ttyACM0',
                    'schemaVersion' => 'sensor-observation-v1',
                    'hasActiveAlarms' => true,
                    'overallStatus' => 'warning',
                    'activeAlarms' => [],
                    'thresholdStatus' => [
                        'temperature' => ['available' => true, 'value' => 23.4, 'unit' => 'C', 'status' => 'normal', 'alarm' => false],
                        'humidity' => ['available' => true, 'value' => 51.2, 'unit' => '%', 'status' => 'warning', 'alarm' => true],
                        'pressure' => ['available' => false, 'unit' => 'hPa', 'status' => 'unavailable', 'alarm' => false],
                    ],
                ], \JSON_THROW_ON_ERROR)),
            ]),
            new NullLogger(),
            'https://sensor-agent.example.invalid',
            '',
            5.0,
            true,
        );

        $service = new SensorDemoService(
            $sensorAgentClient,
            new MockClock('2026-04-01T12:00:45Z'),
            new NullLogger(),
            new ArrayAdapter(),
        );

        $health = $service->getHealth();
        $latest = $service->getLatestObservation();
        $threshold = $service->getThresholdStatus();

        $this->assertSame('stale', $health['status']);
        $this->assertSame(45, $health['freshnessAgeSeconds']);
        $this->assertSame('Latest sensor data is stale (45 seconds old).', $health['message']);
        $this->assertSame(23.4, $latest['temperatureC']);
        $this->assertNull($latest['pressureHpa']);
        $this->assertSame('warning', $threshold['overallStatus']);
        $this->assertTrue($threshold['thresholdStatus']['humidity']['alarm']);
        $this->assertFalse($threshold['thresholdStatus']['pressure']['available']);
    }

    #[Test]
    public function latestObservationFailsClosedWhenUpstreamIsUnavailable(): void
    {
        $sensorAgentClient = new SensorAgentClient(
            new MockHttpClient([
                new MockResponse('{"message":"timeout"}', ['http_code' => 504]),
            ]),
            new NullLogger(),
            'https://sensor-agent.example.invalid',
            '',
            5.0,
            true,
        );

        $service = new SensorDemoService(
            $sensorAgentClient,
            new MockClock('2026-04-01T12:00:10Z'),
            new NullLogger(),
            new ArrayAdapter(),
        );

        $payload = $service->getLatestObservation();

        $this->assertFalse($payload['ok']);
        $this->assertFalse($payload['upstreamReachable']);
        $this->assertSame('Live sensor data is currently unavailable.', $payload['message']);
    }

    #[Test]
    public function getMetricRejectsUnsupportedMetrics(): void
    {
        $sensorAgentClient = new SensorAgentClient(
            new MockHttpClient(),
            new NullLogger(),
            'https://sensor-agent.example.invalid',
            '',
            5.0,
            true,
        );

        $service = new SensorDemoService(
            $sensorAgentClient,
            new MockClock('2026-04-01T12:00:10Z'),
            new NullLogger(),
            new ArrayAdapter(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported sensor metric "co2".');

        $service->getMetric('co2');
    }

    #[Test]
    public function historySeedsAndAppendsLiveObservations(): void
    {
        $sensorAgentClient = new SensorAgentClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'ok' => true,
                    'status' => 'ready',
                    'latestObservationAvailable' => true,
                    'lastObservationAt' => '2026-04-01T12:00:00Z',
                    'freshnessAgeSeconds' => 4,
                    'isFresh' => true,
                ], \JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    'ok' => true,
                    'sensorId' => 'arduino-ttyACM0',
                    'sourcePort' => '/dev/ttyACM0',
                    'schemaVersion' => 'sensor-observation-v1',
                    'observedAt' => '2026-04-01T12:00:00Z',
                    'temperatureC' => 23.4,
                    'humidityPct' => 51.2,
                    'pressureHpa' => 1008.7,
                ], \JSON_THROW_ON_ERROR)),
            ]),
            new NullLogger(),
            'https://sensor-agent.example.invalid',
            '',
            5.0,
            true,
        );

        $service = new SensorDemoService(
            $sensorAgentClient,
            new MockClock('2026-04-01T12:00:05Z'),
            new NullLogger(),
            new ArrayAdapter(),
        );

        $history = $service->getHistory(180, 120);

        $this->assertTrue($history['ok']);
        $this->assertGreaterThan(1, count($history['points']));
        $this->assertSame('2026-04-01T12:00:00Z', $history['points'][array_key_last($history['points'])]['observedAt']);
        $this->assertSame(23.4, $history['points'][array_key_last($history['points'])]['temperatureC']);
    }
}
