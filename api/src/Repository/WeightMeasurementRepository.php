<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WeightMeasurement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeightMeasurement>
 *
 * @method WeightMeasurement|null find($id, $lockMode = null, $lockVersion = null)
 * @method WeightMeasurement|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method WeightMeasurement[]    findAll()
 * @method WeightMeasurement[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class WeightMeasurementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeightMeasurement::class);
    }
}
