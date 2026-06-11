<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Messenger;

use App\ConnectedApps\ConnectedAppServiceFactory;
use App\ConnectedApps\Messenger\Message\SyncOneFromAppMessage;
use App\ConnectedApps\Messenger\SyncOneFromAppMessageHandler;
use App\Entity\ConnectedApp;
use App\Entity\User;
use App\Enum\AppCode;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SyncOneFromAppMessageHandlerTest extends TestCase
{
    private UserRepository $userRepository;
    private LoggerInterface $logger;
    private ConnectedAppServiceFactory $factory;
    private SyncOneFromAppMessageHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->factory = $this->createMock(ConnectedAppServiceFactory::class);
        $this->handler = new SyncOneFromAppMessageHandler($this->userRepository, $this->logger, $this->factory);
    }

    #[Test]
    public function invokeReturnsWhenUserNotFound(): void
    {
        $userId = Uuid::v4();
        $message = new SyncOneFromAppMessage($userId, 'ClassName', Uuid::v4());

        $this->userRepository->expects($this->once())
            ->method('find')
            ->with($userId)
            ->willReturn(null)
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with(\sprintf('User with ID %s not found.', $userId))
        ;

        ($this->handler)($message);
    }

    #[Test]
    public function invokeReturnsWhenNoActiveApps(): void
    {
        $userId = Uuid::v4();
        $message = new SyncOneFromAppMessage($userId, 'ClassName', Uuid::v4());
        $user = $this->createMock(User::class);

        $this->userRepository->expects($this->once())
            ->method('find')
            ->with($userId)
            ->willReturn($user)
        ;

        $user->expects($this->once())
            ->method('getConnectedApps')
            ->willReturn(new ArrayCollection([]))
        ;

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('No connected apps to sync at this time.')
        ;

        ($this->handler)($message);
    }

    #[Test]
    public function invokeSyncs(): void
    {
        $userId = Uuid::v4();
        $entityId = Uuid::v4();
        $className = 'ClassName';
        $message = new SyncOneFromAppMessage($userId, $className, $entityId);
        $user = $this->createMock(User::class);
        $connectedApp = $this->createMock(ConnectedApp::class);
        $connectedApp->method('isActive')->willReturn(true);
        $connectedApp->method('getCode')->willReturn(AppCode::Elabftw);
        $connectedApp->method('getId')->willReturn(Uuid::v4());

        $this->userRepository->expects($this->once())
            ->method('find')
            ->with($userId)
            ->willReturn($user)
        ;

        $user->expects($this->once())
            ->method('getConnectedApps')
            ->willReturn(new ArrayCollection([$connectedApp]))
        ;

        $service = $this->createMock(TestConnectedAppFromService::class);
        $this->factory->expects($this->once())
            ->method('createService')
            ->with(AppCode::Elabftw)
            ->willReturn($service)
        ;

        $service->expects($this->once())
            ->method('supportsEntitySyncFrom')
            ->with($className)
            ->willReturn(true)
        ;

        $service->expects($this->once())
            ->method('syncOneFromExternal')
            ->with($connectedApp, $className, $entityId)
        ;

        ($this->handler)($message);
    }
}
