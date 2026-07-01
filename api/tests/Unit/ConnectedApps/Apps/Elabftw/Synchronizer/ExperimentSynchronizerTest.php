<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Elabftw\Synchronizer;

use App\ConnectedApps\Apps\Elabftw\Client\Client;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentCreateRequestDto;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentDto;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentUpdateRequestDto;
use App\ConnectedApps\Apps\Elabftw\Client\Resources\Experiments;
use App\ConnectedApps\Apps\Elabftw\Mapper\ExperimentMapper;
use App\ConnectedApps\Apps\Elabftw\Synchronizer\ExperimentSynchronizer;
use App\Entity\Account;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Entity\User;
use App\Repository\ExperimentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ExperimentSynchronizerTest extends TestCase
{
    private Client $client;
    private Experiments $experimentsResource;
    private ExperimentRepository $experimentRepository;
    private ExperimentMapper $mapper;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private ExperimentSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->experimentsResource = $this->createMock(Experiments::class);
        $this->experimentRepository = $this->createMock(ExperimentRepository::class);
        $this->mapper = $this->createMock(ExperimentMapper::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->synchronizer = new ExperimentSynchronizer(
            $this->client,
            $this->experimentRepository,
            $this->mapper,
            $this->em,
            $this->logger
        );
    }

    #[Test]
    public function syncOneToThrowsExceptionWhenExperimentNotFound(): void
    {
        $uuid = Uuid::v4();
        $connectedApp = $this->createMock(ConnectedApp::class);

        $this->experimentRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Experiment with ID ' . $uuid->toRfc4122() . ' not found.');

        $this->synchronizer->syncOneTo($connectedApp, $uuid);
    }

    #[Test]
    public function syncOneToCreatesExperimentWhenNoExternalId(): void
    {
        $uuid = Uuid::v4();
        $connectedApp = $this->createMock(ConnectedApp::class);
        $experiment = $this->createMock(Experiment::class);
        $createDto = $this->createMock(ExperimentCreateRequestDto::class);
        $resultDto = new ExperimentDto(123, 'Test Title', metadata: ['field' => 'value']);

        $experiment->method('getElaftwExternalId')->willReturn(null);

        $this->experimentRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($experiment);

        $this->mapper->expects($this->once())
            ->method('mapToExternal')
            ->with($experiment)
            ->willReturn($createDto);

        $this->client->expects($this->once())
            ->method('experiments')
            ->with($connectedApp)
            ->willReturn($this->experimentsResource);

        $this->experimentsResource->expects($this->once())
            ->method('create')
            ->with($createDto)
            ->willReturn($resultDto);

        $experiment->expects($this->once())->method('setElaftwExternalId')->with('123');
        $experiment->expects($this->once())->method('setElabftwMetadata')->with(['field' => 'value']);

        $this->synchronizer->syncOneTo($connectedApp, $uuid);
    }

    #[Test]
    public function syncOneToUpdatesExperimentWhenExternalIdExists(): void
    {
        $uuid = Uuid::v4();
        $connectedApp = $this->createMock(ConnectedApp::class);
        $experiment = $this->createMock(Experiment::class);
        $updateDto = $this->createMock(ExperimentUpdateRequestDto::class);
        $resultDto = new ExperimentDto(123, 'Test Title', metadata: ['updated' => true]);

        $experiment->method('getElaftwExternalId')->willReturn('123');

        $this->experimentRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($experiment);

        $this->mapper->expects($this->once())
            ->method('mapToExternal')
            ->with($experiment)
            ->willReturn($updateDto);

        $this->client->expects($this->once())
            ->method('experiments')
            ->with($connectedApp)
            ->willReturn($this->experimentsResource);

        $this->experimentsResource->expects($this->once())
            ->method('update')
            ->with(123, $updateDto)
            ->willReturn($resultDto);

        $experiment->expects($this->once())->method('setElabftwMetadata')->with(['updated' => true]);

        $this->synchronizer->syncOneTo($connectedApp, $uuid);
    }

    #[Test]
    public function syncAllToSyncsMultipleExperiments(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $user = $this->createMock(User::class);
        $connectedApp->method('getUser')->willReturn($user);

        $experiment1 = $this->createMock(Experiment::class);
        $experiment1->method('getElaftwExternalId')->willReturn(null);
        $experiment1->method('getId')->willReturn(Uuid::v4());

        $experiment2 = $this->createMock(Experiment::class);
        $experiment2->method('getElaftwExternalId')->willReturn('456');
        $experiment2->method('getId')->willReturn(Uuid::v4());

        $this->experimentRepository->expects($this->once())
            ->method('findBy')
            ->with(['user' => $user])
            ->willReturn([$experiment1, $experiment2]);

        $this->client->expects($this->exactly(2))
            ->method('experiments')
            ->with($connectedApp)
            ->willReturn($this->experimentsResource);

        $this->mapper->expects($this->exactly(2))
            ->method('mapToExternal')
            ->willReturnOnConsecutiveCalls(
                $this->createMock(ExperimentCreateRequestDto::class),
                $this->createMock(ExperimentUpdateRequestDto::class)
            );

        $this->experimentsResource->expects($this->once())
            ->method('create')
            ->willReturn(new ExperimentDto(123, 'Title 1'));
        $this->experimentsResource->expects($this->once())
            ->method('update')
            ->willReturn(new ExperimentDto(456, 'Title 2'));

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->with('Experiment synchronized to ElabFTW', $this->anything());

        $this->synchronizer->syncAllTo($connectedApp);
    }

    #[Test]
    public function syncOneFromSuccessfullySynchronizesExperiment(): void
    {
        $uuid = Uuid::v4();
        $connectedApp = $this->createMock(ConnectedApp::class);
        $experiment = $this->createMock(Experiment::class);
        $dto = new ExperimentDto(123, 'Title From eLab');

        $experiment->method('getId')->willReturn($uuid);
        $experiment->method('getElaftwExternalId')->willReturn('123');

        $this->experimentRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($experiment);

        $this->client->expects($this->once())
            ->method('experiments')
            ->with($connectedApp)
            ->willReturn($this->experimentsResource);

        $this->experimentsResource->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($dto);

        $this->mapper->expects($this->once())->method('mapToInternal')->with($dto, $experiment);
        $this->logger->expects($this->once())->method('info')->with('Experiment synchronized FROM ElabFTW', $this->anything());

        $this->synchronizer->syncOneFrom($connectedApp, $uuid);
    }

    #[Test]
    public function syncAllFromSynchronizesAllExperiments(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $user = $this->createMock(User::class);
        $account = $this->createMock(Account::class);
        $user->method('getAccount')->willReturn($account);
        $connectedApp->method('getUser')->willReturn($user);

        $dto1 = new ExperimentDto(1, 'Existing');
        $dto2 = new ExperimentDto(2, 'New');
        $experiment1 = $this->createMock(Experiment::class);

        $this->client->expects($this->once())
            ->method('experiments')
            ->with($connectedApp)
            ->willReturn($this->experimentsResource);

        $this->experimentsResource->expects($this->once())
            ->method('list')
            ->willReturn([$dto1, $dto2]);

        $this->experimentRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnMap([
                [['elaftwExternalId' => '1'], $experiment1],
                [['elaftwExternalId' => '2'], null],
            ]);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Experiment::class));

        $this->mapper->expects($this->exactly(2))->method('mapToInternal');
        $this->logger->expects($this->once())->method('info')->with($this->stringContains('Finished syncing 2 experiments FROM ElabFTW'));

        $entities = $this->synchronizer->syncAllFrom($connectedApp);

        $this->assertCount(2, $entities);
    }
}
