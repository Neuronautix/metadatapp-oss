<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Fair3r\Synchronizer;

use App\ConnectedApps\Apps\Fair3r\Client\Client;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreCreateRequestDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreCreateResponseDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreResourceDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreUpsertRequestDto;
use App\ConnectedApps\Apps\Fair3r\Client\Resources\Datastores;
use App\ConnectedApps\Apps\Fair3r\Mapper\ExperimentMapper;
use App\ConnectedApps\Apps\Fair3r\Synchronizer\ExperimentSynchronizer;
use App\Entity\Experiment;
use App\Entity\Project;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ExperimentSynchronizerTest extends TestCase
{
    private Client $client;
    private Datastores $datastores;
    private ExperimentMapper $mapper;
    private LoggerInterface $logger;
    private ExperimentSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->datastores = $this->createMock(Datastores::class);
        $this->mapper = $this->createMock(ExperimentMapper::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->synchronizer = new ExperimentSynchronizer(
            $this->client,
            $this->mapper,
            $this->logger
        );
    }

    #[Test]
    public function syncExperimentToDatastoreForProject(): void
    {
        $project = $this->createMock(Project::class);
        $experiment = new Experiment();
        // Since we are mocking ExperimentMapper, we need to ensure the experiment produces a DTO
        // We will mock mapToExternal to handle this.

        $project->expects($this->once())
            ->method('getExperiments')
            ->willReturn(new ArrayCollection([$experiment]))
        ;

        // Setup expectations for syncOneExperimentToDatastore logic which is called inside
        // Since syncOneExperimentToDatastore is public, maybe we should test it separately,
        // but here we are testing that syncExperimentToDatastoreForProject iterates correctly.

        // Let's assume syncOneExperimentToDatastore logic:
        $requestDto = $this->createMock(DatastoreCreateRequestDto::class);
        $requestDto->records = []; // Public property

        $this->mapper->expects($this->once())
            ->method('mapToExternal')
            ->with($experiment)
            ->willReturn($requestDto)
        ;

        $this->client->expects($this->once())
            ->method('datastores')
            ->willReturn($this->datastores)
        ;

        $responseDto = new DatastoreCreateResponseDto(
            new DatastoreResourceDto('pkg-id', 'url', 'name', 'format'),
            'new-id',
            'insert',
            []
        );

        $this->datastores->expects($this->once())
            ->method('create')
            ->with($requestDto)
            ->willReturn($responseDto)
        ;

        $this->synchronizer->syncExperimentToDatastoreForProject($project);
    }

    #[Test]
    public function syncOneExperimentToDatastoreLogsErrorOnMappingFailure(): void
    {
        $experiment = new Experiment();
        $exception = new \RuntimeException('Map Error');

        $this->mapper->expects($this->once())
            ->method('mapToExternal')
            ->with($experiment)
            ->willThrowException($exception)
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Mapping Experiment to DatastoreCreateRequestDto failure', ['error' => 'Map Error'])
        ;

        $this->synchronizer->syncOneExperimentToDatastore($experiment);
    }

    #[Test]
    public function syncOneExperimentToDatastoreCreatesDatastoreWhenNoExternalId(): void
    {
        $experiment = new Experiment(); // no external id
        $requestDto = $this->createMock(DatastoreCreateRequestDto::class);
        $requestDto->records = [];

        $this->mapper->expects($this->once())
            ->method('mapToExternal')
            ->with($experiment)
            ->willReturn($requestDto)
        ;

        $this->client->expects($this->once())
            ->method('datastores')
            ->willReturn($this->datastores)
        ;

        $responseDto = new DatastoreCreateResponseDto(
            new DatastoreResourceDto('pkg-id', 'url', 'name', 'format'),
            'new-external-id',
            'insert',
            []
        );

        $this->datastores->expects($this->once())
            ->method('create')
            ->with($requestDto)
            ->willReturn($responseDto)
        ;

        $this->synchronizer->syncOneExperimentToDatastore($experiment);

        $this->assertSame('new-external-id', $experiment->getFair3rExternalId());
    }

    #[Test]
    public function syncOneExperimentToDatastoreUpsertsDatastoreWhenExternalIdExists(): void
    {
        $experiment = new Experiment();
        $experiment->setFair3rExternalId('existing-id');

        $requestDto = $this->createMock(DatastoreUpsertRequestDto::class);
        $requestDto->records = [];

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

        $this->synchronizer->syncOneExperimentToDatastore($experiment);
    }
}
