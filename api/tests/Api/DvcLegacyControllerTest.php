<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\CageFactory;
use App\DataFixtures\Factory\CageHcmMeasureFactory;
use App\DataFixtures\Factory\CageHcmSinusoidMetadataFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\HomeCageMonitoringFactory;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\TimeSeriesFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\VariableFactory;
use App\Enum\HcmDataType;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DvcLegacyControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function namDatasetsRouteReturnsDatasetEnvelopeOnTheBackendRouteReachedByTheOsomaProxy(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/nam/datasets');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertArrayHasKey('data', $payload);
        $this->assertNotEmpty($payload['data']);
    }

    #[Test]
    public function dvcActivityReturnsLinkedTimeSeriesForExternalCageId(): void
    {
        $account = AccountFactory::createOne();
        $user = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => $account,
        ]);
        $cage = CageFactory::createOne([
            'externalId' => 'DVC-CAGE-TEST-1',
            'account' => $account,
        ]);
        $subject = SubjectFactory::createOne([
            'cage' => $cage,
            'account' => $account,
        ]);
        $experiment = ExperimentFactory::createOne([
            'user' => $user,
            'account' => $account,
        ]);
        $variable = VariableFactory::createOne([
            'name' => 'DVC activity count',
            'account' => $account,
        ]);
        TimeSeriesFactory::createOne([
            'subject' => $subject,
            'experiment' => $experiment,
            'variable' => $variable,
            'recordedAt' => new \DateTime('2026-03-03 06:00:00'),
            'value' => '42.00000',
            'unit' => 'count/6h',
            'account' => $account,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/api/dvc/cages/DVC-CAGE-TEST-1/activity');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame('DVC-CAGE-TEST-1', $payload['cageId']);
        $this->assertEquals([42.0], $payload['activity']);
        $this->assertCount(1, $payload['ts']);
        $this->assertStringStartsWith('2026-03-03T06:00:00', $payload['ts'][0]);
    }

    #[Test]
    public function dvcContextEnforcesTenantIsolation(): void
    {
        $ownerAccount = AccountFactory::createOne();
        $owner = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => $ownerAccount,
        ]);
        CageFactory::createOne([
            'externalId' => 'DVC-CAGE-OTHER-ACCOUNT',
            'account' => $ownerAccount,
        ]);
        $otherAccount = AccountFactory::createOne();
        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => $otherAccount,
        ]);

        $client = static::createClient();
        $client->loginUser($otherUser);
        $client->request('GET', '/api/dvc/cages/DVC-CAGE-OTHER-ACCOUNT/context-snapshot');

        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function dvcCircadianReturnsMetricsForConfiguredLightSchedule(): void
    {
        $account = AccountFactory::createOne();
        $user = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => $account,
        ]);
        $hcm = HomeCageMonitoringFactory::createOne([
            'account' => $account,
            'lightsOnUtcHour' => 7,
            'lightDurationHours' => 12,
        ]);
        $cage = CageFactory::createOne([
            'externalId' => 'DVC-CAGE-CIRCADIAN',
            'account' => $account,
            'homeCageMonitoring' => $hcm,
        ]);
        $subject = SubjectFactory::createOne([
            'cage' => $cage,
            'account' => $account,
        ]);
        $experiment = ExperimentFactory::createOne([
            'user' => $user,
            'account' => $account,
        ]);
        $variable = VariableFactory::createOne([
            'name' => 'DVC activity count',
            'account' => $account,
        ]);

        // Three data points at different UTC hours to create measurable amplitude
        foreach ([
            ['hour' => 8, 'value' => '10.0'],   // ZT 1  (day phase)
            ['hour' => 20, 'value' => '80.0'],   // ZT 13 (night phase — peak)
            ['hour' => 23, 'value' => '50.0'],   // ZT 16 (night phase)
        ] as $point) {
            TimeSeriesFactory::createOne([
                'subject' => $subject,
                'experiment' => $experiment,
                'variable' => $variable,
                'recordedAt' => new \DateTime(sprintf('2026-03-03 %02d:00:00 UTC', $point['hour'])),
                'value' => $point['value'],
                'unit' => 'count/h',
                'account' => $account,
            ]);
        }

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/api/dvc/cages/DVC-CAGE-CIRCADIAN/circadian', [
            'query' => ['from' => '2026-03-03T00:00:00Z', 'to' => '2026-03-03T23:59:59Z'],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame('DVC-CAGE-CIRCADIAN', $payload['cageId']);
        $this->assertArrayHasKey('metrics', $payload);

        $metrics = $payload['metrics'];
        // Night phase should be the peak; acrophaseUtcHour = 20
        $this->assertSame(20, $metrics['acrophaseUtcHour']);
        // ZT = (20 - 7 + 24) % 24 = 13
        $this->assertSame(13, $metrics['acrophaseZeitgeber']);
        // Amplitude = max(80,50,10) - min(10,50,80) = 70.0
        $this->assertEquals(70.0, $metrics['amplitude']);
        // Day/Night ratio: day mean = 10 (ZT 1), night mean = (80+50)/2 = 65 → ratio = 10/65 ≈ 0.1538
        $this->assertNotNull($metrics['dayNightRatio']);
        $this->assertLessThan(1.0, $metrics['dayNightRatio']);
        $this->assertSame(3, $metrics['dataPointCount']);
        $this->assertSame(7, $metrics['lightsOnUtcHour']);
        $this->assertSame(12, $metrics['lightDurationHours']);
    }

    #[Test]
    public function dvcCircadianReturnsEmptyMetricsForCageWithNoTimeSeries(): void
    {
        $account = AccountFactory::createOne();
        $user = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => $account,
        ]);
        CageFactory::createOne([
            'externalId' => 'DVC-CAGE-EMPTY-CIRCADIAN',
            'account' => $account,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/api/dvc/cages/DVC-CAGE-EMPTY-CIRCADIAN/circadian');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $metrics = $payload['metrics'];
        $this->assertNull($metrics['acrophaseUtcHour']);
        $this->assertEquals(0.0, $metrics['amplitude']);
        $this->assertSame(0, $metrics['dataPointCount']);
    }

    #[Test]
    public function hcmSystemsSummaryReturnsSinusoidMetadataAcrossSystems(): void
    {
        $account = AccountFactory::createOne();
        $user = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => $account,
        ]);
        $experiment = ExperimentFactory::createOne([
            'user' => $user,
            'account' => $account,
            'protocol' => 'HCM benchmark: RFID occupancy, video tracking distance, social proximity',
        ]);
        $hcm = HomeCageMonitoringFactory::createOne([
            'experiment' => $experiment,
            'system' => 'Live Mouse Tracker',
            'account' => $account,
            'lightsOnUtcHour' => 6,
            'lightDurationHours' => 12,
        ]);
        $cage = CageFactory::createOne([
            'externalId' => 'LMT-CAGE-TEST',
            'account' => $account,
            'homeCageMonitoring' => $hcm,
        ]);
        SubjectFactory::createOne([
            'name' => 'LMT-MOUSE-001',
            'externalId' => 'LMT-RFID-0001',
            'cage' => $cage,
            'account' => $account,
        ]);
        CageHcmSinusoidMetadataFactory::createOne([
            'cage' => $cage,
            'homeCageMonitoring' => $hcm,
            'account' => $account,
            'date' => new \DateTime('2026-03-03'),
            'amplitude' => 22.7,
            'mesor' => 47.0,
            'phase' => 1.92,
            'peakHour' => 19.4,
            'sourceAppCode' => 'live_mouse_tracker',
            'sourceTaskId' => 'LMT-TASK-001',
        ]);
        $variable = VariableFactory::createOne([
            'name' => 'Live Mouse Tracker RFID occupancy',
            'account' => $account,
        ]);
        CageHcmMeasureFactory::createOne([
            'cage' => $cage,
            'homeCageMonitoring' => $hcm,
            'variable' => $variable,
            'dataType' => HcmDataType::Activity,
            'value' => 31.0,
            'recordedAt' => new \DateTime('2026-03-03 12:00:00'),
            'account' => $account,
            'sourceAppCode' => 'live_mouse_tracker',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/api/hcm/systems/summary');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertCount(1, $payload['systems']);
        $this->assertSame('Live Mouse Tracker', $payload['systems'][0]['system']);
        $this->assertContains('RFID', $payload['systems'][0]['modalities']);
        $this->assertSame('LMT-CAGE-TEST', $payload['cages'][0]['cageId']);
        $this->assertSame('LMT-RFID-0001', $payload['cages'][0]['animals'][0]['animalId']);
        $this->assertSame(22.7, $payload['cages'][0]['animals'][0]['circadianParameters']['amplitude']);
        $this->assertSame('live_mouse_tracker', $payload['cages'][0]['animals'][0]['metadata']['sourceApp']);
    }
}
