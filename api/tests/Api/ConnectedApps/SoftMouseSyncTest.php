<?php

declare(strict_types=1);

namespace App\Tests\Api\ConnectedApps;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\ConnectedApps\Apps\SoftMouse\Client\ClientInterface;
use App\ConnectedApps\Apps\SoftMouse\SoftMouseService;
use App\DataFixtures\Factory\ConnectedAppFactory;
use App\DataFixtures\Factory\ProjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Cage;
use App\Entity\Experiment;
use App\Entity\Subject;
use App\Enum\AppCode;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Integration tests for SoftMouse synchronization functionality.
 * Tests the complete sync flow from SoftMouse API to MAPP entities.
 */
class SoftMouseSyncTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    private SoftMouseService $softMouseService;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->softMouseService = static::getContainer()->get(SoftMouseService::class);
    }

    #[Test]
    public function softMouseClientCanFetchAnimals(): void
    {
        /** @var ClientInterface $client */
        $client = static::getContainer()->get(ClientInterface::class);

        $animals = $client->animals()->all();

        $this->assertNotEmpty($animals);
        $this->assertIsArray($animals);
        $this->assertGreaterThanOrEqual(10, \count($animals), 'Should have at least 10 animals from mock');

        // Verify animal data structure
        $firstAnimal = $animals[0];
        $this->assertNotNull($firstAnimal->guiSid);
        $this->assertNotNull($firstAnimal->physicalTag);
        $this->assertNotNull($firstAnimal->strain);
    }

    #[Test]
    public function softMouseClientCanFetchCages(): void
    {
        /** @var ClientInterface $client */
        $client = static::getContainer()->get(ClientInterface::class);

        $cages = $client->cages()->all();

        $this->assertNotEmpty($cages);
        $this->assertIsArray($cages);
        $this->assertGreaterThanOrEqual(3, \count($cages), 'Should have at least 3 cages from mock');

        // Verify cage data structure
        $firstCage = $cages[0];
        $this->assertNotNull($firstCage->cageSid);
        $this->assertNotNull($firstCage->cageTag);
    }

    #[Test]
    public function softMouseClientCanFetchStudies(): void
    {
        /** @var ClientInterface $client */
        $client = static::getContainer()->get(ClientInterface::class);

        $studies = $client->studies()->all();

        $this->assertNotEmpty($studies);
        $this->assertIsArray($studies);
        $this->assertGreaterThanOrEqual(5, \count($studies), 'Should have at least 5 studies from mock');

        // Verify study data structure
        $firstStudy = $studies[0];
        $this->assertNotNull($firstStudy->code);
        $this->assertNotNull($firstStudy->title);
    }

    #[Test]
    public function softMouseClientCanFetchProtocols(): void
    {
        /** @var ClientInterface $client */
        $client = static::getContainer()->get(ClientInterface::class);

        $protocols = $client->protocols()->all();

        $this->assertNotEmpty($protocols);
        $this->assertIsArray($protocols);
        $this->assertGreaterThanOrEqual(5, \count($protocols), 'Should have at least 5 protocols from mock');

        // Verify protocol data structure
        $firstProtocol = $protocols[0];
        $this->assertNotNull($firstProtocol->name);
        $this->assertNotNull($firstProtocol->title);
    }

    #[Test]
    public function fullSynchronizationCreatesSubjectsFromSoftMouse(): void
    {
        $user = UserFactory::createOne(['email' => 'doctor.yiolyo@mdta.net']);
        $connectedApp = ConnectedAppFactory::createOne([
            'code' => AppCode::SoftMouse,
            'name' => 'SoftMouse Test App',
            'isActive' => true,
            'account' => $user->getAccount(),
            'user' => $user,
        ]);

        // Get count before sync
        $subjectRepo = static::getContainer()->get('doctrine')->getRepository(Subject::class);
        $countBefore = \count($subjectRepo->findAll());

        // Run synchronization
        $this->softMouseService->sync($connectedApp);

        // Verify subjects were created
        $countAfter = \count($subjectRepo->findAll());
        $this->assertGreaterThan($countBefore, $countAfter, 'Should have created new subjects');

        // Verify subject has SoftMouse external ID
        $subjects = $subjectRepo->findAll();
        $subjectsWithExternalId = array_filter($subjects, static fn ($s) => null !== $s->getSoftmouseExternalId());
        $this->assertNotEmpty($subjectsWithExternalId, 'Subjects should have SoftMouse external IDs');
    }

    #[Test]
    public function fullSynchronizationCreatesCagesFromSoftMouse(): void
    {
        $user = UserFactory::createOne(['email' => 'doctor.yiolyo@mdta.net']);
        $connectedApp = ConnectedAppFactory::createOne([
            'code' => AppCode::SoftMouse,
            'name' => 'SoftMouse Test App',
            'isActive' => true,
            'account' => $user->getAccount(),
            'user' => $user,
        ]);

        // Get count before sync
        $cageRepo = static::getContainer()->get('doctrine')->getRepository(Cage::class);
        $countBefore = \count($cageRepo->findAll());

        // Run synchronization
        $this->softMouseService->sync($connectedApp);

        // Verify cages were created
        $countAfter = \count($cageRepo->findAll());
        $this->assertGreaterThan($countBefore, $countAfter, 'Should have created new cages');

        // Verify cage has SoftMouse external ID
        $cages = $cageRepo->findAll();
        $cagesWithExternalId = array_filter($cages, static fn ($c) => null !== $c->getSoftmouseExternalId());
        $this->assertNotEmpty($cagesWithExternalId, 'Cages should have SoftMouse external IDs');
    }

    #[Test]
    public function fullSynchronizationCreatesExperimentsFromSoftMouse(): void
    {
        $user = UserFactory::createOne(['email' => 'doctor.yiolyo@mdta.net']);
        $connectedApp = ConnectedAppFactory::createOne([
            'code' => AppCode::SoftMouse,
            'name' => 'SoftMouse Test App',
            'isActive' => true,
            'account' => $user->getAccount(),
            'user' => $user,
        ]);

        // Create a project to associate experiments with
        ProjectFactory::createOne([
            'account' => $user->getAccount(),
            'user' => $user,
        ]);

        // Get count before sync
        $experimentRepo = static::getContainer()->get('doctrine')->getRepository(Experiment::class);
        $countBefore = \count($experimentRepo->findAll());

        // Run synchronization
        $this->softMouseService->sync($connectedApp);

        // Verify experiments were created
        $countAfter = \count($experimentRepo->findAll());
        $this->assertGreaterThan($countBefore, $countAfter, 'Should have created new experiments');

        // Verify experiment has SoftMouse external ID
        $experiments = $experimentRepo->findAll();
        $experimentsWithExternalId = array_filter($experiments, static fn ($e) => null !== $e->getSoftmouseExternalId());
        $this->assertNotEmpty($experimentsWithExternalId, 'Experiments should have SoftMouse external IDs');
    }

    #[Test]
    public function synchronizationUpdatesLastSyncTime(): void
    {
        $user = UserFactory::createOne(['email' => 'doctor.yiolyo@mdta.net']);
        $connectedApp = ConnectedAppFactory::createOne([
            'code' => AppCode::SoftMouse,
            'name' => 'SoftMouse Test App',
            'isActive' => true,
            'lastSyncAt' => null,
            'account' => $user->getAccount(),
            'user' => $user,
        ]);

        $this->assertNull($connectedApp->getLastSyncAt());

        // Run synchronization
        $this->softMouseService->sync($connectedApp);

        // Force refresh from database
        static::getContainer()->get('doctrine')->getManager()->refresh($connectedApp);

        $this->assertNotNull($connectedApp->getLastSyncAt(), 'Last sync time should be updated after sync');
    }

    #[Test]
    public function synchronizationWithDiverseAnimalStrains(): void
    {
        $user = UserFactory::createOne(['email' => 'doctor.yiolyo@mdta.net']);
        $connectedApp = ConnectedAppFactory::createOne([
            'code' => AppCode::SoftMouse,
            'name' => 'SoftMouse Test App',
            'isActive' => true,
            'account' => $user->getAccount(),
            'user' => $user,
        ]);

        // Run synchronization
        $this->softMouseService->sync($connectedApp);

        // Verify subjects with different strains were created
        $subjectRepo = static::getContainer()->get('doctrine')->getRepository(Subject::class);
        $subjects = $subjectRepo->findAll();

        // The mock data includes multiple strains: C57BL/6J, Shank3, Ang1-A, BTBR, FVB/N, 129S1/SvImJ
        $this->assertGreaterThanOrEqual(5, \count($subjects), 'Should have subjects from diverse strains');
    }
}
