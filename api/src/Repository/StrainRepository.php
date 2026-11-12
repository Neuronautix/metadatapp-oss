<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Strain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Strain>
 */
class StrainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Strain::class);
    }

    public function findOneByNormalizedName(string $name): ?Strain
    {
        return $this->createQueryBuilder('strain')
            ->andWhere('LOWER(TRIM(strain.name)) = LOWER(TRIM(:name))')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
