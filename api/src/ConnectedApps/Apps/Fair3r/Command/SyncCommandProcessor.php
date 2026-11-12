<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Command;

use App\ConnectedApps\Apps\Fair3r\Fair3rService;
use App\Enum\AppCode;
use App\Repository\ConnectedAppRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncCommandProcessor
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Fair3rService $fari3r3rService,
        private readonly ConnectedAppRepository $connectedAppRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(?SymfonyStyle $io = null): void
    {
        $this->logger->info('Starting synchronization process for Fair3r app.');

        try {
            $apps = $this->connectedAppRepository->findBy(['code' => AppCode::Fair3r]);
            if (0 >= ($totalCount = \count($apps))) {
                $this->logger->warning('No Fair3r connected apps found.');

                return;
            }

            $io?->text(\sprintf('Found %d Fair3r connected apps to synchronize.', $totalCount));

            foreach ($apps as $app) {
                $this->fari3r3rService->sync($app);
                $app->setLastSyncAt(new \DateTime());
                $this->em->flush();
            }

            $this->logger->info('Synchronization process completed successfully.');
        } catch (\Throwable $throwable) {
            $this->logger->error('[app][Fair3R][SyncCommand] Error during synchronization: ' . $throwable->getMessage());

            return;
        }

        $this->logger->info('Fair3r synchronization process finished.');
    }
}
