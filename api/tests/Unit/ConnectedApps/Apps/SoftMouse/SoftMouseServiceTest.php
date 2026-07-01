<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\SoftMouse;

use App\ConnectedApps\Apps\SoftMouse\Error\SoftMouseSyncError;
use App\ConnectedApps\Apps\SoftMouse\SoftMouseService;
use App\ConnectedApps\Apps\SoftMouse\Synchronizer\CageSynchronizer;
use App\ConnectedApps\Apps\SoftMouse\Synchronizer\ExperimentSynchronizer;
use App\ConnectedApps\Apps\SoftMouse\Synchronizer\SubjectSynchronizer;
use App\ConnectedApps\Messenger\Message\SyncOneToAppMessage;
use App\Entity\Cage;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Entity\Subject;
use App\Entity\User;
use App\Enum\AppCode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SoftMouseServiceTest extends TestCase
{
    private SubjectSynchronizer $subjectSynchronizer;
    private CageSynchronizer $cageSynchronizer;
    private ExperimentSynchronizer $experimentSynchronizer;
    private MessageBusInterface $messageBus;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private SoftMouseService $service;

    protected function setUp(): void
    {
        $this->subjectSynchronizer = $this->createMock(SubjectSynchronizer::class);
        $this->cageSynchronizer = $this->createMock(CageSynchronizer::class);
        $this->experimentSynchronizer = $this->createMock(ExperimentSynchronizer::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new SoftMouseService(
            $this->subjectSynchronizer,
            $this->cageSynchronizer,
            $this->experimentSynchronizer,
            $this->messageBus,
            $this->em,
            $this->logger
        );
    }

    #[Test]
    public function supportsCode(): void
    {
        $this->assertTrue($this->service->supportsCode(AppCode::SoftMouse));
        $this->assertFalse($this->service->supportsCode(AppCode::Fair3r));
    }

    #[Test]
    public function supportsEntitySyncFrom(): void
    {
        $this->assertTrue($this->service->supportsEntitySyncFrom(Cage::class));
        $this->assertTrue($this->service->supportsEntitySyncFrom(Subject::class));
        $this->assertTrue($this->service->supportsEntitySyncFrom(Experiment::class));
        $this->assertFalse($this->service->supportsEntitySyncFrom(User::class));
    }

    #[Test]
    public function supportsEntitySyncTo(): void
    {
        $this->assertFalse($this->service->supportsEntitySyncTo(Cage::class));
    }

    #[Test]
    public function syncOneFromExternalExperiment(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $user = $this->createMock(User::class);
        $userId = Uuid::v4();
        $connectedAppId = Uuid::v4();
        $experimentId = Uuid::v4();
        $externalId = Uuid::v4();

        $connectedApp->method('getuser')->willReturn($user);
        $connectedApp->method('getId')->willReturn($connectedAppId);
        $user->method('getId')->willReturn($userId);

        $connectedApp->expects($this->once())->method('setLastSyncAt');

        $experiment = $this->createMock(Experiment::class);
        $experiment->method('getId')->willReturn($experimentId);

        $this->experimentSynchronizer->expects($this->once())
            ->method('syncOneFrom')
            ->with($connectedApp, $externalId)
            ->willReturn($experiment)
        ;

        $this->em->expects($this->once())->method('flush');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SyncOneToAppMessage::class))
            ->willReturn(new Envelope(new \stdClass()))
        ;

        $this->service->syncOneFromExternal($connectedApp, Experiment::class, $externalId);
    }

    #[Test]
    public function syncOneFromExternalThrowsExceptionForUnsupportedEntity(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Entity classname "App\Entity\User" not implemented');

        $this->service->syncOneFromExternal($connectedApp, User::class, $uuid);
    }

    #[Test]
    public function fullSynchronizationFromExternalSuccess(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $connectedAppId = Uuid::v4();
        $connectedApp->method('getId')->willReturn($connectedAppId);

        $this->experimentSynchronizer->expects($this->once())
            ->method('syncAllFrom')
            ->with($connectedApp)
        ;

        $this->cageSynchronizer->expects($this->once())
            ->method('syncCages')
            ->with($connectedApp)
        ;

        $this->subjectSynchronizer->expects($this->once())
            ->method('syncSubjects')
            ->with($connectedApp)
        ;

        $this->em->expects($this->once())->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('[Apps][SoftMouse] Sync completed successfully')
        ;

        $this->service->fullSynchronizationFromExternal($connectedApp);
    }

    #[Test]
    public function fullSynchronizationFromExternalFailure(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $connectedApp->method('getId')->willReturn(Uuid::v4());

        $exception = new \RuntimeException('Sync Error');
        $this->experimentSynchronizer->expects($this->once())
            ->method('syncAllFrom')
            ->willThrowException($exception)
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('[Apps][SoftMouse] Sync failed', $this->anything())
        ;

        $this->expectException(SoftMouseSyncError::class);
        $this->expectExceptionMessage('Full synchronization failed: Sync Error');

        $this->service->fullSynchronizationFromExternal($connectedApp);
    }
}
