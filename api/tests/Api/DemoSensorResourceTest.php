<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Demo\Sensors\SensorDemoService;
use App\DataFixtures\Factory\UserFactory;
use App\Security\Roles;
use App\Service\SensorAgentClient;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DemoSensorResourceTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function latestEndpointReturnsNormalizedObservationPayload(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        static::getContainer()->set(SensorDemoService::class, new SensorDemoService(
            new SensorAgentClient(
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
                        'action' => 'get_latest',
                        '@type' => 'SensorObservation',
                        'schemaVersion' => 'sensor-observation-v1',
                        'sensorId' => 'arduino-ttyACM0',
                        'sourcePort' => '/dev/ttyACM0',
                        'observedAt' => '2026-04-01T12:00:00Z',
                        'temperatureC' => 23.4,
                        'humidityPct' => 51.2,
                    ], \JSON_THROW_ON_ERROR)),
                ]),
                new NullLogger(),
                'https://sensor-agent.example.invalid',
                '',
                5.0,
                true,
            ),
            new MockClock('2026-04-01T12:00:05Z'),
            new NullLogger(),
            new ArrayAdapter(),
        ));

        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client->loginUser($user);

        $response = $client->request('GET', '/demo/sensors/latest');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'ok' => true,
            'enabled' => true,
            'upstreamReachable' => true,
            'sensorId' => 'arduino-ttyACM0',
            'sourcePort' => '/dev/ttyACM0',
            'schemaVersion' => 'sensor-observation-v1',
            'observedAt' => '2026-04-01T12:00:00Z',
            'temperatureC' => 23.4,
            'humidityPct' => 51.2,
        ]);
        $payload = $response->toArray();
        $this->assertArrayNotHasKey('pressureHpa', $payload);
        $this->assertArrayNotHasKey('message', $payload);
        $this->assertSame('/demo/sensors/latest', $response->toArray()['@id']);
    }

    #[Test]
    public function healthEndpointReturnsWaitingStateUntilFirstObservationArrives(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        static::getContainer()->set(SensorDemoService::class, new SensorDemoService(
            new SensorAgentClient(
                new MockHttpClient([
                    new MockResponse(json_encode([
                        'ok' => true,
                        'status' => 'waiting_for_data',
                        'latestObservationAvailable' => false,
                        'lastObservationAt' => null,
                        'freshnessAgeSeconds' => null,
                        'isFresh' => false,
                    ], \JSON_THROW_ON_ERROR)),
                ]),
                new NullLogger(),
                'https://sensor-agent.example.invalid',
                '',
                5.0,
                true,
            ),
            new MockClock('2026-04-01T12:00:05Z'),
            new NullLogger(),
            new ArrayAdapter(),
        ));

        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client->loginUser($user);

        $client->request('GET', '/demo/sensors/health');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'ok' => true,
            'enabled' => true,
            'upstreamReachable' => true,
            'status' => 'waiting_for_data',
            'latestObservationAvailable' => false,
            'isFresh' => false,
            'message' => 'Waiting for the first observation from the Raspberry Pi sensor.',
        ]);
        $payload = $client->getResponse()->toArray();
        $this->assertArrayNotHasKey('lastObservationAt', $payload);
        $this->assertArrayNotHasKey('freshnessAgeSeconds', $payload);
    }

    #[Test]
    public function thresholdStatusEndpointPreservesOptionalPressureAvailability(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        static::getContainer()->set(SensorDemoService::class, new SensorDemoService(
            new SensorAgentClient(
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
                        'action' => 'get_threshold_status',
                        'observedAt' => '2026-04-01T12:00:00Z',
                        'sensorId' => 'arduino-ttyACM0',
                        'sourcePort' => '/dev/ttyACM0',
                        'schemaVersion' => 'sensor-observation-v1',
                        'hasActiveAlarms' => false,
                        'overallStatus' => 'normal',
                        'activeAlarms' => [],
                        'thresholdStatus' => [
                            'temperature' => [
                                'available' => true,
                                'value' => 23.4,
                                'unit' => 'C',
                                'status' => 'normal',
                                'alarm' => false,
                            ],
                            'humidity' => [
                                'available' => true,
                                'value' => 51.2,
                                'unit' => '%',
                                'status' => 'normal',
                                'alarm' => false,
                            ],
                            'pressure' => [
                                'available' => false,
                                'unit' => 'hPa',
                                'status' => 'unavailable',
                                'alarm' => false,
                            ],
                        ],
                    ], \JSON_THROW_ON_ERROR)),
                ]),
                new NullLogger(),
                'https://sensor-agent.example.invalid',
                '',
                5.0,
                true,
            ),
            new MockClock('2026-04-01T12:00:05Z'),
            new NullLogger(),
            new ArrayAdapter(),
        ));

        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client->loginUser($user);

        $client->request('GET', '/demo/sensors/threshold-status');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'ok' => true,
            'enabled' => true,
            'upstreamReachable' => true,
            'overallStatus' => 'normal',
            'thresholdStatus' => [
                'temperature' => [
                    'available' => true,
                    'value' => 23.4,
                    'unit' => 'C',
                    'status' => 'normal',
                    'alarm' => false,
                ],
                'pressure' => [
                    'available' => false,
                    'value' => null,
                    'unit' => 'hPa',
                    'status' => 'unavailable',
                    'alarm' => false,
                ],
            ],
        ]);
    }

    #[Test]
    public function metricEndpointRejectsUnsupportedMetrics(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = static::createClient();
        $client->loginUser($user);

        $client->request('GET', '/demo/sensors/metric/co2');

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'hydra:title' => 'An error occurred',
        ]);
    }

    #[Test]
    public function historyEndpointReturnsRollingObservationSeries(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        static::getContainer()->set(SensorDemoService::class, new SensorDemoService(
            new SensorAgentClient(
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
                        'schemaVersion' => 'sensor-observation-v1',
                        'sensorId' => 'arduino-ttyACM0',
                        'sourcePort' => '/dev/ttyACM0',
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
            ),
            new MockClock('2026-04-01T12:00:05Z'),
            new NullLogger(),
            new ArrayAdapter(),
        ));

        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client->loginUser($user);

        $response = $client->request('GET', '/demo/sensors/history?rangeMinutes=60&maxPoints=60');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame('demo-sensor-history', $payload['id']);
        $this->assertSame(60, $payload['rangeMinutes']);
        $this->assertSame(60, $payload['maxPoints']);
        $this->assertNotEmpty($payload['points']);
        $this->assertSame('2026-04-01T12:00:00Z', $payload['points'][array_key_last($payload['points'])]['observedAt']);
    }
}
