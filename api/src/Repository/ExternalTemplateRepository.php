<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExternalTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExternalTemplate>
 *
 * @method ExternalTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExternalTemplate|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method ExternalTemplate[]    findAll()
 * @method ExternalTemplate[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class ExternalTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalTemplate::class);
    }

    public function findOneByFileHash(string $fileHash): ?ExternalTemplate
    {
        return $this->findOneBy(['fileHash' => $fileHash]);
    }
}
