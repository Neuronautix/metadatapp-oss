<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ConnectedApp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<ConnectedApp>
 */
class ConnectedAppRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConnectedApp::class);
    }

    /**
     * Find all apps for all users to be synchronized, for now it's used in the scheduler.
     * We can add some condition here to sync only app when they have some settings or any other conditions.
     *
     * @return ConnectedApp[]
     */
    public function findAllAppToSync(): array
    {
        return $this->findBy(['isActive' => true]);
    }

    /**
     * @return ConnectedApp[]
     */
    public function findAllActiveForUser(UserInterface $user): array
    {
        return $this->findBy(['user' => $user, 'isActive' => true]);
    }
}
