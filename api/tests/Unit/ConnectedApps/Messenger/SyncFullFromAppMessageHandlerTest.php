<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Messenger;

use App\ConnectedApps\ConnectedAppServiceFactory;
use App\ConnectedApps\ConnectedAppServiceInterface;
use App\ConnectedApps\Messenger\Message\SyncFullFromAppMessage;
use App\ConnectedApps\Messenger\SyncFullFromAppMessageHandler;
use App\ConnectedApps\Shared\ConnectedAppFromInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use App\Repository\ConnectedAppRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SyncFullFromAppMessageHandlerTest extends TestCase
{
    private ConnectedAppRepository&MockObject $repository;
    private LoggerInterface&MockObject $logger;
    private ConnectedAppServiceFactory&MockObject $factory;
    private SyncFullFromAppMessageHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ConnectedAppRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->factory = $this->createMock(ConnectedAppServiceFactory::class);
        $this->handler = new SyncFullFromAppMessageHandler($this->repository, $this->logger, $this->factory);
    }

    #[Test]
    public function invokeReturnsWhenConnectedAppNotFound(): void
    {
        $id = Uuid::v4();
        $message = new SyncFullFromAppMessage($id);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn(null)
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with(\sprintf('Connected app with ID %s not found.', $id))
        ;

        $this->factory->expects($this->never())->method('createService');

        ($this->handler)($message);
    }

    #[Test]
    public function invokeReturnsWhenServiceNotSupported(): void
    {
        $id = Uuid::v4();
        $message = new SyncFullFromAppMessage($id);
        $connectedApp = $this->createMock(ConnectedApp::class);
        $connectedApp->method('getCode')->willReturn(AppCode::Elabftw);
        $connectedApp->method('getId')->willReturn($id);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn($connectedApp)
        ;

        // Service that does NOT implement ConnectedAppFromInterface
        $service = $this->createMock(ConnectedAppServiceInterface::class);

        $this->factory->expects($this->once())
            ->method('createService')
            ->with(AppCode::Elabftw)
            ->willReturn($service)
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with(\sprintf('Connected app %s is not supported.', AppCode::Elabftw->value))
        ;

        ($this->handler)($message);
    }

    #[Test]
    public function invokeSyncsSuccessfully(): void
    {
        $id = Uuid::v4();
        $message = new SyncFullFromAppMessage($id);
        $connectedApp = $this->createMock(ConnectedApp::class);
        $connectedApp->method('getCode')->willReturn(AppCode::Elabftw);
        $connectedApp->method('getId')->willReturn($id);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn($connectedApp)
        ;

        // Service that implements both ConnectedAppServiceInterface (for factory return type)
        // and ConnectedAppFromInterface (for handler logic)
        $service = $this->getMockBuilder(\App\Tests\Unit\ConnectedApps\Shared\AppServiceTestDouble::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $service->method('supportsCode')->willReturn(true);

        $service->expects($this->once())
            ->method('fullSynchronizationFromExternal')
            ->with($connectedApp)
        ;

        $this->factory->expects($this->once())
            ->method('createService')
            ->with(AppCode::Elabftw)
            ->willReturn($service)
        ;

        $this->logger->expects($this->exactly(2))
            ->method('info')
        ;

        ($this->handler)($message);
    }

    #[Test]
    public function invokeLogsErrorOnException(): void
    {
        $id = Uuid::v4();
        $message = new SyncFullFromAppMessage($id);
        $connectedApp = $this->createMock(ConnectedApp::class);
        $connectedApp->method('getId')->willReturn($id);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willThrowException(new \Exception('Database error'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Error processing sync message: Database error')
        ;

        ($this->handler)($message);
    }
}
