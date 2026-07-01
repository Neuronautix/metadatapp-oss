<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Tecniplast\Command;

use App\ConnectedApps\Apps\Tecniplast\TecniplastService;
use App\Enum\AppCode;
use App\Repository\ConnectedAppRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync:tecniplast',
    description: 'Synchronize data from Tecniplast',
)]
class SyncCommand extends Command
{
    public function __construct(
        private readonly ConnectedAppRepository $connectedAppRepository,
        private readonly TecniplastService $tecniplastService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Tecniplast Synchronization');

        $connectedApps = $this->connectedAppRepository->findBy(['code' => AppCode::Tecniplast]);

        if (empty($connectedApps)) {
            $io->warning('No Tecniplast connected apps found.');

            return Command::SUCCESS;
        }

        foreach ($connectedApps as $connectedApp) {
            if (!$connectedApp->isActive()) {
                $io->note(\sprintf('Skipping inactive App ID: %s', $connectedApp->getId()));
                continue;
            }

            $io->section(\sprintf('Syncing for App ID: %s (%s)', $connectedApp->getId(), $connectedApp->getName()));
            try {
                $this->tecniplastService->fullSynchronizationFromExternal($connectedApp);
                $io->success(\sprintf('Sync completed for %s', $connectedApp->getId()));
            } catch (\Exception $e) {
                $io->error(\sprintf('Sync failed for %s: %s', $connectedApp->getId(), $e->getMessage()));
            }
        }

        return Command::SUCCESS;
    }
}
