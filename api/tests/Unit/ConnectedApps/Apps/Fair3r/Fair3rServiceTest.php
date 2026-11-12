<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Fair3r;

use App\ConnectedApps\Apps\Fair3r\Fair3rService;
use App\ConnectedApps\Apps\Fair3r\Synchronizer\ExperimentSynchronizer;
use App\ConnectedApps\Apps\Fair3r\Synchronizer\ProjectSynchronizer;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AppCode;
use App\Repository\ExperimentRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class Fair3rServiceTest extends TestCase
{
    private ExperimentSynchronizer $experimentSynchronizer;
    private ProjectSynchronizer $projectSynchronizer;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private ProjectRepository $projectRepository;
    private ExperimentRepository $experimentRepository;
    private Fair3rService $service;

    protected function setUp(): void
    {
        $this->experimentSynchronizer = $this->createMock(ExperimentSynchronizer::class);
        $this->projectSynchronizer = $this->createMock(ProjectSynchronizer::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->experimentRepository = $this->createMock(ExperimentRepository::class);

        $this->service = new Fair3rService(
            $this->experimentSynchronizer,
            $this->projectSynchronizer,
            $this->em,
            $this->logger,
            $this->projectRepository,
            $this->experimentRepository
        );
    }

    #[Test]
    public function supportsCode(): void
    {
        $this->assertTrue($this->service->supportsCode(AppCode::Fair3r));
        $this->assertFalse($this->service->supportsCode(AppCode::Elabftw));
    }

    #[Test]
    public function supportsEntitySyncTo(): void
    {
        $this->assertTrue($this->service->supportsEntitySyncTo(Experiment::class));
        // Project::class is not in SUPPORTED_SYNC_TO_ENTITIES array but is handled in syncOneToExternal logic.
        // Let's check logic:
        // private const array SUPPORTED_SYNC_TO_ENTITIES = [Experiment::class];
        // So supportsEntitySyncTo(Project::class) returns false.
        $this->assertFalse($this->service->supportsEntitySyncTo(Project::class));
        $this->assertFalse($this->service->supportsEntitySyncTo(User::class));
    }

    #[Test]
    public function supportsEntitySyncFrom(): void
    {
        $this->assertFalse($this->service->supportsEntitySyncFrom(Experiment::class));
    }

    #[Test]
    public function syncOneToExternalProject(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();

        $this->projectSynchronizer->expects($this->once())
            ->method('syncProjectToDataset')
            ->with($connectedApp, $uuid)
        ;

        $this->em->expects($this->once())->method('flush');

        $this->service->syncOneToExternal($connectedApp, Project::class, $uuid);
    }

    #[Test]
    public function syncOneToExternalExperiment(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();
        $experiment = $this->createMock(Experiment::class);

        $this->experimentRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($experiment)
        ;

        $this->experimentSynchronizer->expects($this->once())
            ->method('syncOneExperimentToDatastore')
            ->with($experiment)
        ;

        $this->em->expects($this->once())->method('flush');

        $this->service->syncOneToExternal($connectedApp, Experiment::class, $uuid);
    }

    #[Test]
    public function syncOneToExternalExperimentNotFound(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();

        $this->experimentRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn(null)
        ;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Experiment with ID %s not found.', $uuid->toRfc4122()));

        $this->service->syncOneToExternal($connectedApp, Experiment::class, $uuid);
    }

    #[Test]
    public function syncOneToExternalUnsupportedEntity(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity class App\Entity\User is not supported for synchronization to Fair3R.');

        $this->service->syncOneToExternal($connectedApp, User::class, $uuid);
    }

    #[Test]
    public function syncAllToExternal(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $user = $this->createMock(User::class);
        $connectedApp->method('getUser')->willReturn($user);

        $project1 = $this->createMock(Project::class);
        $project1->method('getId')->willReturn(Uuid::v4());
        $project2 = $this->createMock(Project::class);
        $project2->method('getId')->willReturn(Uuid::v4());

        $projects = [$project1, $project2];

        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->projectRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('p')
            ->willReturn($qb)
        ;

        $qb->expects($this->once())->method('where')->with('p.user = :user')->willReturnSelf();
        $qb->expects($this->once())->method('andWhere')->with('p.fair3rExternalId IS NOT NULL')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('user', $user)->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($projects)
        ;

        $this->experimentSynchronizer->expects($this->exactly(2))
            ->method('syncExperimentToDatastoreForProject')
        ;

        $this->em->expects($this->exactly(2))->method('flush');

        $this->service->syncAllToExternal($connectedApp, Experiment::class);
    }
}
