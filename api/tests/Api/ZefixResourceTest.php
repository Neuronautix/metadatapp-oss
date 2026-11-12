<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\PlateauFactory;
use App\DataFixtures\Factory\PoolFactory;
use App\DataFixtures\Factory\RackFactory;
use App\DataFixtures\Factory\RoomFactory;
use App\DataFixtures\Factory\MortalityRecordFactory;
use App\Entity\Pool;
use App\Entity\ZebrafishLine;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\ZebrafishBatchFactory;
use App\DataFixtures\Factory\ZebrafishLineFactory;
use App\Enum\MortalityReason;
use App\Enum\PoolStatus;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Enum\ZebrafishLineStatus;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ZefixResourceTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_creates_the_zefix_hierarchy_and_exposes_pool_occupancy_filters(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN]]);
        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $plateauResponse = $authenticatedRequest('POST', '/plateaux.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Aquatic Platform Alpha',
                'code' => 'AQUA-ALPHA',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $plateauIri = $plateauResponse->toArray()['@id'];

        $roomResponse = $authenticatedRequest('POST', '/rooms.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Room 203',
                'plateau' => $plateauIri,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $roomIri = $roomResponse->toArray()['@id'];

        $rackResponse = $authenticatedRequest('POST', '/racks.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'code' => 'RACK-01',
                'room' => $roomIri,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $rackIri = $rackResponse->toArray()['@id'];

        $poolResponse = $authenticatedRequest('POST', '/pools.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'rack' => $rackIri,
                'position' => 'A1',
                'capacity' => 80,
                'status' => '/pool_statuses/occupied',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $poolIri = $poolResponse->toArray()['@id'];

        $lineResponse = $authenticatedRequest('POST', '/zebrafish_lines.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Tg(elavl3:GCaMP6s)',
                'genotype' => 'Tg(elavl3:GCaMP6s)zf410',
                'purpose' => 'Pan-neuronal calcium reporter',
                'status' => '/zebrafish_line_statuses/active',
                'createdAt' => '2024-01-10T09:00:00+00:00',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $lineIri = $lineResponse->toArray()['@id'];

        $batchResponse = $authenticatedRequest('POST', '/zebrafish_batches.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'line' => $lineIri,
                'pool' => $poolIri,
                'birthDate' => '2024-02-01',
                'maleCount' => 12,
                'femaleCount' => 14,
                'unknownSexCount' => 4,
                'totalPopulation' => 30,
                'lifecycleStatus' => '/zebrafish_batch_lifecycle_statuses/active',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $batchIri = $batchResponse->toArray()['@id'];

        $poolItem = $authenticatedRequest('GET', $poolIri);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $poolIri,
            'position' => 'A1',
        ]);
        $poolItemData = $poolItem->toArray();
        $this->assertSame($batchIri, $poolItemData['activeBatch']['@id']);

        $poolCollection = $authenticatedRequest('GET', '/pools.jsonld?' . http_build_query([
            'rack.room' => $roomIri,
            'status' => 'occupied',
            'batches.lifecycleStatus' => 'active',
        ]));
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $poolCollection->toArray()['hydra:member']);
        $this->assertSame($poolIri, $poolCollection->toArray()['hydra:member'][0]['@id']);

        $batchCollection = $authenticatedRequest('GET', '/zebrafish_batches.jsonld?' . http_build_query([
            'pool' => $poolIri,
            'lifecycleStatus' => 'active',
        ]));
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $batchCollection->toArray()['hydra:member']);
        $this->assertSame($batchIri, $batchCollection->toArray()['hydra:member'][0]['@id']);

        $authenticatedRequest('PATCH', $lineIri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'status' => '/zebrafish_line_statuses/paused',
                'purpose' => 'Updated purpose',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $lineIri,
            'purpose' => 'Updated purpose',
        ]);

        $authenticatedRequest('PATCH', $batchIri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'lifecycleStatus' => '/zebrafish_batch_lifecycle_statuses/completed',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $batchIri,
        ]);

        $poolAfterBatchCompletion = $authenticatedRequest('GET', $poolIri);
        $this->assertResponseIsSuccessful();
        $poolAfterBatchCompletionData = $poolAfterBatchCompletion->toArray();
        $this->assertSame($poolIri, $poolAfterBatchCompletionData['@id']);
        $this->assertArrayNotHasKey('activeBatch', $poolAfterBatchCompletionData);

        $authenticatedRequest('DELETE', $batchIri);
        $this->assertResponseStatusCodeSame(204);

        $lineDeleteResponse = $authenticatedRequest('DELETE', $lineIri);
        $this->assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_supports_basic_collection_reads_for_all_zefix_resources(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN]]);

        foreach (['/plateaux.jsonld', '/rooms.jsonld', '/racks.jsonld', '/pools.jsonld', '/zebrafish_lines.jsonld', '/zebrafish_batches.jsonld'] as $endpoint) {
            $client = static::createClient();
            $client->loginUser($user);
            $client->request('GET', $endpoint);
            $this->assertResponseIsSuccessful();
            $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        }
    }

    #[Test]
    public function it_rejects_batch_creation_into_a_pool_with_another_active_batch(): void
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
        $occupiedPool = PoolFactory::createOne([
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
        ZebrafishBatchFactory::createOne([
            'line' => $line,
            'pool' => $occupiedPool,
            'birthDate' => new \DateTimeImmutable('2024-03-18'),
            'maleCount' => 10,
            'femaleCount' => 11,
            'unknownSexCount' => 2,
            'totalPopulation' => 23,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
            'account' => $account,
        ]);

        $client = static::createClient();
        $client->loginUser($user);

        $client->request('POST', '/zebrafish_batches.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'line' => $this->findIriBy(ZebrafishLine::class, ['name' => $line->getName()]),
                'pool' => $this->findIriBy(Pool::class, ['position' => 'A1']),
                'birthDate' => '2024-04-01',
                'maleCount' => 8,
                'femaleCount' => 9,
                'unknownSexCount' => 1,
                'totalPopulation' => 18,
                'lifecycleStatus' => '/zebrafish_batch_lifecycle_statuses/active',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_exposes_location_explorer_data_from_real_backend_state(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN]]);
        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $plateauResponse = $authenticatedRequest('POST', '/plateaux.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'North Aquatics Platform',
                'code' => 'ZF-NORTH',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $plateauIri = $plateauResponse->toArray()['@id'];
        $plateauId = basename($plateauIri);

        $roomResponse = $authenticatedRequest('POST', '/rooms.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Room 101',
                'plateau' => $plateauIri,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $roomIri = $roomResponse->toArray()['@id'];
        $roomId = basename($roomIri);

        $rackResponse = $authenticatedRequest('POST', '/racks.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'code' => 'RACK-01',
                'room' => $roomIri,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $rackIri = $rackResponse->toArray()['@id'];
        $rackId = basename($rackIri);

        $occupiedPoolResponse = $authenticatedRequest('POST', '/pools.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'rack' => $rackIri,
                'position' => 'A1',
                'capacity' => 80,
                'status' => '/pool_statuses/occupied',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $occupiedPoolId = basename($occupiedPoolResponse->toArray()['@id']);

        $emptyPoolResponse = $authenticatedRequest('POST', '/pools.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'rack' => $rackIri,
                'position' => 'A2',
                'capacity' => 80,
                'status' => '/pool_statuses/available',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $emptyPoolId = basename($emptyPoolResponse->toArray()['@id']);

        $lineResponse = $authenticatedRequest('POST', '/zebrafish_lines.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Tg(elavl3:GCaMP6s)',
                'genotype' => 'Tg(elavl3:GCaMP6s)zf410',
                'purpose' => 'Pan-neuronal calcium reporter',
                'status' => '/zebrafish_line_statuses/active',
                'createdAt' => '2024-01-10T09:00:00+00:00',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $lineIri = $lineResponse->toArray()['@id'];
        $lineId = basename($lineIri);

        $batchResponse = $authenticatedRequest('POST', '/zebrafish_batches.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'line' => $lineIri,
                'pool' => $occupiedPoolResponse->toArray()['@id'],
                'birthDate' => '2024-02-01',
                'maleCount' => 12,
                'femaleCount' => 14,
                'unknownSexCount' => 4,
                'totalPopulation' => 30,
                'lifecycleStatus' => '/zebrafish_batch_lifecycle_statuses/active',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $batchId = basename($batchResponse->toArray()['@id']);

        $response = $authenticatedRequest('GET', '/zefix/location-explorer');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $payload = $response->toArray();
        $this->assertArrayHasKey('facility', $payload);
        $this->assertArrayHasKey('plateaux', $payload);
        $this->assertCount(1, $payload['plateaux']);

        $plateau = $payload['plateaux'][0];
        $this->assertSame($plateauId, $plateau['plateauId']);
        $this->assertSame('North Aquatics Platform', $plateau['name']);
        $this->assertSame('ZF-NORTH', $plateau['code']);

        $room = $plateau['rooms'][0];
        $this->assertSame($roomId, $room['roomId']);
        $this->assertSame('Room 101', $room['name']);

        $rack = $room['racks'][0];
        $this->assertSame($rackId, $rack['rackId']);
        $this->assertSame('RACK-01', $rack['code']);
        $this->assertCount(2, $rack['pools']);

        $occupiedPool = $rack['pools'][0];
        $this->assertSame($occupiedPoolId, $occupiedPool['poolId']);
        $this->assertSame('A1', $occupiedPool['position']);
        $this->assertTrue($occupiedPool['occupied']);
        $this->assertSame('occupied', $occupiedPool['status']);
        $this->assertSame($batchId, $occupiedPool['batch']['batchId']);
        $this->assertSame($lineId, $occupiedPool['batch']['lineId']);
        $this->assertSame('Tg(elavl3:GCaMP6s)', $occupiedPool['batch']['lineName']);
        $this->assertSame('2024-02-01', $occupiedPool['batch']['birthDate']);
        $this->assertSame(30, $occupiedPool['batch']['totalPopulation']);
        $this->assertSame('active', $occupiedPool['batch']['lifecycleStatus']);

        $emptyPool = $rack['pools'][1];
        $this->assertSame($emptyPoolId, $emptyPool['poolId']);
        $this->assertSame('A2', $emptyPool['position']);
        $this->assertFalse($emptyPool['occupied']);
        $this->assertSame('available', $emptyPool['status']);
        $this->assertArrayNotHasKey('batch', $emptyPool);
    }

    #[Test]
    public function it_exposes_real_zefix_line_collections_and_detail_for_the_authenticated_account(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
            'roles' => [Roles::ROLE_ADMIN],
        ]);

        $otherAccount = AccountFactory::createOne(['name' => 'South Facility']);
        $otherUser = UserFactory::createOne([
            'account' => $otherAccount,
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
        $poolA = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $poolB = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A2',
            'capacity' => 80,
            'status' => PoolStatus::AVAILABLE,
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

        ZebrafishLineFactory::createOne([
            'name' => 'Tg(mpx:GFP)',
            'genotype' => 'Tg(mpx:GFP)i114',
            'purpose' => 'Should not leak across accounts',
            'status' => ZebrafishLineStatus::ACTIVE,
            'account' => $otherAccount,
        ]);

        ZebrafishBatchFactory::createOne([
            'line' => $line,
            'pool' => $poolA,
            'birthDate' => new \DateTimeImmutable('2024-03-18'),
            'maleCount' => 10,
            'femaleCount' => 11,
            'unknownSexCount' => 2,
            'totalPopulation' => 23,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
            'account' => $account,
        ]);

        ZebrafishBatchFactory::createOne([
            'line' => $line,
            'pool' => $poolB,
            'birthDate' => new \DateTimeImmutable('2024-01-12'),
            'maleCount' => 8,
            'femaleCount' => 7,
            'unknownSexCount' => 1,
            'totalPopulation' => 16,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::QUARANTINED,
            'account' => $account,
        ]);

        $authenticatedRequest = function (string $uri) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request('GET', $uri, [
                'headers' => ['Accept' => 'application/json'],
            ]);
        };

        $collectionResponse = $authenticatedRequest('/zefix/lines?search=mCherry');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');

        $collectionPayload = $collectionResponse->toArray();
        $this->assertCount(1, $collectionPayload);
        $this->assertSame($line->getId()?->toRfc4122(), $collectionPayload[0]['lineID']);
        $this->assertSame('Tg(ins:mCherry)', $collectionPayload[0]['name']);
        $this->assertSame(39, $collectionPayload[0]['totalAnimals']);
        $this->assertSame(2, $collectionPayload[0]['activeBatches']);

        $detailResponse = $authenticatedRequest('/zefix/lines/' . ($line->getId()?->toRfc4122() ?? ''));
        $this->assertResponseIsSuccessful();

        $detailPayload = $detailResponse->toArray();
        $this->assertSame($line->getId()?->toRfc4122(), $detailPayload['line']['lineID']);
        $this->assertCount(2, $detailPayload['batches']);
        $this->assertSame(23, $detailPayload['batches'][0]['individualCount']);
        $this->assertSame($poolA->getId()?->toRfc4122(), $detailPayload['batches'][0]['locationId']);
        $this->assertSame($room->getId()?->toRfc4122(), $detailPayload['batches'][0]['roomID']);
        $this->assertStringContainsString('ZF-NORTH', $detailPayload['batches'][0]['tankLocation']);

        $otherClient = static::createClient();
        $otherClient->loginUser($otherUser);
        $otherClient->request('GET', '/zefix/lines/' . ($line->getId()?->toRfc4122() ?? ''), [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function it_updates_pool_occupancy_and_normalizes_total_population_when_creating_batches(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
            'roles' => [Roles::ROLE_ADMIN],
        ]);

        $otherAccount = AccountFactory::createOne(['name' => 'South Facility']);
        $otherUser = UserFactory::createOne([
            'account' => $otherAccount,
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
        $occupiedPool = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $availablePool = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A2',
            'capacity' => 80,
            'status' => PoolStatus::AVAILABLE,
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

        $existingBatch = ZebrafishBatchFactory::createOne([
            'line' => $line,
            'pool' => $occupiedPool,
            'birthDate' => new \DateTimeImmutable('2024-03-18'),
            'maleCount' => 10,
            'femaleCount' => 11,
            'unknownSexCount' => 2,
            'totalPopulation' => 23,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
            'account' => $account,
        ]);

        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $createdBatchResponse = $authenticatedRequest('POST', '/zebrafish_batches.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'line' => '/zebrafish_lines/' . ($line->getId()?->toRfc4122() ?? ''),
                'pool' => '/pools/' . ($availablePool->getId()?->toRfc4122() ?? ''),
                'birthDate' => '2024-04-03',
                'maleCount' => 8,
                'femaleCount' => 9,
                'unknownSexCount' => 1,
                'totalPopulation' => 999,
                'lifecycleStatus' => '/zebrafish_batch_lifecycle_statuses/active',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $createdBatchPayload = $createdBatchResponse->toArray();
        $createdBatchId = basename($createdBatchPayload['@id']);
        $this->assertSame(18, $createdBatchPayload['totalPopulation']);
        $this->assertSame(8, $createdBatchPayload['maleCount']);
        $this->assertSame(9, $createdBatchPayload['femaleCount']);
        $this->assertSame(1, $createdBatchPayload['unknownSexCount']);

        $updatedPoolResponse = $authenticatedRequest('GET', '/pools/' . ($availablePool->getId()?->toRfc4122() ?? ''));
        $this->assertResponseIsSuccessful();
        $updatedPoolPayload = $updatedPoolResponse->toArray();
        $this->assertSame('occupied', $updatedPoolPayload['status']['value']);
        $this->assertSame($createdBatchPayload['@id'], $updatedPoolPayload['activeBatch']['@id']);

        $explorerResponse = $authenticatedRequest('GET', '/zefix/location-explorer');
        $this->assertResponseIsSuccessful();
        $explorerPayload = $explorerResponse->toArray();
        $explorerPools = $explorerPayload['plateaux'][0]['rooms'][0]['racks'][0]['pools'];
        $this->assertSame($createdBatchId, $explorerPools[1]['batch']['batchId']);
        $this->assertTrue($explorerPools[1]['occupied']);
    }

    #[Test]
    public function it_rejects_creating_a_batch_in_an_occupied_or_non_available_pool(): void
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
        $occupiedPool = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $maintenancePool = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A2',
            'capacity' => 80,
            'status' => PoolStatus::MAINTENANCE,
            'account' => $account,
        ]);
        $line = ZebrafishLineFactory::createOne([
            'name' => 'Tg(ins:mCherry)',
            'account' => $account,
        ]);

        ZebrafishBatchFactory::createOne([
            'line' => $line,
            'pool' => $occupiedPool,
            'birthDate' => new \DateTimeImmutable('2024-03-18'),
            'maleCount' => 10,
            'femaleCount' => 11,
            'unknownSexCount' => 2,
            'totalPopulation' => 23,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
            'account' => $account,
        ]);

        $authenticatedRequest = function (string $poolPath) use ($user, $line) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request('POST', '/zebrafish_batches.jsonld', [
                'headers' => ['Content-Type' => 'application/ld+json'],
                'json' => [
                    'line' => '/zebrafish_lines/' . ($line->getId()?->toRfc4122() ?? ''),
                    'pool' => $poolPath,
                    'birthDate' => '2024-04-03',
                    'maleCount' => 8,
                    'femaleCount' => 9,
                    'unknownSexCount' => 1,
                    'totalPopulation' => 18,
                    'lifecycleStatus' => '/zebrafish_batch_lifecycle_statuses/active',
                ],
            ]);
        };

        $occupiedPoolResponse = $authenticatedRequest('/pools/' . ($occupiedPool->getId()?->toRfc4122() ?? ''));
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString(
            'empty or available pool',
            $occupiedPoolResponse->getContent(false) ?: ''
        );

        $maintenancePoolResponse = $authenticatedRequest('/pools/' . ($maintenancePool->getId()?->toRfc4122() ?? ''));
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString(
            'empty or available pool',
            $maintenancePoolResponse->getContent(false) ?: ''
        );
    }

    #[Test]
    public function it_persists_mortality_records_and_recomputes_batch_and_line_state(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
            'roles' => [Roles::ROLE_ADMIN],
            'firstName' => 'Zoe',
            'lastName' => 'Curie',
            'email' => 'zoe.curie@example.test',
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
        $batch = ZebrafishBatchFactory::createOne([
            'line' => $line,
            'pool' => $pool,
            'birthDate' => new \DateTimeImmutable('2024-03-18'),
            'maleCount' => 10,
            'femaleCount' => 11,
            'unknownSexCount' => 2,
            'totalPopulation' => 23,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
            'account' => $account,
        ]);

        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $recordResponse = $authenticatedRequest('POST', '/mortality_records.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'batch' => '/zebrafish_batches/' . ($batch->getId()?->toRfc4122() ?? ''),
                'date' => '2024-04-01T09:30:00+00:00',
                'count' => 4,
                'reason' => '/mortality_reasons/disease',
                'notes' => 'Escalated to veterinary review.',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $recordPayload = $recordResponse->toArray();
        $this->assertSame(4, $recordPayload['count']);
        $this->assertSame('Escalated to veterinary review.', $recordPayload['notes']);

        $detailResponse = $authenticatedRequest('GET', '/zefix/batches/' . ($batch->getId()?->toRfc4122() ?? ''), [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        $detailPayload = $detailResponse->toArray();
        $this->assertSame(19, $detailPayload['batch']['individualCount']);
        $this->assertSame(23, $detailPayload['batch']['initialCount']);
        $this->assertSame(4, $detailPayload['batch']['mortalityCount']);
        $this->assertCount(2, $detailPayload['timeline']);
        $this->assertSame('Mortality', $detailPayload['timeline'][1]['title']);
        $this->assertCount(1, $detailPayload['mortalityEvents']);
        $this->assertSame('disease', $detailPayload['mortalityEvents'][0]['reason']);
        $this->assertSame('Escalated to veterinary review.', $detailPayload['mortalityEvents'][0]['notes']);
        $this->assertSame('Zoe Curie', $detailPayload['mortalityEvents'][0]['recorder']);

        $lineResponse = $authenticatedRequest('GET', '/zefix/lines/' . ($line->getId()?->toRfc4122() ?? ''), [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        $linePayload = $lineResponse->toArray();
        $this->assertSame(19, $linePayload['line']['totalAnimals']);
        $this->assertSame(17.4, $linePayload['line']['mortalityRate']);
        $this->assertSame(4, $linePayload['batches'][0]['mortalityCount']);
        $this->assertSame(19, $linePayload['batches'][0]['individualCount']);
    }

    #[Test]
    public function it_aggregates_mortality_trends_by_line_room_and_plateau(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
            'roles' => [Roles::ROLE_ADMIN],
        ]);

        $plateauA = PlateauFactory::createOne([
            'name' => 'North Aquatics Platform',
            'code' => 'ZF-NORTH',
            'account' => $account,
        ]);
        $plateauB = PlateauFactory::createOne([
            'name' => 'South Aquatics Platform',
            'code' => 'ZF-SOUTH',
            'account' => $account,
        ]);
        $roomA = RoomFactory::createOne([
            'name' => 'Room 101',
            'plateau' => $plateauA,
            'account' => $account,
        ]);
        $roomB = RoomFactory::createOne([
            'name' => 'Room 201',
            'plateau' => $plateauB,
            'account' => $account,
        ]);
        $rackA = RackFactory::createOne([
            'code' => 'RACK-01',
            'room' => $roomA,
            'account' => $account,
        ]);
        $rackB = RackFactory::createOne([
            'code' => 'RACK-02',
            'room' => $roomB,
            'account' => $account,
        ]);
        $poolA = PoolFactory::createOne([
            'rack' => $rackA,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $poolB = PoolFactory::createOne([
            'rack' => $rackB,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $lineA = ZebrafishLineFactory::createOne([
            'name' => 'Tg(ins:mCherry)',
            'account' => $account,
        ]);
        $lineB = ZebrafishLineFactory::createOne([
            'name' => 'Tg(mpx:GFP)',
            'account' => $account,
        ]);
        $batchA = ZebrafishBatchFactory::createOne([
            'line' => $lineA,
            'pool' => $poolA,
            'birthDate' => new \DateTimeImmutable('2024-03-18'),
            'maleCount' => 10,
            'femaleCount' => 11,
            'unknownSexCount' => 2,
            'totalPopulation' => 23,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
            'account' => $account,
        ]);
        $batchB = ZebrafishBatchFactory::createOne([
            'line' => $lineB,
            'pool' => $poolB,
            'birthDate' => new \DateTimeImmutable('2024-03-20'),
            'maleCount' => 9,
            'femaleCount' => 8,
            'unknownSexCount' => 1,
            'totalPopulation' => 18,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
            'account' => $account,
        ]);

        MortalityRecordFactory::createOne([
            'batch' => $batchA,
            'date' => new \DateTimeImmutable('2024-04-01T09:30:00+00:00'),
            'count' => 4,
            'reason' => MortalityReason::DISEASE,
            'account' => $account,
            'user' => $user,
        ]);
        MortalityRecordFactory::createOne([
            'batch' => $batchA,
            'date' => new \DateTimeImmutable('2024-04-02T09:30:00+00:00'),
            'count' => 1,
            'reason' => MortalityReason::NATURAL,
            'account' => $account,
            'user' => $user,
        ]);
        MortalityRecordFactory::createOne([
            'batch' => $batchB,
            'date' => new \DateTimeImmutable('2024-04-01T11:00:00+00:00'),
            'count' => 2,
            'reason' => MortalityReason::UNKNOWN,
            'account' => $account,
            'user' => $user,
        ]);

        $authenticatedRequest = function (string $uri) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request('GET', $uri, [
                'headers' => ['Accept' => 'application/json'],
            ]);
        };

        $lineTrendResponse = $authenticatedRequest('/zefix/mortality-trends?lineID=' . ($lineA->getId()?->toRfc4122() ?? ''));
        $this->assertResponseIsSuccessful();
        $lineTrendPayload = $lineTrendResponse->toArray();
        $this->assertCount(2, $lineTrendPayload);
        $this->assertSame('2024-04-01', $lineTrendPayload[0]['date']);
        $this->assertSame(4, $lineTrendPayload[0]['mortalities']);

        $roomTrendResponse = $authenticatedRequest('/zefix/mortality-trends?roomID=' . ($roomB->getId()?->toRfc4122() ?? ''));
        $this->assertResponseIsSuccessful();
        $roomTrendPayload = $roomTrendResponse->toArray();
        $this->assertCount(1, $roomTrendPayload);
        $this->assertSame(2, $roomTrendPayload[0]['mortalities']);

        $plateauTrendResponse = $authenticatedRequest('/zefix/mortality-trends?plateauID=' . ($plateauA->getId()?->toRfc4122() ?? ''));
        $this->assertResponseIsSuccessful();
        $plateauTrendPayload = $plateauTrendResponse->toArray();
        $this->assertCount(2, $plateauTrendPayload);
        $this->assertSame(4, $plateauTrendPayload[0]['mortalities']);
        $this->assertSame(1, $plateauTrendPayload[1]['mortalities']);
    }

}
