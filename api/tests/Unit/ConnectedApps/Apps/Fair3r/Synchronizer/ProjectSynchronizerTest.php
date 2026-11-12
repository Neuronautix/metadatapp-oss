<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Fair3r\Synchronizer;

use App\ConnectedApps\Apps\Fair3r\Client\Client;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreCreateRequestDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreCreateResponseDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreResourceDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreUpsertRequestDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\ResourceRequestDto;
use App\ConnectedApps\Apps\Fair3r\Client\Resources\Datastores;
use App\ConnectedApps\Apps\Fair3r\Mapper\ExperimentMapper;
use App\ConnectedApps\Apps\Fair3r\Synchronizer\ProjectSynchronizer;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Repository\ExperimentRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ProjectSynchronizerTest extends TestCase
{
    private Client $client;
    private Datastores $datastores;
    private ExperimentRepository $experimentRepository;
    private ExperimentMapper $mapper;
    private LoggerInterface $logger;
    private ProjectSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->datastores = $this->createMock(Datastores::class);
        $this->experimentRepository = $this->createMock(ExperimentRepository::class);
        $this->mapper = $this->createMock(ExperimentMapper::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->synchronizer = new ProjectSynchronizer(
            $this->client,
            $this->experimentRepository,
            $this->mapper,
            $this->logger
        );
    }

    #[Test]
    public function syncProjectToDatasetThrowsExceptionIfExperimentNotFound(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();

        $this->experimentRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn(null)
        ;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Experiment with ID ' . $uuid->toRfc4122() . ' not found.');

        $this->synchronizer->syncProjectToDataset($connectedApp, $uuid);
    }

    #[Test]
    public function syncProjectToDatasetSkipsIfProjectExternalIdMissing(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();
        $experiment = $this->createMock(Experiment::class);
        $project = $this->createMock(Project::class);

        $this->experimentRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($experiment)
        ;

        $experiment->method('getId')->willReturn($uuid);
        $experiment->method('getProject')->willReturn($project);
        $project->method('getFair3rExternalId')->willReturn(null);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Experiment not linked to a Fair3R project, skipping synchronization')
        ;

        $this->synchronizer->syncProjectToDataset($connectedApp, $uuid);
    }

    #[Test]
    public function syncProjectToDatasetCreatesNewDatastore(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();
        $experiment = new Experiment();
        // Set properties as it's a real object mainly
        $project = new Project();
        $project->setFair3rExternalId('project-123');
        $experiment->setProject($project);

        $requestDto = new DatastoreCreateRequestDto(new ResourceRequestDto('pkg-id'), [], []);

        $responseDto = new DatastoreCreateResponseDto(
            new DatastoreResourceDto('pkg-id', 'http://url', 'name', 'format'),
            'new-resource-id',
            'insert',
            []
        );

        $this->experimentRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($experiment)
        ;

        $this->mapper->expects($this->once())
            ->method('mapToExternal')
            ->with($experiment)
            ->willReturn($requestDto)
        ;

        $this->client->expects($this->once())
            ->method('datastores')
            ->willReturn($this->datastores)
        ;

        $this->datastores->expects($this->once())
            ->method('create')
            ->with($requestDto)
            ->willReturn($responseDto)
        ;

        $this->synchronizer->syncProjectToDataset($connectedApp, $uuid);

        $this->assertSame('new-resource-id', $experiment->getFair3rExternalId());
    }

    #[Test]
    public function syncProjectToDatasetUpsertsDatastore(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();
        $experiment = new Experiment();
        $experiment->setFair3rExternalId('existing-id');
        $project = new Project();
        $project->setFair3rExternalId('project-123');
        $experiment->setProject($project);

        // When ID exists, mapper usually returns UpdateDTO but code checks generic structure
        // The code expects DatastoreUpsertRequestDto if ID exists
        $requestDto = new DatastoreUpsertRequestDto(null, 'resource-id', [], 'upsert');

        $this->experimentRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($experiment)
        ;

        $this->mapper->expects($this->once())
            ->method('mapToExternal')
            ->with($experiment)
            ->willReturn($requestDto)
        ;

        $this->client->expects($this->once())
            ->method('datastores')
            ->willReturn($this->datastores)
        ;

        $this->datastores->expects($this->once())
            ->method('upsert')
            ->with($requestDto)
        ;

        $this->synchronizer->syncProjectToDataset($connectedApp, $uuid);
    }
}
