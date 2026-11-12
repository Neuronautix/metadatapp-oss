<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Messenger;

use App\ConnectedApps\ConnectedAppServiceFactory;
use App\ConnectedApps\Messenger\Message\SyncAllFromAppMessage;
use App\ConnectedApps\Messenger\Message\SyncOneToAppMessage;
use App\ConnectedApps\Messenger\SyncAllFromAppMessageHandler;
use App\Entity\ConnectedApp;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\User;
use App\Enum\AppCode;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SyncAllFromAppMessageHandlerTest extends TestCase
{
    private UserRepository $userRepository;
    private LoggerInterface $logger;
    private ConnectedAppServiceFactory $factory;
    private MessageBusInterface $messageBus;
    private SyncAllFromAppMessageHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->factory = $this->createMock(ConnectedAppServiceFactory::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->handler = new SyncAllFromAppMessageHandler(
            $this->userRepository,
            $this->logger,
            $this->factory,
            $this->messageBus
        );
    }

    #[Test]
    public function invokeReturnsWhenUserNotFound(): void
    {
        $userId = Uuid::v4();
        $message = new SyncAllFromAppMessage($userId, 'SomeEntity');

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
        $message = new SyncAllFromAppMessage($userId, 'SomeEntity');
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
    public function invokeSyncsAndDispatches(): void
    {
        $userId = Uuid::v4();
        $className = 'App\Entity\Experiment';
        $message = new SyncAllFromAppMessage($userId, $className);
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);

        $connectedApp = $this->createMock(ConnectedApp::class);
        $connectedApp->method('isActive')->willReturn(true);
        $connectedApp->method('getCode')->willReturn(AppCode::SoftMouse);
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
            ->with(AppCode::SoftMouse)
            ->willReturn($service)
        ;

        $service->expects($this->once())
            ->method('supportsEntitySyncFrom')
            ->with($className)
            ->willReturn(true)
        ;

        $entity = $this->createMock(ConnectedAppEntityInterface::class);
        $entity->method('getId')->willReturn(Uuid::v4());

        $service->expects($this->once())
            ->method('syncAllFromExternal')
            ->with($connectedApp, $className)
            ->willReturn([$entity])
        ;

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SyncOneToAppMessage::class))
            ->willReturn(new Envelope(new \stdClass()))
        ;

        ($this->handler)($message);
    }
}
