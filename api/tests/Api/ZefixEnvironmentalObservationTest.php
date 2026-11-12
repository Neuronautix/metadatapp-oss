<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\PlateauFactory;
use App\DataFixtures\Factory\RackFactory;
use App\DataFixtures\Factory\RoomFactory;
use App\DataFixtures\Factory\SystemFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Enum\EnvironmentalMetric;
use App\Enum\EnvironmentalObservationSource;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ZefixEnvironmentalObservationTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_persists_manual_environmental_observations_and_rolls_them_up_by_system_and_room(): void
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
        $system = SystemFactory::createOne([
            'code' => 'SYS-170',
            'manufacturer' => 'Tecniplast',
            'model' => 'ZebTEC Active Blue',
            'room' => $room,
            'account' => $account,
            'waterTemperature' => 19.8,
            'pH' => 7.1,
            'conductivity' => 500.0,
            'oxygen' => 7.7,
            'waterFlow' => 12.3,
            'filtrationStatus' => 'OK',
        ]);
        RackFactory::createOne([
            'code' => 'RACK-170',
            'room' => $room,
            'account' => $account,
            'system' => $system,
        ]);

        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $temperatureResponse = $authenticatedRequest('POST', '/zefix/environmental-observations', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json' => [
                'scopeType' => 'system',
                'scopeID' => 'SYS-170',
                'metric' => EnvironmentalMetric::TEMPERATURE->value,
                'value' => 20.4,
                'timestamp' => '2024-03-01T09:00:00+00:00',
                'source' => EnvironmentalObservationSource::MANUAL->value,
                'notes' => 'Manual temperature reading',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains([
            'scopeType' => 'system',
            'scopeID' => 'SYS-170',
            'value' => 20.4,
            'notes' => 'Manual temperature reading',
        ]);

        $phResponse = $authenticatedRequest('POST', '/zefix/environmental-observations', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json' => [
                'scopeType' => 'system',
                'scopeID' => 'SYS-170',
                'metric' => EnvironmentalMetric::PH->value,
                'value' => 7.35,
                'timestamp' => '2024-03-01T10:00:00+00:00',
                'source' => EnvironmentalObservationSource::MANUAL->value,
                'notes' => 'Manual pH reading',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains([
            'scopeType' => 'system',
            'scopeID' => 'SYS-170',
            'value' => 7.35,
            'notes' => 'Manual pH reading',
        ]);

        $systemResponse = $authenticatedRequest('GET', '/zefix/systems/SYS-170', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(20.4, $systemResponse->toArray()['waterTemperature']);
        self::assertSame(7.35, $systemResponse->toArray()['pH']);
        self::assertSame($room->getId()?->toRfc4122(), $systemResponse->toArray()['roomID']);
        self::assertSame('Room 101', $systemResponse->toArray()['roomName']);

        $systemHistoryResponse = $authenticatedRequest('GET', '/zefix/systems/SYS-170/history', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $systemHistoryResponse->toArray());
        self::assertSame(20.4, $systemHistoryResponse->toArray()[0]['temperature']);
        self::assertSame(0.0, $systemHistoryResponse->toArray()[0]['pH']);
        self::assertSame(20.4, $systemHistoryResponse->toArray()[1]['temperature']);
        self::assertSame(7.35, $systemHistoryResponse->toArray()[1]['pH']);

        $roomResponse = $authenticatedRequest('GET', sprintf('/zefix/rooms/%s', $room->getId()?->toRfc4122()), [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(20.4, $roomResponse->toArray()['roomTemperature']);
        self::assertSame(0.0, $roomResponse->toArray()['humidity']);
        self::assertCount(1, $roomResponse->toArray()['temperatureTrend']);
        self::assertCount(1, $roomResponse->toArray()['pHTrend']);
        self::assertSame(0, $roomResponse->toArray()['mortality30d']);
        self::assertSame(0, $roomResponse->toArray()['breedingEvents30d']);
    }

    #[Test]
    public function it_rejects_observations_against_another_accounts_system(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $otherAccount = AccountFactory::createOne(['name' => 'South Facility']);

        $user = UserFactory::createOne([
            'account' => $account,
            'roles' => [Roles::ROLE_ADMIN],
        ]);
        UserFactory::createOne([
            'account' => $otherAccount,
            'roles' => [Roles::ROLE_ADMIN],
        ]);

        $northPlateau = PlateauFactory::createOne([
            'name' => 'North Aquatics Platform',
            'code' => 'ZF-NORTH',
            'account' => $account,
        ]);
        $northRoom = RoomFactory::createOne([
            'name' => 'Room 101',
            'plateau' => $northPlateau,
            'account' => $account,
        ]);
        SystemFactory::createOne([
            'code' => 'SYS-170A',
            'manufacturer' => 'Tecniplast',
            'model' => 'ZebTEC Active Blue',
            'room' => $northRoom,
            'account' => $account,
        ]);

        $southPlateau = PlateauFactory::createOne([
            'name' => 'South Aquatics Platform',
            'code' => 'ZF-SOUTH',
            'account' => $otherAccount,
        ]);
        $southRoom = RoomFactory::createOne([
            'name' => 'Room 201',
            'plateau' => $southPlateau,
            'account' => $otherAccount,
        ]);
        SystemFactory::createOne([
            'code' => 'SYS-170B',
            'manufacturer' => 'Tecniplast',
            'model' => 'ZebTEC Active Blue',
            'room' => $southRoom,
            'account' => $otherAccount,
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
                'scopeID' => 'SYS-170B',
                'metric' => EnvironmentalMetric::TEMPERATURE->value,
                'value' => 21.0,
                'timestamp' => '2024-03-01T09:00:00+00:00',
                'source' => EnvironmentalObservationSource::MANUAL->value,
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }
}
