<?php

declare(strict_types=1);

namespace App\ConnectedApps\Messenger;

use App\ConnectedApps\ConnectedAppServiceFactory;
use App\ConnectedApps\Messenger\Message\SyncOneFromAppMessage;
use App\ConnectedApps\Shared\ConnectedAppFromInterface;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class SyncOneFromAppMessageHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private ConnectedAppServiceFactory $factory,
    ) {
    }

    public function __invoke(SyncOneFromAppMessage $message): void
    {
        try {
            $user = $this->userRepository->find($message->userId);
            if (null === $user) {
                $this->logger->error(\sprintf('User with ID %s not found.', $message->userId));

                return;
            }

            $apps = $user->getConnectedApps()->filter(static fn ($connectedApp) => $connectedApp->isActive());
            if ($apps->isEmpty()) {
                $this->logger->warning('No connected apps to sync at this time.');

                return;
            }

            foreach ($apps as $connectedApp) {
                $appService = $this->factory->createService($connectedApp->getCode());
                if (!$appService instanceof ConnectedAppFromInterface || !$appService->supportsEntitySyncFrom($message->entityClassName)) {
                    $this->logger->debug(\sprintf('App %s does not support entity %s, skipping synchronization one from.', $connectedApp->getCode()->value, $message->entityClassName));
                    continue;
                }
                $this->logger->info(\sprintf('Syncing connected app %s with ID %s for message %s.', $connectedApp->getCode()->value, $connectedApp->getId(), json_encode($message, \JSON_THROW_ON_ERROR)));
                $appService->syncOneFromExternal(
                    $connectedApp,
                    $message->entityClassName,
                    $message->entityId
                );
                $this->logger->info(\sprintf('Syncing connected app with ID %s.', $connectedApp->getId()));
            }
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf('Error processing sync message: %s', $e->getMessage()));

            return;
        }
    }
}
