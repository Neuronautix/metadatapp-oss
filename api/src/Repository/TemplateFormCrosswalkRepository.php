<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TemplateFormCrosswalk;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TemplateFormCrosswalk>
 *
 * @method TemplateFormCrosswalk|null find($id, $lockMode = null, $lockVersion = null)
 * @method TemplateFormCrosswalk|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method TemplateFormCrosswalk[]    findAll()
 * @method TemplateFormCrosswalk[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class TemplateFormCrosswalkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TemplateFormCrosswalk::class);
    }
}
