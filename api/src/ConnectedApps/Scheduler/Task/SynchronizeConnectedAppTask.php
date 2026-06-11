<?php

declare(strict_types=1);

namespace App\ConnectedApps\Scheduler\Task;

use App\ConnectedApps\Messenger\Message\SyncFullFromAppMessage;
use App\Repository\ConnectedAppRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask('* * * * *')]
readonly class SynchronizeConnectedAppTask
{
    public function __construct(
        private ConnectedAppRepository $connectedAppRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(): void
    {
        $this->logger->info('Starting connected app sync task...');

        $apps = $this->connectedAppRepository->findAllAppToSync();
        if ([] === $apps) {
            $this->logger->warning('No connected apps to sync at this time.');

            return;
        }

        $countApp = \count($apps);
        if ($countApp > 1000) {
            throw new \RuntimeException('Too many connected apps to sync at once. Please implement pagination or batch processing.');
        }
        $this->logger->info(\sprintf('Found %d connected apps to sync.', $countApp));
        foreach ($apps as $app) {
            if (!$app->getId()) {
                continue;
            }
            $message = new SyncFullFromAppMessage($app->getId());
            $this->messageBus->dispatch($message);
            $this->logger->info(\sprintf('Dispatched sync message for connected app: %s %s', $app->getCode()->value, $app->getId()));
        }

        $this->logger->info('Connected app sync task completed successfully:.');
    }
}
