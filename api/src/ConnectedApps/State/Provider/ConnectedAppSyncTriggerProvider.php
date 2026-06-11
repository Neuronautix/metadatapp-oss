<?php

declare(strict_types=1);

namespace App\ConnectedApps\State\Provider;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ConnectedApps\Messenger\Message\SyncAllFromAppMessage;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 *  Provider that triggers synchronization of connected app entities via Messenger.
 *
 * @implements ProviderInterface<ConnectedAppEntityInterface>
 */
final readonly class ConnectedAppSyncTriggerProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: CollectionProvider::class)]
        private ProviderInterface $decorated, // the default provider
        private Security $security,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @return ConnectedAppEntityInterface|iterable<ConnectedAppEntityInterface>|null
     *
     * @throws ExceptionInterface
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ConnectedAppEntityInterface|iterable|null
    {
        if (
            !$operation instanceof CollectionOperationInterface
            || !is_a($resourceClass = $operation->getClass(), ConnectedAppEntityInterface::class, true)
        ) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getConnectedApps()->isEmpty()) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        $this->bus->dispatch(new SyncAllFromAppMessage($user->getId(), $resourceClass));

        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
