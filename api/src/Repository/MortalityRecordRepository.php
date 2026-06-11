<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\MortalityRecord;
use App\Entity\Plateau;
use App\Entity\Room;
use App\Entity\ZebrafishBatch;
use App\Entity\ZebrafishLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MortalityRecord>
 *
 * @method MortalityRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method MortalityRecord|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method MortalityRecord[]    findAll()
 * @method MortalityRecord[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class MortalityRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MortalityRecord::class);
    }

    /**
     * @return list<MortalityRecord>
     */
    public function findForBatch(ZebrafishBatch $batch): array
    {
        return $this->createQueryBuilder('mortality')
            ->andWhere('mortality.batch = :batch')
            ->setParameter('batch', $batch)
            ->orderBy('mortality.date', 'ASC')
            ->addOrderBy('mortality.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function sumCountForBatch(ZebrafishBatch $batch): int
    {
        return (int) $this->createQueryBuilder('mortality')
            ->select('COALESCE(SUM(mortality.count), 0)')
            ->andWhere('mortality.batch = :batch')
            ->setParameter('batch', $batch)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function sumCountForLine(Account $account, ZebrafishLine $line): int
    {
        return (int) $this->createQueryBuilder('mortality')
            ->select('COALESCE(SUM(mortality.count), 0)')
            ->innerJoin('mortality.batch', 'batch')
            ->andWhere('mortality.account = :account')
            ->andWhere('batch.line = :line')
            ->setParameter('account', $account)
            ->setParameter('line', $line)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function sumCountForRoom(Account $account, Room $room): int
    {
        return (int) $this->createQueryBuilder('mortality')
            ->select('COALESCE(SUM(mortality.count), 0)')
            ->innerJoin('mortality.batch', 'batch')
            ->innerJoin('batch.pool', 'pool')
            ->innerJoin('pool.rack', 'rack')
            ->andWhere('mortality.account = :account')
            ->andWhere('rack.room = :room')
            ->setParameter('account', $account)
            ->setParameter('room', $room)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function sumCountForPlateau(Account $account, Plateau $plateau): int
    {
        return (int) $this->createQueryBuilder('mortality')
            ->select('COALESCE(SUM(mortality.count), 0)')
            ->innerJoin('mortality.batch', 'batch')
            ->innerJoin('batch.pool', 'pool')
            ->innerJoin('pool.rack', 'rack')
            ->innerJoin('rack.room', 'room')
            ->andWhere('mortality.account = :account')
            ->andWhere('room.plateau = :plateau')
            ->setParameter('account', $account)
            ->setParameter('plateau', $plateau)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
