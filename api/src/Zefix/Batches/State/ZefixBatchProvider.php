<?php

declare(strict_types=1);

namespace App\Zefix\Batches\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\MortalityRecord;
use App\Entity\User;
use App\Entity\ZebrafishBatch;
use App\Entity\ZebrafishLine;
use App\Zefix\Batches\ZefixBatchDetail;
use App\Zefix\Batches\ZefixBatchLine;
use App\Zefix\Batches\ZefixBatchLocation;
use App\Zefix\Batches\ZefixBatchMortalityEvent;
use App\Zefix\Batches\ZefixBatchSexRatio;
use App\Zefix\Batches\ZefixBatchSummary;
use App\Zefix\Batches\ZefixBatchTimelinePoint;
use App\Zefix\Batches\ZefixBatchView;
use App\Zefix\Support\ZefixBatchStatistics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<ZefixBatchView>
 */
final readonly class ZefixBatchProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
        private ZefixBatchStatistics $statistics,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|ZefixBatchDetail
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User is not authenticated.');
        }

        if (isset($uriVariables['batchID'])) {
            return $this->provideDetail((string) $uriVariables['batchID'], $user);
        }

        return $this->provideCollection($user);
    }

    /**
     * @return list<ZefixBatchSummary>
     */
    private function provideCollection(User $user): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $search = trim((string) $request?->query->get('search', ''));

        $queryBuilder = $this->entityManager->getRepository(ZebrafishBatch::class)
            ->createQueryBuilder('batch')
            ->innerJoin('batch.line', 'line')->addSelect('line')
            ->innerJoin('batch.pool', 'pool')->addSelect('pool')
            ->leftJoin('batch.mortalityRecords', 'mortalityRecord')->addSelect('mortalityRecord')
            ->innerJoin('pool.rack', 'rack')->addSelect('rack')
            ->innerJoin('rack.room', 'room')->addSelect('room')
            ->innerJoin('room.plateau', 'plateau')->addSelect('plateau')
            ->andWhere('batch.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('batch.birthDate', 'DESC')
            ->addOrderBy('line.name', 'ASC')
        ;

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(CONCAT(batch.id, \' \', COALESCE(line.name, \'\'), \' \', COALESCE(room.name, \'\'), \' \', COALESCE(rack.code, \'\'), \' \', COALESCE(pool.position, \'\'), \' \', COALESCE(plateau.code, \'\'))) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%')
            ;
        }

        /** @var list<ZebrafishBatch> $batches */
        $batches = $queryBuilder->getQuery()->getResult();

        return array_map($this->buildSummary(...), $batches);
    }

    private function provideDetail(string $batchId, User $user): ZefixBatchDetail
    {
        $batch = $this->entityManager->getRepository(ZebrafishBatch::class)
            ->createQueryBuilder('batch')
            ->innerJoin('batch.line', 'line')->addSelect('line')
            ->innerJoin('batch.pool', 'pool')->addSelect('pool')
            ->leftJoin('batch.mortalityRecords', 'mortalityRecord')->addSelect('mortalityRecord')
            ->innerJoin('pool.rack', 'rack')->addSelect('rack')
            ->innerJoin('rack.room', 'room')->addSelect('room')
            ->innerJoin('room.plateau', 'plateau')->addSelect('plateau')
            ->andWhere('batch.account = :account')
            ->andWhere('batch.id = :batchId')
            ->setParameter('account', $user->getAccount())
            ->setParameter('batchId', $batchId)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$batch instanceof ZebrafishBatch) {
            throw new NotFoundHttpException('Zefix batch not found.');
        }

        $summary = $this->buildSummary($batch);

        $mortalityRecords = $batch->getMortalityRecords()->toArray();
        usort(
            $mortalityRecords,
            static fn (MortalityRecord $left, MortalityRecord $right): int => $left->getDate() <=> $right->getDate()
        );

        $timeline = [
            new ZefixBatchTimelinePoint(
                id: $summary->batchID . '-birth',
                title: 'Birth',
                subtitle: $summary->initialCount . ' fish',
                timestamp: $batch->getBirthDate()->format(\DATE_ATOM),
                color: 'green',
            ),
        ];

        foreach ($mortalityRecords as $record) {
            $timeline[] = new ZefixBatchTimelinePoint(
                id: $record->getId()?->toRfc4122() ?? '',
                title: 'Mortality',
                subtitle: $record->getCount() . ' · ' . $record->getReason()->value,
                timestamp: $record->getDate()->format(\DATE_ATOM),
                color: $record->getCount() >= 10 ? 'red' : 'amber',
            );
        }

        return new ZefixBatchDetail(
            batchID: $summary->batchID,
            batch: $summary,
            line: $this->buildLine($batch->getLine()),
            location: $this->buildLocation($batch),
            timeline: $timeline,
            mortalityEvents: array_map(
                fn (MortalityRecord $record): ZefixBatchMortalityEvent => new ZefixBatchMortalityEvent(
                    id: $record->getId()?->toRfc4122() ?? '',
                    type: 'mortality',
                    date: $record->getDate()->format(\DATE_ATOM),
                    number: $record->getCount(),
                    reason: $record->getReason()->value,
                    notes: $record->getNotes(),
                    recorder: $this->formatRecorder($record->getRecorder()),
                ),
                $mortalityRecords
            ),
        );
    }

    private function buildSummary(ZebrafishBatch $batch): ZefixBatchSummary
    {
        $pool = $batch->getPool();
        $rack = $pool->getRack();
        $room = $rack->getRoom();
        $plateau = $room->getPlateau();
        $systemID = $rack->getSystem()?->getCode() ?? $rack->getCode();
        $ageDays = max(0, $batch->getBirthDate()->diff(new \DateTimeImmutable('today'))->days);

        $mortalityRecords = $batch->getMortalityRecords()->toArray();
        usort(
            $mortalityRecords,
            static fn (MortalityRecord $left, MortalityRecord $right): int => $left->getDate() <=> $right->getDate()
        );

        return new ZefixBatchSummary(
            batchID: $batch->getId()?->toRfc4122() ?? '',
            lineID: $batch->getLine()->getId()?->toRfc4122() ?? '',
            birthDate: $batch->getBirthDate()->format('Y-m-d'),
            sexRatio: new ZefixBatchSexRatio(
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
                    'notes' => $record->getNotes(),
                    'recorder' => $this->formatRecorder($record->getRecorder()),
                ],
                $mortalityRecords
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

    private function buildLine(ZebrafishLine $line): ZefixBatchLine
    {
        return new ZefixBatchLine(
            lineID: $line->getId()?->toRfc4122() ?? '',
            name: $line->getName(),
            genotype: $line->getGenotype() ?? 'Not specified',
            origin: 'Persisted Zefix colony',
            description: $line->getPurpose() ?? 'No description provided.',
            creationDate: $line->getCreatedAt()->format(\DATE_ATOM),
            responsibleScientist: 'Unassigned',
        );
    }

    private function buildLocation(ZebrafishBatch $batch): ZefixBatchLocation
    {
        $pool = $batch->getPool();
        $rack = $pool->getRack();
        $room = $rack->getRoom();
        $systemID = $rack->getSystem()?->getCode() ?? $rack->getCode();

        return new ZefixBatchLocation(
            locationId: $pool->getId()?->toRfc4122() ?? '',
            facility: $batch->getAccount()->getName(),
            roomID: $room->getId()?->toRfc4122() ?? '',
            roomName: $room->getName(),
            systemID: $systemID,
            rack: $rack->getCode(),
            tank: $pool->getPosition(),
        );
    }

    private function formatRecorder(User $recorder): string
    {
        return $recorder->getName() ?? $recorder->getEmail();
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
}
