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
use App\Enum\MortalityReason;
use App\Enum\PoolStatus;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ZefixPermissionTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_keeps_viewers_read_only_and_allows_elevated_users_to_write_and_export(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $adminUser = UserFactory::createOne([
            'account' => $account,
            'roles' => [Roles::ROLE_ADMIN],
        ]);
        $viewerUser = UserFactory::createOne([
            'account' => $account,
            'roles' => [Roles::ROLE_USER],
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
            'code' => 'SYS-173',
            'manufacturer' => 'Tecniplast',
            'model' => 'ZebTEC Active Blue',
            'room' => $room,
            'account' => $account,
        ]);
        $rack = RackFactory::createOne([
            'code' => 'RACK-173',
            'room' => $room,
            'system' => $system,
            'account' => $account,
        ]);
        $availablePool = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::AVAILABLE,
            'account' => $account,
        ]);
        $occupiedPool = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A2',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $line = ZebrafishLineFactory::createOne([
            'name' => 'Tg(permission:line)',
            'genotype' => 'Tg(permission:line)zf410',
            'purpose' => 'Permission test line',
            'account' => $account,
        ]);
        $batch = ZebrafishBatchFactory::createOne([
            'line' => $line,
            'pool' => $occupiedPool,
            'account' => $account,
            'birthDate' => new \DateTimeImmutable('2024-03-05'),
            'maleCount' => 10,
            'femaleCount' => 10,
            'unknownSexCount' => 0,
            'totalPopulation' => 20,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
        ]);

        $requestAsViewer = function (string $method, string $uri, array $options = []) use ($viewerUser) {
            $client = static::createClient();
            $client->loginUser($viewerUser);

            return $client->request($method, $uri, $options);
        };

        $requestAsAdmin = function (string $method, string $uri, array $options = []) use ($adminUser) {
            $client = static::createClient();
            $client->loginUser($adminUser);

            return $client->request($method, $uri, $options);
        };

        foreach (['/zefix/overview', '/zefix/lines', '/zefix/batches', '/zefix/location-explorer', '/zefix/alerts'] as $uri) {
            $viewerResponse = $requestAsViewer('GET', $uri, [
                'headers' => ['Accept' => 'application/json'],
            ]);
            self::assertResponseIsSuccessful();
            self::assertNotEmpty($viewerResponse->getContent(false));
        }

        $viewerExportResponse = $requestAsViewer('GET', '/zefix/exports/line-statistics.csv', [
            'headers' => ['Accept' => 'text/csv'],
        ]);
        self::assertSame(Response::HTTP_FORBIDDEN, $viewerExportResponse->getStatusCode());

        $viewerReportResponse = $requestAsViewer('GET', '/zefix/reports/lines/' . ($line->getId()?->toRfc4122() ?? '') . '.pdf', [
            'headers' => ['Accept' => 'application/pdf'],
        ]);
        self::assertSame(Response::HTTP_FORBIDDEN, $viewerReportResponse->getStatusCode());

        $viewerObservationResponse = $requestAsViewer('POST', '/zefix/environmental-observations', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json' => [
                'scopeType' => 'system',
                'scopeID' => 'SYS-173',
                'metric' => EnvironmentalMetric::PH->value,
                'value' => 7.8,
                'timestamp' => '2026-04-01T10:00:00+00:00',
                'source' => EnvironmentalObservationSource::MANUAL->value,
            ],
        ]);
        self::assertSame(Response::HTTP_FORBIDDEN, $viewerObservationResponse->getStatusCode());

        $viewerBatchResponse = $requestAsViewer('POST', '/zebrafish_batches.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'line' => '/zebrafish_lines/' . ($line->getId()?->toRfc4122() ?? ''),
                'pool' => '/pools/' . ($availablePool->getId()?->toRfc4122() ?? ''),
                'birthDate' => '2024-04-01',
                'maleCount' => 8,
                'femaleCount' => 9,
                'unknownSexCount' => 1,
                'totalPopulation' => 18,
                'lifecycleStatus' => '/zebrafish_batch_lifecycle_statuses/active',
            ],
        ]);
        self::assertSame(Response::HTTP_FORBIDDEN, $viewerBatchResponse->getStatusCode());

        $viewerMortalityResponse = $requestAsViewer('POST', '/mortality_records.jsonld', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'batch' => '/zebrafish_batches/' . ($batch->getId()?->toRfc4122() ?? ''),
                'date' => '2024-04-01',
                'count' => 1,
                'reason' => '/mortality_reasons/' . MortalityReason::NATURAL->value,
                'notes' => 'Viewer should not be allowed to persist mortality.',
            ],
        ]);
        self::assertSame(Response::HTTP_FORBIDDEN, $viewerMortalityResponse->getStatusCode());

        $viewerAcknowledgeResponse = $requestAsViewer('POST', '/zefix/alerts/00000000-0000-0000-0000-000000000000/acknowledge', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json' => new \stdClass(),
        ]);
        self::assertSame(Response::HTTP_FORBIDDEN, $viewerAcknowledgeResponse->getStatusCode());

        $viewerResolveResponse = $requestAsViewer('POST', '/zefix/alerts/00000000-0000-0000-0000-000000000000/resolve', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json' => new \stdClass(),
        ]);
        self::assertSame(Response::HTTP_FORBIDDEN, $viewerResolveResponse->getStatusCode());

        $adminObservationResponse = $requestAsAdmin('POST', '/zefix/environmental-observations', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json' => [
                'scopeType' => 'system',
                'scopeID' => 'SYS-173',
                'metric' => EnvironmentalMetric::TEMPERATURE->value,
                'value' => 30.4,
                'timestamp' => '2026-04-01T10:30:00+00:00',
                'source' => EnvironmentalObservationSource::MANUAL->value,
                'notes' => 'Admin observation should persist.',
            ],
        ]);
        self::assertSame(Response::HTTP_CREATED, $adminObservationResponse->getStatusCode());

        $adminExportResponse = $requestAsAdmin('GET', '/zefix/exports/line-statistics.csv', [
            'headers' => ['Accept' => 'text/csv'],
        ]);
        self::assertSame(Response::HTTP_OK, $adminExportResponse->getStatusCode());
        self::assertStringContainsString('Tg(permission:line)', $adminExportResponse->getContent(false));

        $activeAlertsResponse = $requestAsAdmin('GET', '/zefix/alerts?status=active', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $activeAlertsResponse->toArray());
        $alertID = $activeAlertsResponse->toArray()[0]['alertID'];

        $adminAcknowledgeResponse = $requestAsAdmin('POST', sprintf('/zefix/alerts/%s/acknowledge', $alertID), [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json' => new \stdClass(),
        ]);
        self::assertSame(Response::HTTP_CREATED, $adminAcknowledgeResponse->getStatusCode());

        $adminReportResponse = $requestAsAdmin('GET', '/zefix/reports/lines/' . ($line->getId()?->toRfc4122() ?? '') . '.pdf', [
            'headers' => ['Accept' => 'application/pdf'],
        ]);
        self::assertSame(Response::HTTP_OK, $adminReportResponse->getStatusCode());
        self::assertStringStartsWith('%PDF-1.4', $adminReportResponse->getContent(false));
    }
}
