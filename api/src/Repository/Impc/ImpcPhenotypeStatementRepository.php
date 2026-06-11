<?php

declare(strict_types=1);

namespace App\Repository\Impc;

use App\Entity\Impc\ImpcPhenotypeStatement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImpcPhenotypeStatement>
 */
class ImpcPhenotypeStatementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImpcPhenotypeStatement::class);
    }
}
