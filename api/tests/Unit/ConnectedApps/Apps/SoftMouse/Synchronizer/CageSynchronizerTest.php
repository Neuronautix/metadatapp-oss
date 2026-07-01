<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\SoftMouse\Synchronizer;

use App\ConnectedApps\Apps\SoftMouse\Client\ClientInterface;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\CageDto;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\Cages;
use App\ConnectedApps\Apps\SoftMouse\Mapper\CageMapper;
use App\ConnectedApps\Apps\SoftMouse\Synchronizer\CageSynchronizer;
use App\Entity\Account;
use App\Entity\Cage;
use App\Entity\ConnectedApp;
use App\Repository\CageRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CageSynchronizerTest extends TestCase
{
    private ClientInterface $client;
    private Cages $cagesResource;
    private EntityManagerInterface $em;
    private CageRepository $cageRepository;
    private CageMapper $cageMapper;
    private CageSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->cagesResource = $this->createMock(Cages::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->cageRepository = $this->createMock(CageRepository::class);
        $this->cageMapper = $this->createMock(CageMapper::class);

        $this->synchronizer = new CageSynchronizer(
            $this->client,
            $this->em,
            $this->cageRepository,
            $this->cageMapper
        );
    }

    #[Test]
    public function syncCagesReturnsEmptyArrayWhenNoCagesReturned(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);

        $this->client->expects($this->once())
            ->method('cages')
            ->willReturn($this->cagesResource)
        ;

        $this->cagesResource->expects($this->once())
            ->method('search')
            ->willReturn([])
        ;

        $result = $this->synchronizer->syncCages($connectedApp);

        $this->assertEmpty($result);
    }

    #[Test]
    public function syncCagesSyncsNewCage(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $account = $this->createMock(Account::class);
        $connectedApp->method('getAccount')->willReturn($account);

        $cageDto = new CageDto(
            1,
            'owner',
            new \DateTime(),
            new \DateTime(),
            'creator',
            123,
            1
        );

        $this->client->expects($this->once())
            ->method('cages')
            ->willReturn($this->cagesResource)
        ;

        $this->cagesResource->expects($this->once())
            ->method('search')
            ->willReturn([$cageDto])
        ;

        $this->cageRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['softmouseExternalId' => 123])
            ->willReturn(null)
        ;

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Cage::class))
        ;

        $this->cageMapper->expects($this->once())
            ->method('mapFromExternal')
            ->with($cageDto, $this->isInstanceOf(Cage::class))
        ;

        $result = $this->synchronizer->syncCages($connectedApp);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Cage::class, $result[0]);
    }

    #[Test]
    public function syncCageThrowsExceptionIfCageNotFound(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();

        $this->cageRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn(null)
        ;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cage not found');

        $this->synchronizer->syncCage($connectedApp, $uuid);
    }

    #[Test]
    public function syncCageThrowsExceptionIfCageHasNoExternalId(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();
        $cage = new Cage();

        $this->cageRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($cage)
        ;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cage external id not found');

        $this->synchronizer->syncCage($connectedApp, $uuid);
    }

    #[Test]
    public function syncCageThrowsExceptionIfCageNotFoundInSoftMouse(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();
        $cage = new Cage();
        $cage->setSoftmouseExternalId('123');

        $this->cageRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($cage)
        ;

        $this->client->expects($this->once())
            ->method('cages')
            ->willReturn($this->cagesResource)
        ;

        $this->cagesResource->expects($this->once())
            ->method('get')
            ->with('123')
            ->willReturn(null)
        ;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cage not found in SoftMouse');

        $this->synchronizer->syncCage($connectedApp, $uuid);
    }

    #[Test]
    public function syncCageSuccess(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $account = $this->createMock(Account::class);
        $connectedApp->method('getAccount')->willReturn($account);

        $uuid = Uuid::v4();
        $cage = new Cage();
        $cage->setSoftmouseExternalId('123');
        $cageDto = new CageDto(
            1,
            'owner',
            new \DateTime(),
            new \DateTime(),
            'creator',
            123,
            1
        );

        $this->cageRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($cage)
        ;

        $this->client->expects($this->once())
            ->method('cages')
            ->willReturn($this->cagesResource)
        ;

        $this->cagesResource->expects($this->once())
            ->method('get')
            ->with('123')
            ->willReturn($cageDto)
        ;

        $this->cageMapper->expects($this->once())
            ->method('mapFromExternal')
            ->with($cageDto, $cage)
        ;

        $this->em->expects($this->once())
            ->method('persist')
            ->with($cage)
        ;

        $this->synchronizer->syncCage($connectedApp, $uuid);
    }
}
