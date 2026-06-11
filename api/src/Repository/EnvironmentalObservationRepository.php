<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EnvironmentalObservation;
use App\Entity\Room;
use App\Entity\System;
use App\Enum\EnvironmentalMetric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EnvironmentalObservation>
 *
 * @method EnvironmentalObservation|null find($id, $lockMode = null, $lockVersion = null)
 * @method EnvironmentalObservation|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method EnvironmentalObservation[]    findAll()
 * @method EnvironmentalObservation[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class EnvironmentalObservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnvironmentalObservation::class);
    }

    /**
     * @return list<EnvironmentalObservation>
     */
    public function findForSystem(System $system): array
    {
        return $this->createQueryBuilder('observation')
            ->andWhere('observation.system = :system')
            ->setParameter('system', $system)
            ->orderBy('observation.timestamp', 'ASC')
            ->addOrderBy('observation.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<EnvironmentalObservation>
     */
    public function findForRoom(Room $room): array
    {
        return $this->createQueryBuilder('observation')
            ->leftJoin('observation.system', 'system')
            ->andWhere('observation.room = :room OR system.room = :room')
            ->setParameter('room', $room)
            ->orderBy('observation.timestamp', 'ASC')
            ->addOrderBy('observation.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findLatestForSystemMetric(System $system, EnvironmentalMetric $metric): ?EnvironmentalObservation
    {
        return $this->createQueryBuilder('observation')
            ->andWhere('observation.system = :system')
            ->andWhere('observation.metric = :metric')
            ->setParameter('system', $system)
            ->setParameter('metric', $metric)
            ->orderBy('observation.timestamp', 'DESC')
            ->addOrderBy('observation.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Returns the latest observation per (system, metric) combination for a set of systems.
     * Uses a subquery to avoid N+1 queries when building collection snapshots.
     *
     * @param list<System> $systems
     *
     * @return array<string, array<string, EnvironmentalObservation>> keyed by system code then metric value
     */
    public function findLatestPerSystemMetric(array $systems): array
    {
        if ([] === $systems) {
            return [];
        }

        $subQB = $this->createQueryBuilder('sub')
            ->select('MAX(sub.id)')
            ->andWhere('sub.system IN (:systems)')
            ->groupBy('sub.system, sub.metric')
        ;

        /** @var list<EnvironmentalObservation> $observations */
        $observations = $this->createQueryBuilder('observation')
            ->andWhere('observation.id IN (' . $subQB->getDQL() . ')')
            ->setParameter('systems', $systems)
            ->getQuery()
            ->getResult()
        ;

        $result = [];
        foreach ($observations as $observation) {
            $systemCode = $observation->getSystem()?->getCode();
            if (null === $systemCode) {
                continue;
            }

            $metricValue = $observation->getMetric()->value;
            $result[$systemCode][$metricValue] = $observation;
        }

        return $result;
    }
}
