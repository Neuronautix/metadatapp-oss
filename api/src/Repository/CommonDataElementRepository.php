<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\CommonDataElement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommonDataElement>
 *
 * @method CommonDataElement|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommonDataElement|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method CommonDataElement[]    findAll()
 * @method CommonDataElement[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class CommonDataElementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommonDataElement::class);
    }

    public function findOneByLocalKey(Account $account, string $localKey): ?CommonDataElement
    {
        return $this->findOneBy(['account' => $account, 'localKey' => $localKey]);
    }
}
