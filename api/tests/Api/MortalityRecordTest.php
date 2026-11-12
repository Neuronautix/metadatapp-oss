<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\PlateauFactory;
use App\DataFixtures\Factory\PoolFactory;
use App\DataFixtures\Factory\RackFactory;
use App\DataFixtures\Factory\RoomFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\ZebrafishLineFactory;
use App\Enum\MortalityReason;
use App\Enum\PoolStatus;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Enum\ZebrafishLineStatus;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class MortalityRecordTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_persists_mortality_records_and_recomputes_batch_population_and_history(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
            'firstName' => 'Nora',
            'lastName' => 'Recorder',
            'roles' => [Roles::ROLE_ADMIN],
        ]);

        $plateau = PlateauFactory::createOne([
            'name' => 'North Aquatics Platform',
            'code' => 'ZF-NORTH',
            'account' => $account,
        ]);
        $room = RoomFactory::createOne([
            'name' => 'Room 101',
            'plateau' => $plateau,
            'account' => $account,
        ]);
        $rack = RackFactory::createOne([
            'code' => 'RACK-01',
            'room' => $room,
            'account' => $account,
        ]);
        $pool = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $line = ZebrafishLineFactory::createOne([
            'name' => 'Tg(ins:mCherry)',
            'genotype' => 'Tg(ins:mCherry)s960',
            'purpose' => 'Beta-cell reporter line',
            'status' => ZebrafishLineStatus::ACTIVE,
            'createdAt' => new \DateTimeImmutable('2024-02-15T08:30:00+00:00'),
            'account' => $account,
        ]);

        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $batchResponse = $authenticatedRequest('POST', '/zebrafish_batches.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'line' => $this->findIriBy(\App\Entity\ZebrafishLine::class, ['id' => $line->getId()?->toRfc4122()]),
                'pool' => $this->findIriBy(\App\Entity\Pool::class, ['id' => $pool->getId()?->toRfc4122()]),
                'birthDate' => '2024-03-18',
                'maleCount' => 12,
                'femaleCount' => 14,
                'unknownSexCount' => 4,
                'totalPopulation' => 999,
                'lifecycleStatus' => '/zebrafish_batch_lifecycle_statuses/active',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $batchPayload = $batchResponse->toArray();
        $batchIri = $batchPayload['@id'];

        $mortalityResponse = $authenticatedRequest('POST', '/mortality_records.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'batch' => $batchIri,
                'date' => '2024-04-05',
                'count' => 4,
                'reason' => '/mortality_reasons/' . MortalityReason::DISEASE->value,
                'notes' => 'Observed after transfer.',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'reason' => '/mortality_reasons/' . MortalityReason::DISEASE->value,
            'count' => 4,
        ]);

        $detailResponse = $authenticatedRequest('GET', \sprintf('/zefix/batches/%s', basename($batchIri)), [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();

        $detailPayload = $detailResponse->toArray();
        self::assertSame(26, $detailPayload['batch']['individualCount']);
        self::assertSame(4, $detailPayload['batch']['mortalityCount']);
        self::assertCount(2, $detailPayload['timeline']);
        self::assertCount(1, $detailPayload['mortalityEvents']);
        self::assertSame(MortalityReason::DISEASE->value, $detailPayload['mortalityEvents'][0]['reason']);
        self::assertSame('Nora Recorder', $detailPayload['mortalityEvents'][0]['recorder']);

        $recordsResponse = $authenticatedRequest('GET', '/mortality_records.jsonld?' . http_build_query([
            'batch' => $batchIri,
        ]), [
            'headers' => ['Accept' => 'application/ld+json'],
        ]);
        $this->assertResponseIsSuccessful();
        self::assertCount(1, $recordsResponse->toArray()['hydra:member']);
        self::assertSame('/mortality_reasons/' . MortalityReason::DISEASE->value, $recordsResponse->toArray()['hydra:member'][0]['reason']);
    }

    #[Test]
    public function it_aggregates_mortality_trends_by_line_room_and_plateau_scope(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
            'firstName' => 'Nora',
            'lastName' => 'Recorder',
            'roles' => [Roles::ROLE_ADMIN],
        ]);

        $plateau = PlateauFactory::createOne([
            'name' => 'North Aquatics Platform',
            'code' => 'ZF-NORTH',
            'account' => $account,
        ]);
        $roomOne = RoomFactory::createOne([
            'name' => 'Room 101',
            'plateau' => $plateau,
            'account' => $account,
        ]);
        $roomTwo = RoomFactory::createOne([
            'name' => 'Room 102',
            'plateau' => $plateau,
            'account' => $account,
        ]);
        $rackOne = RackFactory::createOne([
            'code' => 'RACK-01',
            'room' => $roomOne,
            'account' => $account,
        ]);
        $rackTwo = RackFactory::createOne([
            'code' => 'RACK-02',
            'room' => $roomTwo,
            'account' => $account,
        ]);
        $poolOne = PoolFactory::createOne([
            'rack' => $rackOne,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $poolTwo = PoolFactory::createOne([
            'rack' => $rackTwo,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $line = ZebrafishLineFactory::createOne([
            'name' => 'Tg(ins:mCherry)',
            'genotype' => 'Tg(ins:mCherry)s960',
            'purpose' => 'Beta-cell reporter line',
            'status' => ZebrafishLineStatus::ACTIVE,
            'createdAt' => new \DateTimeImmutable('2024-02-15T08:30:00+00:00'),
            'account' => $account,
        ]);

        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $batchOneResponse = $authenticatedRequest('POST', '/zebrafish_batches.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'line' => $this->findIriBy(\App\Entity\ZebrafishLine::class, ['id' => $line->getId()?->toRfc4122()]),
                'pool' => $this->findIriBy(\App\Entity\Pool::class, ['id' => $poolOne->getId()?->toRfc4122()]),
                'birthDate' => '2024-03-18',
                'maleCount' => 10,
                'femaleCount' => 10,
                'unknownSexCount' => 0,
                'totalPopulation' => 999,
                'lifecycleStatus' => '/zebrafish_batch_lifecycle_statuses/active',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $batchOneIri = $batchOneResponse->toArray()['@id'];

        $batchTwoResponse = $authenticatedRequest('POST', '/zebrafish_batches.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'line' => $this->findIriBy(\App\Entity\ZebrafishLine::class, ['id' => $line->getId()?->toRfc4122()]),
                'pool' => $this->findIriBy(\App\Entity\Pool::class, ['id' => $poolTwo->getId()?->toRfc4122()]),
                'birthDate' => '2024-03-20',
                'maleCount' => 9,
                'femaleCount' => 11,
                'unknownSexCount' => 0,
                'totalPopulation' => 999,
                'lifecycleStatus' => '/zebrafish_batch_lifecycle_statuses/quarantined',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $batchTwoIri = $batchTwoResponse->toArray()['@id'];

        $mortalityEntries = [
            [$batchOneIri, '2024-04-01', 2, '/mortality_reasons/' . MortalityReason::NATURAL->value],
            [$batchOneIri, '2024-04-01', 3, '/mortality_reasons/' . MortalityReason::SYSTEM_ISSUE->value],
            [$batchTwoIri, '2024-04-02', 4, '/mortality_reasons/' . MortalityReason::DISEASE->value],
        ];

        foreach ($mortalityEntries as [$batchIri, $date, $count, $reason]) {
            $authenticatedRequest('POST', '/mortality_records.jsonld', [
                'headers' => ['Content-Type' => 'application/ld+json'],
                'json' => [
                    'batch' => $batchIri,
                    'date' => $date,
                    'count' => $count,
                    'reason' => $reason,
                ],
            ]);
            $this->assertResponseStatusCodeSame(201);
        }

        $lineTrendResponse = $authenticatedRequest('GET', '/zefix/mortality-trends?' . http_build_query([
            'lineID' => $line->getId()?->toRfc4122(),
        ]), [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        self::assertSame([
            ['date' => '2024-04-01', 'mortalities' => 5],
            ['date' => '2024-04-02', 'mortalities' => 4],
        ], $lineTrendResponse->toArray());

        $roomTrendResponse = $authenticatedRequest('GET', '/zefix/mortality-trends?' . http_build_query([
            'roomID' => $roomOne->getId()?->toRfc4122(),
        ]), [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        self::assertSame([
            ['date' => '2024-04-01', 'mortalities' => 5],
        ], $roomTrendResponse->toArray());

        $plateauTrendResponse = $authenticatedRequest('GET', '/zefix/mortality-trends?' . http_build_query([
            'plateauID' => $plateau->getId()?->toRfc4122(),
        ]), [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        self::assertSame([
            ['date' => '2024-04-01', 'mortalities' => 5],
            ['date' => '2024-04-02', 'mortalities' => 4],
        ], $plateauTrendResponse->toArray());

        $lineDetailResponse = $authenticatedRequest('GET', '/zefix/lines/' . ($line->getId()?->toRfc4122() ?? ''), [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        self::assertSame(22.5, $lineDetailResponse->toArray()['line']['mortalityRate']);
    }

    #[Test]
    public function it_rejects_mortality_entries_that_exceed_current_population(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
            'roles' => [Roles::ROLE_ADMIN],
        ]);

        $plateau = PlateauFactory::createOne([
            'name' => 'North Aquatics Platform',
            'code' => 'ZF-NORTH',
            'account' => $account,
        ]);
        $room = RoomFactory::createOne([
            'name' => 'Room 101',
            'plateau' => $plateau,
            'account' => $account,
        ]);
        $rack = RackFactory::createOne([
            'code' => 'RACK-01',
            'room' => $room,
            'account' => $account,
        ]);
        $pool = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $line = ZebrafishLineFactory::createOne([
            'name' => 'Tg(ins:mCherry)',
            'status' => ZebrafishLineStatus::ACTIVE,
            'account' => $account,
        ]);

        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $batchResponse = $authenticatedRequest('POST', '/zebrafish_batches.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'line' => $this->findIriBy(\App\Entity\ZebrafishLine::class, ['id' => $line->getId()?->toRfc4122()]),
                'pool' => $this->findIriBy(\App\Entity\Pool::class, ['id' => $pool->getId()?->toRfc4122()]),
                'birthDate' => '2024-03-18',
                'maleCount' => 2,
                'femaleCount' => 1,
                'unknownSexCount' => 0,
                'totalPopulation' => 999,
                'lifecycleStatus' => '/zebrafish_batch_lifecycle_statuses/active',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $batchIri = $batchResponse->toArray()['@id'];

        $authenticatedRequest('POST', '/mortality_records.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'batch' => $batchIri,
                'date' => '2024-04-05',
                'count' => 4,
                'reason' => '/mortality_reasons/' . MortalityReason::DISEASE->value,
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }
}
