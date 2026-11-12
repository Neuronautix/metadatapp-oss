<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\SoftMouse\Synchronizer;

use App\ConnectedApps\Apps\SoftMouse\Client\ClientInterface;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\StudyDataDto;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\StudyDto;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\Studies;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\StudyData;
use App\ConnectedApps\Apps\SoftMouse\Mapper\ExperimentMapper;
use App\ConnectedApps\Apps\SoftMouse\Synchronizer\ExperimentSynchronizer;
use App\Entity\Account;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ExperimentRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ExperimentSynchronizerTest extends TestCase
{
    private ClientInterface $client;
    private Studies $studiesResource;
    private StudyData $studyDataResource;
    private EntityManagerInterface $em;
    private ExperimentRepository $experimentRepository;
    private ProjectRepository $projectRepository;
    private ExperimentMapper $mapper;
    private LoggerInterface $logger;
    private ExperimentSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->studiesResource = $this->createMock(Studies::class);
        $this->studyDataResource = $this->createMock(StudyData::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->experimentRepository = $this->createMock(ExperimentRepository::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->mapper = $this->createMock(ExperimentMapper::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->synchronizer = new ExperimentSynchronizer(
            $this->client,
            $this->em,
            $this->experimentRepository,
            $this->projectRepository,
            $this->mapper,
            $this->logger
        );
    }

    #[Test]
    public function syncAllFromReturnsNullIfNoStudies(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);

        $this->client->expects($this->once())
            ->method('studies')
            ->willReturn($this->studiesResource)
        ;

        $this->studiesResource->expects($this->once())
            ->method('search')
            ->willReturn([])
        ;

        $result = $this->synchronizer->syncAllFrom($connectedApp);

        $this->assertNull($result);
    }

    #[Test]
    public function syncAllFromSyncsExperiment(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $account = $this->createMock(Account::class);
        $user = $this->createMock(User::class);
        $connectedApp->method('getAccount')->willReturn($account);
        $connectedApp->method('getUser')->willReturn($user);

        $studyDto = new StudyDto('code1', 'Title 1', 'Project 1');

        $studyDataDto = new StudyDataDto('Study Name', 1, 1, 'Task Name', 'TaskType', []);

        $this->client->expects($this->once())
            ->method('studies')
            ->willReturn($this->studiesResource)
        ;

        $this->studiesResource->expects($this->once())
            ->method('search')
            ->willReturn([$studyDto])
        ;

        $this->client->expects($this->once())
            ->method('studyData')
            ->willReturn($this->studyDataResource)
        ;

        $this->studyDataResource->expects($this->once())
            ->method('get')
            ->with('code1')
            ->willReturn($studyDataDto)
        ;

        $this->experimentRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['softmouseExternalId' => 'code1'])
            ->willReturn(null)
        ;

        $this->projectRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['softmouseExternalId' => 'Project 1'])
            ->willReturn(null)
        ;

        $this->em->expects($this->exactly(2))
            ->method('persist')
            ->with($this->logicalOr(
                $this->isInstanceOf(Experiment::class),
                $this->isInstanceOf(Project::class)
            ))
        ;

        $this->mapper->expects($this->once())
            ->method('mapFromExternal')
            ->willReturn(true)
        ;

        $result = $this->synchronizer->syncAllFrom($connectedApp);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Experiment::class, $result[0]);
    }

    #[Test]
    public function syncOneFromThrowsExceptionIfExperimentNotFound(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();

        $this->experimentRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn(null)
        ;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subject with ID ' . $uuid . ' not found.');

        $this->synchronizer->syncOneFrom($connectedApp, $uuid);
    }
}
