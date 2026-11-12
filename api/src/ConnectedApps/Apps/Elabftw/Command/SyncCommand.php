<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync:elabftw',
    description: 'Synchronise data from ElabFTW into Metadatapp',
)]
class SyncCommand extends Command
{
    public function __construct(
        private readonly SyncCommandProcessor $syncCommandProcessor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Simulate the synchronization process without persisting changes')
        ;
    }

    /**
     * @throws \Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Synchronisation: ElabFTW -> Mapp');
        $io->text($dryRun ? 'Running in dry-run mode. No changes will be persisted.' : 'Running in live mode.');

        $this->syncCommandProcessor->process($io);

        $io->success('Synchronization completed successfully.');

        return Command::SUCCESS;
    }
}
