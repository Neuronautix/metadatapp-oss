<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExternalForm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExternalForm>
 *
 * @method ExternalForm|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExternalForm|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method ExternalForm[]    findAll()
 * @method ExternalForm[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class ExternalFormRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalForm::class);
    }
}
