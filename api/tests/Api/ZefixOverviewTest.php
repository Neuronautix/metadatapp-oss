<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\EnvironmentalObservationFactory;
use App\DataFixtures\Factory\MortalityRecordFactory;
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
use App\Enum\MortalityReason;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Enum\ZebrafishLineStatus;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ZefixOverviewTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_exposes_real_dashboard_aggregates(): void
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
            'name' => 'Room 301',
            'plateau' => $plateau,
            'account' => $account,
        ]);
        $system = SystemFactory::createOne([
            'code' => 'SYS-169',
            'manufacturer' => 'Tecniplast',
            'model' => 'ZebTEC Active Blue',
            'room' => $room,
            'account' => $account,
        ]);
        $rack = RackFactory::createOne([
            'code' => 'RACK-169',
            'room' => $room,
            'account' => $account,
            'system' => $system,
        ]);
        $poolA1 = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A1',
            'capacity' => 80,
            'account' => $account,
        ]);
        $poolA2 = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A2',
            'capacity' => 80,
            'account' => $account,
        ]);
        $poolB1 = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'B1',
            'capacity' => 60,
            'account' => $account,
        ]);

        $activeLine = ZebrafishLineFactory::createOne([
            'name' => 'Tg(active:line)',
            'status' => ZebrafishLineStatus::ACTIVE,
            'account' => $account,
        ]);
        $pausedLine = ZebrafishLineFactory::createOne([
            'name' => 'Tg(paused:line)',
            'status' => ZebrafishLineStatus::PAUSED,
            'account' => $account,
        ]);

        $activeBatch = ZebrafishBatchFactory::createOne([
            'line' => $activeLine,
            'pool' => $poolA1,
            'account' => $account,
            'birthDate' => new \DateTimeImmutable('first day of this month'),
            'maleCount' => 10,
            'femaleCount' => 10,
            'unknownSexCount' => 0,
            'totalPopulation' => 20,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
        ]);
        $quarantinedBatch = ZebrafishBatchFactory::createOne([
            'line' => $pausedLine,
            'pool' => $poolA2,
            'account' => $account,
            'birthDate' => new \DateTimeImmutable('first day of this month -2 months'),
            'maleCount' => 8,
            'femaleCount' => 7,
            'unknownSexCount' => 0,
            'totalPopulation' => 15,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::QUARANTINED,
        ]);
        ZebrafishBatchFactory::createOne([
            'line' => $pausedLine,
            'pool' => $poolB1,
            'account' => $account,
            'birthDate' => new \DateTimeImmutable('first day of this month -4 months'),
            'maleCount' => 5,
            'femaleCount' => 5,
            'unknownSexCount' => 0,
            'totalPopulation' => 10,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::COMPLETED,
        ]);

        MortalityRecordFactory::createOne([
            'batch' => $activeBatch,
            'date' => new \DateTimeImmutable('today 09:00:00'),
            'count' => 3,
            'reason' => MortalityReason::DISEASE,
            'account' => $account,
            'user' => $user,
        ]);
        MortalityRecordFactory::createOne([
            'batch' => $quarantinedBatch,
            'date' => new \DateTimeImmutable('-2 days 09:00:00'),
            'count' => 2,
            'reason' => MortalityReason::UNKNOWN,
            'account' => $account,
            'user' => $user,
        ]);

        EnvironmentalObservationFactory::createOne([
            'system' => $system,
            'metric' => EnvironmentalMetric::TEMPERATURE,
            'value' => 28.1,
            'timestamp' => new \DateTimeImmutable('2026-03-31T10:00:00+00:00'),
            'account' => $account,
            'user' => $user,
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
                'scopeID' => 'SYS-169',
                'metric' => EnvironmentalMetric::TEMPERATURE->value,
                'value' => 30.2,
                'timestamp' => '2026-04-01T09:00:00+00:00',
                'source' => EnvironmentalObservationSource::MANUAL->value,
                'notes' => 'Temperature drift for overview alert count.',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $overviewResponse = $authenticatedRequest('GET', '/zefix/overview', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();

        $payload = $overviewResponse->toArray();
        self::assertSame(1, $payload['totalLines']);
        self::assertSame(2, $payload['totalBatches']);
        self::assertSame(35, $payload['totalFish']);
        self::assertSame(3, $payload['todaysMortalities']);
        self::assertSame(1, $payload['activeAlerts']);
        self::assertSame([
            ['line' => 'Tg(active:line)', 'fish' => 20],
            ['line' => 'Tg(paused:line)', 'fish' => 15],
        ], $payload['populationByLine']);
        self::assertCount(14, $payload['mortalityTrend']);
        self::assertSame(5, array_sum(array_column($payload['mortalityTrend'], 'mortalities')));
        self::assertCount(2, $payload['temperatureTrend']);
        self::assertSame(30.2, $payload['temperatureTrend'][1]['temperature']);
        self::assertCount(6, $payload['breedingOutput']);
        self::assertSame(3, array_sum(array_column($payload['breedingOutput'], 'births')));
    }
}
