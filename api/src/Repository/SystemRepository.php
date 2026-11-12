<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Room;
use App\Entity\System;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<System>
 *
 * @method System|null find($id, $lockMode = null, $lockVersion = null)
 * @method System|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method System[]    findAll()
 * @method System[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class SystemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, System::class);
    }

    /**
     * @return list<System>
     */
    public function findForRoom(Room $room): array
    {
        return $this->createQueryBuilder('system')
            ->leftJoin('system.racks', 'rack')
            ->andWhere('system.room = :room OR rack.room = :room')
            ->setParameter('room', $room)
            ->orderBy('system.code', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
