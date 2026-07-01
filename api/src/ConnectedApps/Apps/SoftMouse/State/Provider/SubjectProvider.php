<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\State\Provider;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Subject;
use App\Entity\User;
use App\Enum\AppCode;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provider.
 *
 * @implements ProviderInterface<Subject>
 */
final readonly class SubjectProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Subject> $decorated
     */
    public function __construct(
        #[Autowire(service: ItemProvider::class)]
        private ProviderInterface $decorated,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Subject|iterable|null
    {
        if (
            !(
                $operation instanceof Get
                && Subject::class === $operation->getClass()
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
