<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Messenger;

use App\ConnectedApps\ConnectedAppServiceFactory;
use App\ConnectedApps\Messenger\Message\SyncOneToAppMessage;
use App\ConnectedApps\Messenger\SyncOneToAppMessageHandler;
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
class SyncOneToAppMessageHandlerTest extends TestCase
{
    private UserRepository $userRepository;
    private LoggerInterface $logger;
    private ConnectedAppServiceFactory $factory;
    private SyncOneToAppMessageHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->factory = $this->createMock(ConnectedAppServiceFactory::class);

        $this->handler = new SyncOneToAppMessageHandler(
            $this->userRepository,
            $this->logger,
            $this->factory
        );
    }

    #[Test]
    public function invokeReturnsWhenUserNotFound(): void
    {
        $userId = Uuid::v4();
        $message = new SyncOneToAppMessage($userId, 'SomeEntity', Uuid::v4());

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
    public function invokeSyncs(): void
    {
        $userId = Uuid::v4();
        $entityId = Uuid::v4();
        $className = 'App\Entity\Experiment';
        $message = new SyncOneToAppMessage($userId, $className, $entityId);
        $user = $this->createMock(User::class);

        $connectedApp = $this->createMock(ConnectedApp::class);
        $connectedApp->method('isActive')->willReturn(true);
        $connectedApp->method('getCode')->willReturn(AppCode::Fair3r);
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

        $service = $this->createMock(TestConnectedAppToService::class);
        $this->factory->expects($this->once())
            ->method('createService')
            ->with(AppCode::Fair3r)
            ->willReturn($service)
        ;

        $service->expects($this->once())
            ->method('supportsEntitySyncTo')
            ->with($className)
            ->willReturn(true)
        ;

        $service->expects($this->once())
            ->method('syncOneToExternal')
            ->with($connectedApp, $className, $entityId)
        ;

        ($this->handler)($message);
    }
}
