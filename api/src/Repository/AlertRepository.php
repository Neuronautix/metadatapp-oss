<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Alert;
use App\Entity\Room;
use App\Entity\System;
use App\Entity\ZebrafishBatch;
use App\Enum\AlertStatus;
use App\Enum\AlertType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alert>
 *
 * @method Alert|null find($id, $lockMode = null, $lockVersion = null)
 * @method Alert|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method Alert[]    findAll()
 * @method Alert[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    /**
     * @return list<Alert>
     */
    public function findForAccount(Account $account, ?AlertStatus $status = null): array
    {
        $queryBuilder = $this->createQueryBuilder('alert')
            ->leftJoin('alert.system', 'system')->addSelect('system')
            ->leftJoin('alert.room', 'room')->addSelect('room')
            ->leftJoin('alert.batch', 'batch')->addSelect('batch')
            ->leftJoin('batch.line', 'line')->addSelect('line')
            ->andWhere('alert.account = :account')
            ->setParameter('account', $account)
            ->orderBy('alert.createdAt', 'DESC')
            ->addOrderBy('alert.id', 'DESC')
        ;

        if ($status instanceof AlertStatus) {
            $queryBuilder
                ->andWhere('alert.status = :status')
                ->setParameter('status', $status)
            ;
        }

        /** @var list<Alert> */
        return $queryBuilder->getQuery()->getResult();
    }

    public function findUnresolvedForScope(
        Account $account,
        AlertType $type,
        ?System $system,
        ?Room $room,
        ?ZebrafishBatch $batch,
    ): ?Alert {
        $queryBuilder = $this->createQueryBuilder('alert')
            ->andWhere('alert.account = :account')
            ->andWhere('alert.type = :type')
            ->andWhere('alert.status IN (:statuses)')
            ->setParameter('account', $account)
            ->setParameter('type', $type)
            ->setParameter('statuses', [AlertStatus::ACTIVE, AlertStatus::ACKNOWLEDGED])
            ->setMaxResults(1)
        ;

        if ($system instanceof System) {
            $queryBuilder
                ->andWhere('alert.system = :system')
                ->setParameter('system', $system)
            ;
        } else {
            $queryBuilder->andWhere('alert.system IS NULL');
        }

        if ($room instanceof Room) {
            $queryBuilder
                ->andWhere('alert.room = :room')
                ->setParameter('room', $room)
            ;
        } else {
            $queryBuilder->andWhere('alert.room IS NULL');
        }

        if ($batch instanceof ZebrafishBatch) {
            $queryBuilder
                ->andWhere('alert.batch = :batch')
                ->setParameter('batch', $batch)
            ;
        } else {
            $queryBuilder->andWhere('alert.batch IS NULL');
        }

        /** @var Alert|null */
        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function countActiveForSystem(System $system): int
    {
        return (int) $this->createQueryBuilder('alert')
            ->select('COUNT(alert.id)')
            ->leftJoin('alert.batch', 'batch')
            ->leftJoin('batch.pool', 'pool')
            ->leftJoin('pool.rack', 'rack')
            ->andWhere('alert.status = :status')
            ->andWhere('(alert.system = :system OR rack.system = :system)')
            ->setParameter('status', AlertStatus::ACTIVE)
            ->setParameter('system', $system)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Returns the active alert count per system code for a set of systems.
     * Uses a single grouped query to avoid N+1 issues in collection endpoints.
     *
     * @param list<System> $systems
     *
     * @return array<string, int> keyed by system code
     */
    public function countActivePerSystem(array $systems): array
    {
        if ([] === $systems) {
            return [];
        }

        $directRows = $this->createQueryBuilder('alert')
            ->select('IDENTITY(alert.system) AS systemId, COUNT(alert.id) AS cnt')
            ->andWhere('alert.status = :status')
            ->andWhere('alert.system IN (:systems)')
            ->setParameter('status', AlertStatus::ACTIVE)
            ->setParameter('systems', $systems)
            ->groupBy('alert.system')
            ->getQuery()
            ->getResult()
        ;

        $batchRows = $this->createQueryBuilder('alert')
            ->select('IDENTITY(rack.system) AS systemId, COUNT(alert.id) AS cnt')
            ->leftJoin('alert.batch', 'batch')
            ->leftJoin('batch.pool', 'pool')
            ->leftJoin('pool.rack', 'rack')
            ->andWhere('alert.status = :status')
            ->andWhere('rack.system IN (:systems)')
            ->andWhere('alert.system IS NULL')
            ->setParameter('status', AlertStatus::ACTIVE)
            ->setParameter('systems', $systems)
            ->groupBy('rack.system')
            ->getQuery()
            ->getResult()
        ;

        $result = [];
        foreach ([...$directRows, ...$batchRows] as $row) {
            if (null === $row['systemId']) {
                continue;
            }

            $systemId = (string) $row['systemId'];
            $result[$systemId] = ($result[$systemId] ?? 0) + (int) $row['cnt'];
        }

        $systemCodeById = [];
        foreach ($systems as $system) {
            $id = $system->getId()?->toRfc4122();
            if (null !== $id) {
                $systemCodeById[$id] = $system->getCode();
            }
        }

        $byCode = [];
        foreach ($result as $id => $count) {
            $code = $systemCodeById[$id] ?? null;
            if (null !== $code) {
                $byCode[$code] = $count;
            }
        }

        return $byCode;
    }
}
