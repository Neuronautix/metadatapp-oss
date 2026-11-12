<?php

declare(strict_types=1);

namespace App\Tests\Functional\ConnectedApps\Tecniplast;

use App\ConnectedApps\Apps\Tecniplast\Import\DvcImportService;
use App\DataFixtures\Factory\CageFactory;
use App\DataFixtures\Factory\ConnectedAppFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\ProjectFactory;
use App\DataFixtures\Factory\StrainFactory;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\VariableFactory;
use App\Entity\CageHcmMeasure;
use App\Entity\TimeSeries;
use App\Entity\Variable;
use App\Enum\AppCode;
use App\Enum\HealthStatus;
use App\Enum\Sex;
use App\Enum\Species;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DvcImportServiceTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function itImportsDvcZipFixtureIntoExperimentLinkedMetadata(): void
    {
        $user = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
        ]);
        $account = $user->getAccount();
        $project = ProjectFactory::createOne([
            'account' => $account,
            'user' => $user,
        ]);
        $experiment = ExperimentFactory::createOne([
            'account' => $account,
            'user' => $user,
            'project' => $project,
        ]);
        $connectedApp = ConnectedAppFactory::createOne([
            'code' => AppCode::Tecniplast,
            'name' => 'Tecniplast DVC',
            'isActive' => true,
            'account' => $account,
            'user' => $user,
        ]);

        $cage = CageFactory::createOne([
            'account' => $account,
            'externalId' => 'DVC-CAGE-IMPORT-1',
            'homeCageMonitoring' => null,
        ]);
        $strain = StrainFactory::createOne(['account' => $account]);
        SubjectFactory::createOne([
            'account' => $account,
            'cage' => $cage,
            'strain' => $strain,
            'name' => 'SM-IMPORT-0001',
            'species' => Species::Mouse,
            'sex' => Sex::Male,
            'healthStatus' => HealthStatus::Healthy,
            'isPregnant' => false,
        ]);

        $existingVariable = VariableFactory::createOne([
            'account' => $account,
            'name' => 'DVC activity count',
            'tecniplastExternalId' => 'DVC_METRIC_ACTIVITY',
        ]);

        $zipPath = $this->buildFixtureArchive();

        /** @var DvcImportService $importService */
        $importService = self::getContainer()->get(DvcImportService::class);
        $result = $importService->importFromZip($zipPath, $experiment, $connectedApp, 26099);

        @unlink($zipPath);

        self::assertSame(3, $result->processedRows);
        self::assertSame(2, $result->importedRows);
        self::assertSame(1, $result->skippedRows);

        $entityManager = self::getContainer()->get('doctrine')->getManager();

        $series = $entityManager->getRepository(TimeSeries::class)->findBy(['experiment' => $experiment]);
        self::assertCount(2, $series);
        foreach ($series as $point) {
            self::assertSame('26099', $point->getSourceTaskId());
            self::assertSame(AppCode::Tecniplast->value, $point->getSourceAppCode());
        }

        $measures = $entityManager->getRepository(CageHcmMeasure::class)->findBy(['cage' => $cage]);
        self::assertCount(2, $measures);
        foreach ($measures as $measure) {
            self::assertSame('26099', $measure->getSourceTaskId());
            self::assertSame(AppCode::Tecniplast->value, $measure->getSourceAppCode());
            self::assertNotNull($measure->getHomeCageMonitoring());
            self::assertSame((string) $experiment->getId(), (string) $measure->getHomeCageMonitoring()->getExperiment()->getId());
        }

        $activityVariable = $entityManager->getRepository(Variable::class)->findOneBy([
            'account' => $account,
            'tecniplastExternalId' => 'DVC_METRIC_ACTIVITY',
        ]);
        self::assertNotNull($activityVariable);
        self::assertSame((string) $existingVariable->getId(), (string) $activityVariable->getId());

        $foodVariable = $entityManager->getRepository(Variable::class)->findOneBy([
            'account' => $account,
            'tecniplastExternalId' => 'DVC_METRIC_FOOD',
        ]);
        self::assertNotNull($foodVariable);
        self::assertSame('DVC food intake', $foodVariable->getName());
    }

    private function buildFixtureArchive(): string
    {
        $fixturePath = __DIR__ . '/../../../Fixtures/dvc/dvc-import-fixture.txt';
        $csv = file_get_contents($fixturePath);
        self::assertNotFalse($csv);

        $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dvc-import-' . uniqid('', true) . '.zip';
        $archive = new \ZipArchive();
        $openResult = $archive->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        self::assertTrue(true === $openResult, 'Unable to create fixture ZIP archive.');

        $archive->addFromString('dvc-import.csv', (string) $csv);
        $archive->close();

        return $zipPath;
    }
}
