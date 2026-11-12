<?php

declare(strict_types=1);

namespace App\Zefix\Lines\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\MortalityRecord;
use App\Entity\User;
use App\Entity\ZebrafishBatch;
use App\Entity\ZebrafishLine;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Enum\ZebrafishLineStatus;
use App\Zefix\Lines\ZefixLineBatchSexRatio;
use App\Zefix\Lines\ZefixLineBatchView;
use App\Zefix\Lines\ZefixLineDetail;
use App\Zefix\Lines\ZefixLineSummary;
use App\Zefix\Lines\ZefixLineView;
use App\Zefix\Support\ZefixBatchStatistics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<ZefixLineView>
 */
final readonly class ZefixLineProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
        private ZefixBatchStatistics $statistics,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|ZefixLineDetail
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User is not authenticated.');
        }

        if (isset($uriVariables['lineID'])) {
            return $this->provideDetail((string) $uriVariables['lineID'], $user);
        }

        return $this->provideCollection($user);
    }

    /**
     * @return list<ZefixLineSummary>
     */
    private function provideCollection(User $user): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $search = trim((string) $request?->query->get('search', ''));
        $status = trim((string) $request?->query->get('status', ''));

        $queryBuilder = $this->entityManager->getRepository(ZebrafishLine::class)
            ->createQueryBuilder('line')
            ->leftJoin('line.batches', 'batch')->addSelect('batch')
            ->leftJoin('batch.mortalityRecords', 'mortalityRecord')->addSelect('mortalityRecord')
            ->andWhere('line.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('line.name', 'ASC')
        ;

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(CONCAT(COALESCE(line.name, \'\'), \' \', COALESCE(line.genotype, \'\'), \' \', COALESCE(line.purpose, \'\'), \' \', line.status)) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%')
            ;
        }

        if ('' !== $status) {
            $lineStatus = ZebrafishLineStatus::tryFrom($status);
            if (null === $lineStatus) {
                return [];
            }

            $queryBuilder
                ->andWhere('line.status = :status')
                ->setParameter('status', $lineStatus)
            ;
        }

        /** @var list<ZebrafishLine> $lines */
        $lines = $queryBuilder->getQuery()->getResult();

        return array_map($this->buildSummary(...), $lines);
    }

    private function provideDetail(string $lineId, User $user): ZefixLineDetail
    {
        $line = $this->entityManager->getRepository(ZebrafishLine::class)
            ->createQueryBuilder('line')
            ->leftJoin('line.batches', 'batch')->addSelect('batch')
            ->leftJoin('batch.mortalityRecords', 'mortalityRecord')->addSelect('mortalityRecord')
            ->leftJoin('batch.pool', 'pool')->addSelect('pool')
            ->leftJoin('pool.rack', 'rack')->addSelect('rack')
            ->leftJoin('rack.room', 'room')->addSelect('room')
            ->leftJoin('room.plateau', 'plateau')->addSelect('plateau')
            ->andWhere('line.account = :account')
            ->andWhere('line.id = :lineId')
            ->setParameter('account', $user->getAccount())
            ->setParameter('lineId', $lineId)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$line instanceof ZebrafishLine) {
            throw new NotFoundHttpException('Zefix line not found.');
        }

        $batches = $line->getBatches()->toArray();
        usort(
            $batches,
            static fn (ZebrafishBatch $left, ZebrafishBatch $right): int => $right->getBirthDate() <=> $left->getBirthDate()
        );

        return new ZefixLineDetail(
            lineID: $line->getId()?->toRfc4122() ?? '',
            line: $this->buildSummary($line),
            batches: array_map($this->buildBatchView(...), $batches),
        );
    }

    private function buildSummary(ZebrafishLine $line): ZefixLineSummary
    {
        $relatedBatches = $line->getBatches()->toArray();
        $totalAnimals = array_reduce($relatedBatches, fn (int $sum, ZebrafishBatch $batch): int => $sum + $this->statistics->getCurrentPopulation($batch), 0);
        $activeBatches = array_reduce(
            $relatedBatches,
            function (int $count, ZebrafishBatch $batch): int {
                if ($this->statistics->getCurrentPopulation($batch) <= 0) {
                    return $count;
                }

                return \in_array(
                    $batch->getLifecycleStatus(),
                    [ZebrafishBatchLifecycleStatus::ACTIVE, ZebrafishBatchLifecycleStatus::QUARANTINED],
                    true
                ) ? $count + 1 : $count;
            },
            0
        );
        $initialPopulation = array_reduce($relatedBatches, fn (int $sum, ZebrafishBatch $batch): int => $sum + $this->statistics->getInitialPopulation($batch), 0);
        $mortalityCount = array_reduce($relatedBatches, fn (int $sum, ZebrafishBatch $batch): int => $sum + $this->statistics->getMortalityCount($batch), 0);
        $mortalityRate = 0 === $initialPopulation ? 0.0 : round(($mortalityCount / $initialPopulation) * 100, 1);

        $reproductionStatus = 'Stable';
        if (0 === $activeBatches || \in_array($line->getStatus(), [ZebrafishLineStatus::PAUSED, ZebrafishLineStatus::ARCHIVED], true)) {
            $reproductionStatus = 'Paused';
        } elseif ($activeBatches >= 4) {
            $reproductionStatus = 'Breeding';
        } elseif ($mortalityRate >= 14.0) {
            $reproductionStatus = 'Watch';
        }

        return new ZefixLineSummary(
            lineID: $line->getId()?->toRfc4122() ?? '',
            name: $line->getName(),
            genotype: $line->getGenotype() ?? 'Not specified',
            origin: 'Persisted Zefix colony',
            description: $line->getPurpose() ?? 'No description provided.',
            creationDate: $line->getCreatedAt()->format(\DATE_ATOM),
            responsibleScientist: 'Unassigned',
            totalAnimals: $totalAnimals,
            activeBatches: $activeBatches,
            mortalityRate: $mortalityRate,
            reproductionStatus: $reproductionStatus,
        );
    }

    private function buildBatchView(ZebrafishBatch $batch): ZefixLineBatchView
    {
        $pool = $batch->getPool();
        $rack = $pool->getRack();
        $room = $rack->getRoom();
        $plateau = $room->getPlateau();
        $systemID = $rack->getSystem()?->getCode() ?? $rack->getCode();
        $ageDays = max(0, $batch->getBirthDate()->diff(new \DateTimeImmutable('today'))->days);

        $mortalityEvents = $batch->getMortalityRecords()->toArray();
        usort(
            $mortalityEvents,
            static fn (MortalityRecord $left, MortalityRecord $right): int => $left->getDate() <=> $right->getDate()
        );

        return new ZefixLineBatchView(
            batchID: $batch->getId()?->toRfc4122() ?? '',
            lineID: $batch->getLine()->getId()?->toRfc4122() ?? '',
            birthDate: $batch->getBirthDate()->format('Y-m-d'),
            sexRatio: new ZefixLineBatchSexRatio(
                male: $batch->getMaleCount(),
                female: $batch->getFemaleCount(),
                unknown: $batch->getUnknownSexCount(),
            ),
            initialCount: $this->statistics->getInitialPopulation($batch),
            individualCount: $this->statistics->getCurrentPopulation($batch),
            locationId: $pool->getId()?->toRfc4122() ?? '',
            systemID: $systemID,
            roomID: $room->getId()?->toRfc4122() ?? '',
            events: array_map(
                fn (MortalityRecord $record): array => [
                    'id' => $record->getId()?->toRfc4122() ?? '',
                    'type' => 'mortality',
                    'date' => $record->getDate()->format(\DATE_ATOM),
                    'number' => $record->getCount(),
                    'reason' => $record->getReason()->value,
                    'recorder' => $this->formatRecorder($record->getRecorder()),
                ],
                $mortalityEvents
            ),
            lineName: $batch->getLine()->getName(),
            roomName: $room->getName(),
            tankLocation: \sprintf(
                '%s / %s / %s / %s / %s',
                $batch->getAccount()->getName(),
                $plateau->getCode(),
                $room->getName(),
                $rack->getCode(),
                $pool->getPosition(),
            ),
            ageDays: $ageDays,
            ageLabel: $this->formatAgeLabel($ageDays),
            mortalityCount: $this->statistics->getMortalityCount($batch),
        );
    }

    private function formatAgeLabel(int $ageDays): string
    {
        if ($ageDays < 31) {
            return \sprintf('%d days', $ageDays);
        }

        if ($ageDays < 365) {
            return \sprintf('%d months', max(1, (int) floor($ageDays / 30)));
        }

        return \sprintf('%d years', max(1, (int) floor($ageDays / 365)));
    }

    private function formatRecorder(User $recorder): string
    {
        return $recorder->getName() ?? $recorder->getEmail();
    }
}
