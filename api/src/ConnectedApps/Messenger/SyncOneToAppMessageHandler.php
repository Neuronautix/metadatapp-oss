<?php

declare(strict_types=1);

namespace App\ConnectedApps\Messenger;

use App\ConnectedApps\ConnectedAppServiceFactory;
use App\ConnectedApps\Error\SyncValidityException;
use App\ConnectedApps\Messenger\Message\SyncOneToAppMessage;
use App\ConnectedApps\Shared\ConnectedAppToInterface;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class SyncOneToAppMessageHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
        private readonly ConnectedAppServiceFactory $factory,
    ) {
    }

    public function __invoke(SyncOneToAppMessage $message): void
    {
        try {
            $user = $this->userRepository->find($message->userId);
            if (null === $user) {
                $this->logger->error(\sprintf('User with ID %s not found.', $message->userId));

                return;
            }

            $apps = $user->getConnectedApps()->filter(static fn ($app) => $app->isActive() && $app->getId() !== $message->senderConnectedAppId);
            if ($apps->isEmpty()) {
                $this->logger->warning('No connected apps to sync at this time.');

                return;
            }

            foreach ($apps as $app) {
                try {
                    $appService = $this->factory->createService($app->getCode());
                    if (!$appService instanceof ConnectedAppToInterface || !$appService->supportsEntitySyncTo($message->entityClassName)) {
                        $this->logger->debug(\sprintf('App %s does not support entity %s, skipping synchronization one to.', $app->getCode()->value, $message->entityClassName));
                        continue;
                    }
                    $this->logger->info(\sprintf('Syncing connected app %s with ID %s for message %s.', $app->getCode()->value, $app->getId(), json_encode($message, \JSON_THROW_ON_ERROR)));

                    $appService->syncOneToExternal(
                        $app,
                        $message->entityClassName,
                        $message->entityId
                    );
                    $this->logger->info(\sprintf('Syncing connected app with ID %s for entity %s.', $app->getId(), $message->entityClassName));
                } catch (SyncValidityException $e) {
                    $this->logger->error(\sprintf('Skipping sync. Invalid entity %s %s for synchronizing with connected app %s with ID %s: %s. ', $message->entityClassName, $message->entityId, $app->getCode()->value, $app->getId(), $e->getMessage()));
                    continue;
                } catch (\Throwable $e) {
                    $this->logger->error(\sprintf('Error syncing connected app %s with ID %s: %s', $app->getCode()->value, $app->getId(), $e->getMessage()));
                    continue;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf('Error processing sync message: %s', $e->getMessage()));

            return;
        }
    }
}
