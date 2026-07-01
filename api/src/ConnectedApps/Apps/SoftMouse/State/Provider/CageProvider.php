<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\State\Provider;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Cage;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\User;
use App\Enum\AppCode;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provider.
 * /**
 * Provider.
 *
 * @implements ProviderInterface<ConnectedAppEntityInterface>
 */
final readonly class CageProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<ConnectedAppEntityInterface> $decorated
     */
    public function __construct(
        #[Autowire(service: ItemProvider::class)]
        private ProviderInterface $decorated,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ConnectedAppEntityInterface|iterable|null
    {
        if (
            !(
                $operation instanceof Get
                && Cage::class === $operation->getClass()
                && ($user = $this->security->getUser())
                && $user instanceof User
                && $user->getConnectedAppByCode(AppCode::SoftMouse)
            )
        ) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        // todo fetch and hydrate the entity from the connected app here
        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
