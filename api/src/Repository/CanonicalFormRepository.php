<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CanonicalForm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CanonicalForm>
 *
 * @method CanonicalForm|null find($id, $lockMode = null, $lockVersion = null)
 * @method CanonicalForm|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method CanonicalForm[]    findAll()
 * @method CanonicalForm[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class CanonicalFormRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CanonicalForm::class);
    }
}
