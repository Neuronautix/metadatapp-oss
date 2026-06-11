<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\PlateauFactory;
use App\DataFixtures\Factory\PoolFactory;
use App\DataFixtures\Factory\RackFactory;
use App\DataFixtures\Factory\RoomFactory;
use App\DataFixtures\Factory\SystemFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\ZebrafishBatchFactory;
use App\DataFixtures\Factory\ZebrafishLineFactory;
use App\Enum\EnvironmentalMetric;
use App\Enum\EnvironmentalObservationSource;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ZefixAlertTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_creates_and_updates_persisted_system_alerts_from_environmental_observations(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
            'firstName' => 'Alice',
            'lastName' => 'Operator',
            'roles' => [Roles::ROLE_ADMIN],
        ]);

        $plateau = PlateauFactory::createOne([
            'name' => 'North Platform',
            'code' => 'ZF-NORTH',
            'account' => $account,
        ]);
        $room = RoomFactory::createOne([
            'name' => 'Room 101',
            'plateau' => $plateau,
            'account' => $account,
        ]);
        $system = SystemFactory::createOne([
            'code' => 'SYS-171',
            'manufacturer' => 'Tecniplast',
            'model' => 'ZebTEC Active Blue',
            'room' => $room,
            'account' => $account,
        ]);

        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $authenticatedRequest('POST', '/zefix/environmental-observations', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json' => [
                'scopeType' => 'system',
                'scopeID' => 'SYS-171',
                'metric' => EnvironmentalMetric::TEMPERATURE->value,
                'value' => 30.4,
                'timestamp' => '2024-03-01T09:00:00+00:00',
                'source' => EnvironmentalObservationSource::MANUAL->value,
                'notes' => 'Temperature drift',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $activeAlertsResponse = $authenticatedRequest('GET', '/zefix/alerts?status=active', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $activeAlertsResponse->toArray());
        self::assertSame('temperature outside range', $activeAlertsResponse->toArray()[0]['type']);
        self::assertSame('CRITICAL', $activeAlertsResponse->toArray()[0]['severity']);
        self::assertSame('active', $activeAlertsResponse->toArray()[0]['status']);
        self::assertSame('system', $activeAlertsResponse->toArray()[0]['affectedEntityType']);
        self::assertSame('SYS-171', $activeAlertsResponse->toArray()[0]['affectedEntityId']);
        $alertID = $activeAlertsResponse->toArray()[0]['alertID'];

        $systemResponse = $authenticatedRequest('GET', '/zefix/systems/SYS-171', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $systemResponse->toArray()['activeAlerts']);

        $acknowledgeResponse = $authenticatedRequest('POST', sprintf('/zefix/alerts/%s/acknowledge', $alertID), [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json' => new \stdClass(),
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('acknowledged', $acknowledgeResponse->toArray()['status']);
        self::assertNotNull($acknowledgeResponse->toArray()['acknowledgedAt']);

        $activeAlertsAfterAcknowledge = $authenticatedRequest('GET', '/zefix/alerts?status=active', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame([], $activeAlertsAfterAcknowledge->toArray());

        $systemAfterAcknowledge = $authenticatedRequest('GET', '/zefix/systems/SYS-171', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $systemAfterAcknowledge->toArray()['activeAlerts']);

        $resolveResponse = $authenticatedRequest('POST', sprintf('/zefix/alerts/%s/resolve', $alertID), [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json' => new \stdClass(),
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('resolved', $resolveResponse->toArray()['status']);
        self::assertNotNull($resolveResponse->toArray()['resolvedAt']);
    }

    #[Test]
    public function it_creates_batch_alerts_from_mortality_spikes_and_counts_them_on_the_linked_system(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
            'roles' => [Roles::ROLE_ADMIN],
        ]);

        $plateau = PlateauFactory::createOne([
            'name' => 'North Platform',
            'code' => 'ZF-NORTH',
            'account' => $account,
        ]);
        $room = RoomFactory::createOne([
            'name' => 'Room 201',
            'plateau' => $plateau,
            'account' => $account,
        ]);
        $system = SystemFactory::createOne([
            'code' => 'SYS-171B',
            'manufacturer' => 'Tecniplast',
            'model' => 'ZebTEC Active Blue',
            'room' => $room,
            'account' => $account,
        ]);
        $rack = RackFactory::createOne([
            'code' => 'RACK-171',
            'room' => $room,
            'account' => $account,
            'system' => $system,
        ]);
        $pool = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A1',
            'capacity' => 80,
            'account' => $account,
        ]);
        $line = ZebrafishLineFactory::createOne([
            'name' => 'Tg(alert:line)',
            'account' => $account,
        ]);
        $batch = ZebrafishBatchFactory::createOne([
            'line' => $line,
            'pool' => $pool,
            'account' => $account,
            'maleCount' => 10,
            'femaleCount' => 10,
            'unknownSexCount' => 0,
            'totalPopulation' => 20,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
        ]);

        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $authenticatedRequest('POST', '/mortality_records.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'json' => [
                'batch' => sprintf('/zebrafish_batches/%s', $batch->getId()?->toRfc4122()),
                'date' => '2024-03-02T09:00:00+00:00',
                'count' => 5,
                'reason' => '/mortality_reasons/disease',
                'notes' => 'Five fish found dead after inspection.',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $activeAlertsResponse = $authenticatedRequest('GET', '/zefix/alerts?status=active', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $activeAlertsResponse->toArray());
        self::assertSame('high mortality in batch', $activeAlertsResponse->toArray()[0]['type']);
        self::assertSame('CRITICAL', $activeAlertsResponse->toArray()[0]['severity']);
        self::assertSame('batch', $activeAlertsResponse->toArray()[0]['affectedEntityType']);
        self::assertSame($batch->getId()?->toRfc4122(), $activeAlertsResponse->toArray()[0]['affectedEntityId']);

        $systemResponse = $authenticatedRequest('GET', '/zefix/systems/SYS-171B', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $systemResponse->toArray()['activeAlerts']);
    }
}
