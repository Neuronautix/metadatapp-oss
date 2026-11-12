<?php

declare(strict_types=1);

namespace App\Tests\Functional\ConnectedApps\Elabftw;

use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ConnectedAppFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Enum\AppCode;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

class SyncCommandTest extends KernelTestCase
{
    use Factories;

    #[Test]
    public function execute(): void
    {
        ConnectedAppFactory::new([
            'code' => AppCode::Elabftw,
            'name' => 'Elabftw Test App',
            'isActive' => true,
            'account' => AccountFactory::new()->create(),
            'user' => UserFactory::new()->create(),
        ])->create();

        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('app:sync:elabftw');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertSame(Command::SUCCESS, $exitCode);
    }
}
