<?php

declare(strict_types=1);

namespace App\Zefix\Mortality\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\MortalityRecord;
use App\Entity\User;
use App\Zefix\Mortality\ZefixMortalityTrendPoint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<ZefixMortalityTrendPoint>
 */
final readonly class ZefixMortalityTrendProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User is not authenticated.');
        }

        $request = $this->requestStack->getCurrentRequest();
        $lineId = trim((string) $request?->query->get('lineID', ''));
        $roomId = trim((string) $request?->query->get('roomID', ''));
        $plateauId = trim((string) $request?->query->get('plateauID', ''));
        $dateFrom = trim((string) $request?->query->get('dateFrom', ''));
        $dateTo = trim((string) $request?->query->get('dateTo', ''));

        $queryBuilder = $this->entityManager->getRepository(MortalityRecord::class)
            ->createQueryBuilder('record')
            ->innerJoin('record.batch', 'batch')
            ->innerJoin('batch.line', 'line')
            ->innerJoin('batch.pool', 'pool')
            ->innerJoin('pool.rack', 'rack')
            ->innerJoin('rack.room', 'room')
            ->innerJoin('room.plateau', 'plateau')
            ->andWhere('record.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('record.date', 'ASC')
            ->addOrderBy('record.id', 'ASC')
        ;

        if ('' !== $lineId) {
            $queryBuilder
                ->andWhere('line.id = :lineId')
                ->setParameter('lineId', $lineId)
            ;
        }

        if ('' !== $roomId) {
            $queryBuilder
                ->andWhere('room.id = :roomId')
                ->setParameter('roomId', $roomId)
            ;
        }

        if ('' !== $plateauId) {
            $queryBuilder
                ->andWhere('plateau.id = :plateauId')
                ->setParameter('plateauId', $plateauId)
            ;
        }

        if ('' !== $dateFrom) {
            $queryBuilder
                ->andWhere('record.date >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom))
            ;
        }

        if ('' !== $dateTo) {
            $queryBuilder
                ->andWhere('record.date <= :dateTo')
                ->setParameter('dateTo', (new \DateTimeImmutable($dateTo))->setTime(23, 59, 59))
            ;
        }

        /** @var list<MortalityRecord> $records */
        $records = $queryBuilder->getQuery()->getResult();

        $countsByDate = [];
        foreach ($records as $record) {
            $dateKey = $record->getDate()->format('Y-m-d');
            $countsByDate[$dateKey] = ($countsByDate[$dateKey] ?? 0) + $record->getCount();
        }

        ksort($countsByDate);

        $trendPoints = [];
        foreach ($countsByDate as $date => $mortalities) {
            $trendPoints[] = new ZefixMortalityTrendPoint(
                date: $date,
                mortalities: $mortalities,
            );
        }

        return $trendPoints;
    }
}
