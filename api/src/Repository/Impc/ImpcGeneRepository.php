<?php

declare(strict_types=1);

namespace App\Repository\Impc;

use App\Entity\Impc\ImpcGene;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImpcGene>
 */
class ImpcGeneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImpcGene::class);
    }
}
