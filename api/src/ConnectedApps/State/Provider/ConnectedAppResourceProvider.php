<?php

declare(strict_types=1);

// todo do it like https://api-platform.com/docs/core/state-providers/#symfony-state-provider-mechanism
// and create repository that will be used for instance when we persit the project with the info from the preclinicalTrials
// https://github.com/api-platform/demo/blob/86671152a0860257c218298b2b0e7508bbff254b/api/src/State/Processor/BookPersistProcessor.php#L35

namespace App\ConnectedApps\State\Provider;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Provider that will fetch then hydrate and merge data from connected apps with the current entity.
 *
 * @implements ProviderInterface<ConnectedAppEntityInterface>
 */
final readonly class ConnectedAppResourceProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: ItemProvider::class)]
        private ProviderInterface $decorated,
        private Security $security,
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
            !$operation instanceof Get
            || !is_a($resourceClass = $operation->getClass(), ConnectedAppEntityInterface::class, true)
        ) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getConnectedApps()->isEmpty()) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }
        // fetch the connected app entity from their api (using a custom repository or service) then hydrate and merge it with our Entity
        // like it's done in the api p demo: https://github.com/api-platform/demo/blob/86671152a0860257c218298b2b0e7508bbff254b/api/src/State/Processor/BookPersistProcessor.php#L35

        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
