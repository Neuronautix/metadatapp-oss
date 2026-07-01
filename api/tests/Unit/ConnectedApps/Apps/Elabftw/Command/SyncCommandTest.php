<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Elabftw\Command;

use App\ConnectedApps\Apps\Elabftw\Command\SyncCommand;
use App\ConnectedApps\Apps\Elabftw\Command\SyncCommandProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SyncCommandTest extends TestCase
{
    #[Test]
    public function execute(): void
    {
        $processor = $this->createMock(SyncCommandProcessor::class);
        $command = new SyncCommand($processor);

        $application = new Application();
        $application->add($command);

        $command = $application->find('app:sync:elabftw');
        $commandTester = new CommandTester($command);

        $processor->expects($this->once())
            ->method('process')
        ;

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Synchronisation: ElabFTW -> Mapp', $output);
        $this->assertStringContainsString('Synchronization completed successfully.', $output);
    }
}
