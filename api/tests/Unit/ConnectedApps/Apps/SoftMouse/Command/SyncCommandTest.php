<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\SoftMouse\Command;

use App\ConnectedApps\Apps\SoftMouse\Command\SyncCommand;
use App\ConnectedApps\Apps\SoftMouse\SoftMouseService;
use App\Entity\ConnectedApp;
use App\Entity\User;
use App\Enum\AppCode;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SyncCommandTest extends TestCase
{
    private UserRepository $userRepository;
    private SoftMouseService $softMouseService;
    private EntityManagerInterface $em;
    private SyncCommand $command;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->softMouseService = $this->createMock(SoftMouseService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->command = new SyncCommand(
            $this->userRepository,
            $this->softMouseService,
            $this->em
        );
    }

    #[Test]
    public function execute(): void
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('app:sync:softmouse');
        $commandTester = new CommandTester($command);

        $email = 'researcher@example.test';

        $user = $this->createMock(User::class);
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn($user)
        ;

        $app1 = $this->createMock(ConnectedApp::class);
        $app1->method('isActive')->willReturn(true);
        $app1->method('getId')->willReturn(\Symfony\Component\Uid\Uuid::v4());

        $user->expects($this->once())
            ->method('getConnectedAppByCode')
            ->with(AppCode::SoftMouse)
            ->willReturn($app1)
        ;

        $this->softMouseService->expects($this->once())
            ->method('sync')
            ->with($app1)
        ;

        $commandTester->execute(['email' => $email]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('SoftMouse ➜ Synchronisation (Batched)', $output);
    }
}
