<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Scheduler\Task;

use App\ConnectedApps\Messenger\Message\SyncFullFromAppMessage;
use App\ConnectedApps\Scheduler\Task\SynchronizeConnectedAppTask;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use App\Repository\ConnectedAppRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SynchronizeConnectedAppTaskTest extends TestCase
{
    private ConnectedAppRepository $repository;
    private MessageBusInterface $messageBus;
    private LoggerInterface $logger;
    private SynchronizeConnectedAppTask $task;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ConnectedAppRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->task = new SynchronizeConnectedAppTask(
            $this->repository,
            $this->messageBus,
            $this->logger
        );
    }

    #[Test]
    public function invokeReturnsWhenNoAppsToSync(): void
    {
        $this->repository->expects($this->any())
            ->method('findAllAppToSync')
            ->willReturn([])
        ;

        $this->logger->expects($this->any())
            ->method('warning')
            ->with('No connected apps to sync at this time.')
        ;

        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->task)();
    }

    #[Test]
    public function invokeThrowsExceptionWhenTooManyApps(): void
    {
        $apps = array_fill(0, 1001, new ConnectedApp());

        $this->repository->expects($this->once())
            ->method('findAllAppToSync')
            ->willReturn($apps)
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Too many connected apps to sync at once. Please implement pagination or batch processing.');

        ($this->task)();
    }

    #[Test]
    public function invokeDispatchesMessagesForApps(): void
    {
        $app1 = $this->createMock(ConnectedApp::class);
        $app1->method('getId')->willReturn(Uuid::v4());
        $app1->method('getCode')->willReturn(AppCode::Elabftw);

        $app2 = $this->createMock(ConnectedApp::class);
        $app2->method('getId')->willReturn(Uuid::v4());
        $app2->method('getCode')->willReturn(AppCode::Elabftw);

        // App with no ID (should be skipped based on logic, though strict typing might make it hard to have null id)
        // If getId returns ?Uuid, then it is possible.
        $app3 = $this->createMock(ConnectedApp::class);
        $app3->method('getId')->willReturn(null);

        $this->repository->expects($this->once())
            ->method('findAllAppToSync')
            ->willReturn([$app1, $app2, $app3])
        ;

        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(SyncFullFromAppMessage::class))
            ->willReturn(new Envelope(new \stdClass()))
        ;

        ($this->task)();
    }
}
