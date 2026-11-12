<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Command;

use App\ConnectedApps\Apps\Elabftw\ElabftwService;
use App\Enum\AppCode;
use App\Repository\ConnectedAppRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

readonly class SyncCommandProcessor
{
    public function __construct(
        private LoggerInterface $logger,
        private ElabftwService $elabftwService,
        private ConnectedAppRepository $connectedAppRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(?SymfonyStyle $io = null): void
    {
        $this->logger->info('Starting synchronization process for Elabftw app.');
        try {
            $apps = $this->connectedAppRepository->findBy(['code' => AppCode::Elabftw]);
            if (0 >= ($totalCount = \count($apps))) {
                $this->logger->warning('No Elabftw connected apps found.');

                return;
            }

            $io?->text(\sprintf('Found %d Elabftw connected apps to synchronize.', $totalCount));
            $io?->progressStart($totalCount);

            foreach ($apps as $app) {
                $this->elabftwService->fullSynchronizationFromExternal($app);
                $app->setLastSyncAt(new \DateTime());
                $this->em->flush();
                $io?->progressAdvance();
            }

            $io?->progressFinish();
            $this->logger->info('Synchronization process completed successfully.');
        } catch (\Throwable $throwable) {
            $this->logger->error('Error during synchronization: ' . $throwable->getMessage());

            return;
        }

        $this->logger->info('Elabftw synchronization process finished.');
    }
}
