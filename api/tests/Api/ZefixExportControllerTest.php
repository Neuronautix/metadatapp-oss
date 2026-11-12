<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\MortalityRecordFactory;
use App\DataFixtures\Factory\PlateauFactory;
use App\DataFixtures\Factory\PoolFactory;
use App\DataFixtures\Factory\RackFactory;
use App\DataFixtures\Factory\RoomFactory;
use App\DataFixtures\Factory\SystemFactory;
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

final class ZefixExportControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_exports_the_main_zefix_csv_surfaces_from_real_state(): void
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
            'system' => $system,
            'account' => $account,
        ]);
        $poolA1 = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $poolA2 = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A2',
            'capacity' => 80,
            'status' => PoolStatus::AVAILABLE,
            'account' => $account,
        ]);

        $activeLine = ZebrafishLineFactory::createOne([
            'name' => 'Tg(active:line)',
            'genotype' => 'Tg(active:line)zf410',
            'purpose' => 'Pan-neuronal calcium reporter',
            'status' => ZebrafishLineStatus::ACTIVE,
            'account' => $account,
        ]);
        $pausedLine = ZebrafishLineFactory::createOne([
            'name' => 'Tg(paused:line)',
            'genotype' => 'Tg(paused:line)zf411',
            'purpose' => 'Paused glial reporter',
            'status' => ZebrafishLineStatus::PAUSED,
            'account' => $account,
        ]);

        $activeBatch = ZebrafishBatchFactory::createOne([
            'line' => $activeLine,
            'pool' => $poolA1,
            'account' => $account,
            'birthDate' => new \DateTimeImmutable('2026-03-05'),
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
            'birthDate' => new \DateTimeImmutable('2026-01-15'),
            'maleCount' => 8,
            'femaleCount' => 7,
            'unknownSexCount' => 0,
            'totalPopulation' => 15,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::QUARANTINED,
        ]);

        MortalityRecordFactory::createOne([
            'batch' => $activeBatch,
            'date' => new \DateTimeImmutable('today 09:00:00'),
            'count' => 3,
            'reason' => MortalityReason::DISEASE,
            'notes' => 'Morning check mortality export.',
            'account' => $account,
            'user' => $user,
        ]);
        MortalityRecordFactory::createOne([
            'batch' => $quarantinedBatch,
            'date' => new \DateTimeImmutable('-2 days 09:00:00'),
            'count' => 2,
            'reason' => MortalityReason::UNKNOWN,
            'notes' => 'Historical mortality row.',
            'account' => $account,
            'user' => $user,
        ]);

        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $poolResponse = $authenticatedRequest('GET', '/zefix/exports/pool-occupancy.csv', [
            'headers' => ['Accept' => 'text/csv'],
            'query' => [
                'plateauID' => $plateau->getId()?->toRfc4122() ?? '',
            ],
        ]);
        self::assertSame(200, $poolResponse->getStatusCode());
        self::assertSame('text/csv; charset=UTF-8', $poolResponse->getHeaders(false)['content-type'][0] ?? null);
        self::assertStringContainsString('ZF-NORTH,"Room 301",RACK-169,A1,SYS-169,occupied,yes,', $poolResponse->getContent());

        $batchResponse = $authenticatedRequest('GET', '/zefix/exports/batch-history.csv', [
            'headers' => ['Accept' => 'text/csv'],
            'query' => [
                'lineID' => $activeLine->getId()?->toRfc4122() ?? '',
            ],
        ]);
        self::assertSame(200, $batchResponse->getStatusCode());
        self::assertStringContainsString('Tg(active:line)', $batchResponse->getContent());
        self::assertStringContainsString('Room 301 / RACK-169 / A1', $batchResponse->getContent());

        $lineResponse = $authenticatedRequest('GET', '/zefix/exports/line-statistics.csv', [
            'headers' => ['Accept' => 'text/csv'],
            'query' => [
                'status' => 'active',
            ],
        ]);
        self::assertSame(200, $lineResponse->getStatusCode());
        self::assertStringContainsString('Tg(active:line)', $lineResponse->getContent());
        self::assertStringContainsString('Watch', $lineResponse->getContent());

        $mortalityResponse = $authenticatedRequest('GET', '/zefix/exports/mortality-logs.csv', [
            'headers' => ['Accept' => 'text/csv'],
            'query' => [
                'batchID' => $activeBatch->getId()?->toRfc4122() ?? '',
            ],
        ]);
        self::assertSame(200, $mortalityResponse->getStatusCode());
        self::assertStringContainsString('Morning check mortality export.', $mortalityResponse->getContent());
        self::assertStringContainsString('disease', $mortalityResponse->getContent());
    }

    #[Test]
    public function it_exports_a_basic_pdf_line_report(): void
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
        $rack = RackFactory::createOne([
            'code' => 'RACK-169',
            'room' => $room,
            'account' => $account,
        ]);
        $pool = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A1',
            'capacity' => 80,
            'account' => $account,
        ]);

        $line = ZebrafishLineFactory::createOne([
            'name' => 'Tg(active:line)',
            'genotype' => 'Tg(active:line)zf410',
            'purpose' => 'Pan-neuronal calcium reporter',
            'status' => ZebrafishLineStatus::ACTIVE,
            'account' => $account,
        ]);

        ZebrafishBatchFactory::createOne([
            'line' => $line,
            'pool' => $pool,
            'account' => $account,
            'birthDate' => new \DateTimeImmutable('2026-03-05'),
            'maleCount' => 10,
            'femaleCount' => 10,
            'unknownSexCount' => 0,
            'totalPopulation' => 20,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
        ]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('GET', '/zefix/reports/lines/' . ($line->getId()?->toRfc4122() ?? '') . '.pdf', [
            'headers' => ['Accept' => 'application/pdf'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->getHeaders(false)['content-type'][0] ?? null);
        self::assertStringStartsWith('%PDF-1.4', $response->getContent());
        self::assertStringContainsString('Tg\\(active:line\\)', $response->getContent());
        self::assertStringContainsString('Current animals:', $response->getContent());
    }
}
