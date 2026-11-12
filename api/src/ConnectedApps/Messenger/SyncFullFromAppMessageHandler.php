<?php

declare(strict_types=1);

namespace App\ConnectedApps\Messenger;

use App\ConnectedApps\ConnectedAppServiceFactory;
use App\ConnectedApps\Messenger\Message\SyncFullFromAppMessage;
use App\ConnectedApps\Shared\ConnectedAppFromInterface;
use App\Repository\ConnectedAppRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * This message handler is responsible for synchronizing all entities of all classes for a connected app.
 */
#[AsMessageHandler]
readonly class SyncFullFromAppMessageHandler // naming
{
    public function __construct(
        private ConnectedAppRepository $connectedAppRepository,
        private LoggerInterface $logger,
        private ConnectedAppServiceFactory $factory,
    ) {
    }

    public function __invoke(SyncFullFromAppMessage $message): void
    {
        try {
            // Fetch the connected app by ID
            $connectedApp = $this->connectedAppRepository->find($message->connectedAppId);

            if (null === $connectedApp) {
                $this->logger->error(\sprintf('Connected app with ID %s not found.', $message->connectedAppId));

                return;
            }

            $appService = $this->factory->createService($connectedApp->getCode());
            if (!$appService instanceof ConnectedAppFromInterface) {
                $this->logger->error(\sprintf('Connected app %s is not supported.', $connectedApp->getCode()->value));

                return;
            }
            $this->logger->info(\sprintf('Let`s sync from connected app %s with ID %s for message %s.', $connectedApp->getCode()->value, $connectedApp->getId(), json_encode($message, \JSON_THROW_ON_ERROR)));
            $appService->fullSynchronizationFromExternal($connectedApp);

            $this->logger->info(\sprintf('Sync successful for App %s .', $connectedApp->getId()));
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf('Error processing sync message: %s', $e->getMessage()));

            return;
        }
    }
}
