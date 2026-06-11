<?php

declare(strict_types=1);
// api/src/Doctrine/CurrentUserExtension.php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\Entity\UserAwareInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class CurrentUserExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (!is_subclass_of($resourceClass, UserAwareInterface::class)) {
            return;
        }
        if (
            $this->security->isGranted('ROLE_SUPER_ADMIN', $this->security->getUser())
            || $this->security->isGranted('ROLE_ADMIN', $this->security->getUser())
        ) {
            return;
        }

        $user = $this->security->getUser();
        if (null === $user) {
            throw new AccessDeniedException('User is not authenticated, it should be forbidden in context of USer Aware, set a role or voter allowed to see all user\'s entities.');
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(\sprintf('%s.user = :current_user_id', $rootAlias));
        $queryBuilder->setParameter('current_user_id', $user);
    }
}
