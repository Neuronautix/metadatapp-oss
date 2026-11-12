<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LlmProviderCredential;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LlmProviderCredential>
 */
final class LlmProviderCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LlmProviderCredential::class);
    }

    public function findActiveForUser(string $provider, User $user): ?LlmProviderCredential
    {
        return $this->findOneBy([
            'provider' => $provider,
            'user' => $user,
            'active' => true,
        ]);
    }

    /**
     * @return list<LlmProviderCredential>
     */
    public function findAllForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['provider' => 'ASC']);
    }
}
