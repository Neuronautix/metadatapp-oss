<?php

declare(strict_types=1);

namespace App\ConnectedApps\Messenger;

use App\ConnectedApps\ConnectedAppServiceFactory;
use App\ConnectedApps\Messenger\Message\SyncAllFromAppMessage;
use App\ConnectedApps\Messenger\Message\SyncOneToAppMessage;
use App\ConnectedApps\Shared\ConnectedAppFromInterface;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class SyncAllFromAppMessageHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private ConnectedAppServiceFactory $factory,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(SyncAllFromAppMessage $message): void
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
                if (!$appService instanceof ConnectedAppFromInterface) {
                    $this->logger->info(\sprintf('No service found for connected app with code %s.', $connectedApp->getCode()->value));

                    continue;
                }
                if (!$appService->supportsEntitySyncFrom($message->entityClassName)) {
                    $this->logger->info(\sprintf('App %s does not support entity %s, skipping synchronization all from.', $connectedApp->getCode()->value, $message->entityClassName));
                    continue;
                }
                $this->logger->info(\sprintf('Syncing connected app %s with ID %s for message %s.', $connectedApp->getCode()->value, $connectedApp->getId(), json_encode($message, \JSON_THROW_ON_ERROR)));

                $entities = $appService->syncAllFromExternal(
                    $connectedApp,
                    $message->entityClassName,
                );
                $this->logger->info(\sprintf('Syncing connected app with ID %s.', $connectedApp->getId()));
                // synchronization with external id to push
                foreach ($entities ?? [] as $entity) {
                    $entityId = $entity->getId();
                    $userId = $user->getId();
                    if ($entityId && $userId) {
                        $this->logger->info(\sprintf('Synchronized entity with ID %s from connected app with ID %s.', $entityId, $connectedApp->getId()));
                        $this->messageBus->dispatch(
                            new SyncOneToAppMessage(
                                $userId,
                                $message->entityClassName,
                                $entityId,
                                $connectedApp->getId(),
                            )
                        );
                    } else {
                        $this->logger->warning('Entity or user ID is null, skipping message dispatch.');
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf('Error processing sync message: %s', $e->getMessage()));

            return;
        }
    }
}
