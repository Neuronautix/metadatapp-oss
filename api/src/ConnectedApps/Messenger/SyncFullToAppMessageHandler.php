<?php

declare(strict_types=1);

namespace App\ConnectedApps\Messenger;

use App\ConnectedApps\ConnectedAppServiceFactory;
use App\ConnectedApps\Messenger\Message\SyncFullToAppMessage;
use App\ConnectedApps\Shared\ConnectedAppToInterface;
use App\Entity\Experiment;
use App\Entity\Subject;
use App\Repository\ConnectedAppRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncFullToAppMessageHandler
{
    public function __construct(
        private ConnectedAppRepository $connectedAppRepository,
        private LoggerInterface $logger,
        private ConnectedAppServiceFactory $factory,
    ) {
    }

    public function __invoke(SyncFullToAppMessage $message): void
    {
        try {
            $connectedApp = $this->connectedAppRepository->find($message->connectedAppId);
            if (null === $connectedApp) {
                $this->logger->error(\sprintf('Connected app with ID %s not found.', $message->connectedAppId));

                return;
            }

            $appService = $this->factory->createService($connectedApp->getCode());
            if (!$appService instanceof ConnectedAppToInterface) {
                $this->logger->error(\sprintf('Connected app %s does not support outbound synchronization.', $connectedApp->getCode()->value));

                return;
            }

            foreach ([Experiment::class, Subject::class] as $entityClassName) {
                if (!$appService->supportsEntitySyncTo($entityClassName)) {
                    continue;
                }

                $appService->syncAllToExternal($connectedApp, $entityClassName);
            }

            $this->logger->info(\sprintf('Outbound sync successful for app %s.', $connectedApp->getId()));
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf('Error processing outbound sync message: %s', $e->getMessage()));
        }
    }
}
