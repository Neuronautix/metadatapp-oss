<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\CdeElement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CdeElement>
 */
class CdeElementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CdeElement::class);
    }

    public function findByAccountAndTinyId(Account $account, string $tinyId): ?CdeElement
    {
        return $this->findOneBy(['account' => $account, 'tinyId' => $tinyId]);
    }
}
