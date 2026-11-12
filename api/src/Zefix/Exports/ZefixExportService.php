<?php

declare(strict_types=1);

namespace App\Zefix\Exports;

use App\Entity\CryoRecord;
use App\Entity\MortalityRecord;
use App\Entity\Plateau;
use App\Entity\Pool;
use App\Entity\Rack;
use App\Entity\Room;
use App\Entity\User;
use App\Entity\ZebrafishBatch;
use App\Entity\ZebrafishLine;
use App\Enum\CryoRecordStatus;
use App\Enum\PoolStatus;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Enum\ZebrafishLineStatus;
use App\Zefix\Support\ZefixBatchStatistics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class ZefixExportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ZefixBatchStatistics $statistics,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportPoolOccupancy(User $user, array $filters): Response
    {
        $rows = $this->buildPoolOccupancyRows($user, $filters);

        return $this->csvResponse(
            'zefix-pool-occupancy.csv',
            ['plateau', 'room', 'rack', 'pool', 'system', 'status', 'occupied', 'batchId', 'lineName', 'lifecycleStatus', 'population', 'capacity', 'occupancyPercent'],
            $rows,
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportBatchHistory(User $user, array $filters): Response
    {
        $rows = $this->buildBatchHistoryRows($user, $filters);

        return $this->csvResponse(
            'zefix-batch-history.csv',
            ['batchId', 'lineName', 'birthDate', 'ageDays', 'ageLabel', 'lifecycleStatus', 'population', 'initialPopulation', 'mortalityCount', 'system', 'room', 'tankLocation'],
            $rows,
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportLineStatistics(User $user, array $filters): Response
    {
        $rows = $this->buildLineStatisticsRows($user, $filters);

        return $this->csvResponse(
            'zefix-line-statistics.csv',
            ['lineId', 'name', 'genotype', 'status', 'totalAnimals', 'activeBatches', 'mortalityRate', 'reproductionStatus', 'createdAt', 'batchCount', 'mortalityCount'],
            $rows,
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportMortalityLogs(User $user, array $filters): Response
    {
        $rows = $this->buildMortalityRows($user, $filters);

        return $this->csvResponse(
            'zefix-mortality-logs.csv',
            ['date', 'batchId', 'lineName', 'count', 'reason', 'recorder', 'system', 'room', 'rack', 'pool', 'notes'],
            $rows,
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportCryoInventory(User $user, array $filters): Response
    {
        $rows = $this->buildCryoInventoryRows($user, $filters);

        return $this->csvResponse(
            'zefix-cryo-inventory.csv',
            ['cryoId', 'lineId', 'lineName', 'storageDate', 'storageLocation', 'protocolUsed', 'status', 'notes'],
            $rows,
        );
    }

    public function exportLineReport(User $user, string $lineID): Response
    {
        $line = $this->loadLineForReport($user, $lineID);
        $summary = $this->summarizeLine($line);
        $batches = $line->getBatches()->toArray();
        usort(
            $batches,
            static fn (ZebrafishBatch $left, ZebrafishBatch $right): int => $right->getBirthDate() <=> $left->getBirthDate()
        );

        $lines = [
            \sprintf('Line ID: %s', $summary['lineId']),
            \sprintf('Name: %s', $summary['name']),
            \sprintf('Genotype: %s', $summary['genotype']),
            \sprintf('Status: %s', $summary['status']),
            \sprintf('Created: %s', $summary['createdAt']),
            \sprintf('Responsible scientist: %s', $summary['responsibleScientist']),
            \sprintf('Current animals: %d', $summary['totalAnimals']),
            \sprintf('Active batches: %d', $summary['activeBatches']),
            \sprintf('Mortality rate: %.1f%%', $summary['mortalityRate']),
            \sprintf('Reproduction status: %s', $summary['reproductionStatus']),
            '',
            'Latest batches:',
        ];

        foreach (\array_slice($batches, 0, 8) as $batch) {
            $batchSummary = $this->summarizeBatch($batch);
            $lines[] = \sprintf(
                '%s | %s | %s fish | %s mortalities | %s',
                $batchSummary['batchId'],
                $batchSummary['ageLabel'],
                $batchSummary['population'],
                $batchSummary['mortalityCount'],
                $batchSummary['tankLocation'],
            );
        }

        $lines[] = '';
        $lines[] = 'Generated from persisted ZEFIX data.';

        return $this->pdfResponse(
            \sprintf('zefix-line-report-%s.pdf', $summary['lineId']),
            \sprintf('ZEFIX line report - %s', $summary['name']),
            $lines,
        );
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, int|float|string>>
     */
    private function buildPoolOccupancyRows(User $user, array $filters): array
    {
        $search = $this->normalizedSearch($filters['search'] ?? '');
        $plateauId = $this->parseUuidFilter($filters['plateauID'] ?? null, 'plateauID');
        $roomId = $this->parseUuidFilter($filters['roomID'] ?? null, 'roomID');
        $rackId = $this->parseUuidFilter($filters['rackID'] ?? null, 'rackID');
        $poolId = $this->parseUuidFilter($filters['poolID'] ?? null, 'poolID');
        $status = $this->enumOrNull(PoolStatus::class, $filters['status'] ?? null);

        if ('' !== trim((string) ($filters['status'] ?? '')) && null === $status) {
            return [];
        }

        $queryBuilder = $this->entityManager->getRepository(Plateau::class)
            ->createQueryBuilder('plateau')
            ->leftJoin('plateau.rooms', 'room')->addSelect('room')
            ->leftJoin('room.racks', 'rack')->addSelect('rack')
            ->leftJoin('rack.pools', 'pool')->addSelect('pool')
            ->leftJoin('pool.batches', 'batch', 'WITH', 'batch.lifecycleStatus IN (:occupyingStatuses)')->addSelect('batch')
            ->leftJoin('batch.line', 'line')->addSelect('line')
            ->andWhere('plateau.account = :account')
            ->setParameter('account', $user->getAccount())
            ->setParameter('occupyingStatuses', [ZebrafishBatchLifecycleStatus::ACTIVE, ZebrafishBatchLifecycleStatus::QUARANTINED])
            ->orderBy('plateau.name', 'ASC')
            ->addOrderBy('room.name', 'ASC')
            ->addOrderBy('rack.code', 'ASC')
            ->addOrderBy('pool.position', 'ASC')
        ;

        if (null !== $plateauId) {
            $queryBuilder->andWhere('plateau.id = :plateauId')->setParameter('plateauId', $plateauId);
        }

        if (null !== $roomId) {
            $queryBuilder->andWhere('room.id = :roomId')->setParameter('roomId', $roomId);
        }

        if (null !== $rackId) {
            $queryBuilder->andWhere('rack.id = :rackId')->setParameter('rackId', $rackId);
        }

        if (null !== $poolId) {
            $queryBuilder->andWhere('pool.id = :poolId')->setParameter('poolId', $poolId);
        }

        if (null !== $status) {
            $queryBuilder->andWhere('pool.status = :status')->setParameter('status', $status);
        }

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(CONCAT(COALESCE(plateau.name, \'\'), \' \', COALESCE(plateau.code, \'\'), \' \', COALESCE(room.name, \'\'), \' \', COALESCE(rack.code, \'\'), \' \', COALESCE(pool.position, \'\'), \' \', COALESCE(line.name, \'\'), \' \', COALESCE(batch.id, \'\'))) LIKE :search')
                ->setParameter('search', '%' . $search . '%')
            ;
        }

        /** @var list<Plateau> $plateaux */
        $plateaux = $queryBuilder->getQuery()->getResult();

        $rows = [];
        foreach ($plateaux as $plateau) {
            foreach ($plateau->getRooms() as $room) {
                foreach ($room->getRacks() as $rack) {
                    foreach ($rack->getPools() as $pool) {
                        if (null !== $status && $pool->getStatus() !== $status) {
                            continue;
                        }

                        $batch = $this->occupyingBatchForPool($pool);
                        if (null !== $poolId && $pool->getId()?->toRfc4122() !== $poolId->toRfc4122()) {
                            continue;
                        }

                        if (null !== $roomId && $room->getId()?->toRfc4122() !== $roomId->toRfc4122()) {
                            continue;
                        }

                        if (null !== $rackId && $rack->getId()?->toRfc4122() !== $rackId->toRfc4122()) {
                            continue;
                        }

                        if (null !== $plateauId && $plateau->getId()?->toRfc4122() !== $plateauId->toRfc4122()) {
                            continue;
                        }

                        if ('' !== $search && !$this->poolMatchesSearch($plateau, $room, $rack, $pool, $batch, $search)) {
                            continue;
                        }

                        $population = null !== $batch ? $batch->getTotalPopulation() : 0;
                        $capacity = $pool->getCapacity();
                        $rows[] = [
                            'plateau' => $plateau->getCode(),
                            'room' => $room->getName(),
                            'rack' => $rack->getCode(),
                            'pool' => $pool->getPosition(),
                            'system' => $rack->getSystem()?->getCode() ?? '',
                            'status' => $pool->getStatus()->value,
                            'occupied' => null !== $batch ? 'yes' : 'no',
                            'batchId' => $batch?->getId()?->toRfc4122() ?? '',
                            'lineName' => $batch?->getLine()->getName() ?? '',
                            'lifecycleStatus' => $batch?->getLifecycleStatus()->value ?? '',
                            'population' => $population,
                            'capacity' => $capacity,
                            'occupancyPercent' => 0 === $capacity ? 0.0 : round(($population / $capacity) * 100, 1),
                        ];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, int|float|string>>
     */
    private function buildBatchHistoryRows(User $user, array $filters): array
    {
        $search = $this->normalizedSearch($filters['search'] ?? '');
        $lineId = $this->parseUuidFilter($filters['lineID'] ?? null, 'lineID');
        $poolId = $this->parseUuidFilter($filters['poolID'] ?? null, 'poolID');
        $roomId = $this->parseUuidFilter($filters['roomID'] ?? null, 'roomID');
        $plateauId = $this->parseUuidFilter($filters['plateauID'] ?? null, 'plateauID');
        $lifecycleStatus = $this->enumOrNull(ZebrafishBatchLifecycleStatus::class, $filters['lifecycleStatus'] ?? null);

        if ('' !== trim((string) ($filters['lifecycleStatus'] ?? '')) && null === $lifecycleStatus) {
            return [];
        }

        $queryBuilder = $this->entityManager->getRepository(ZebrafishBatch::class)
            ->createQueryBuilder('batch')
            ->innerJoin('batch.line', 'line')->addSelect('line')
            ->innerJoin('batch.pool', 'pool')->addSelect('pool')
            ->innerJoin('pool.rack', 'rack')->addSelect('rack')
            ->innerJoin('rack.room', 'room')->addSelect('room')
            ->innerJoin('room.plateau', 'plateau')->addSelect('plateau')
            ->leftJoin('batch.mortalityRecords', 'mortalityRecord')->addSelect('mortalityRecord')
            ->andWhere('batch.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('batch.birthDate', 'DESC')
            ->addOrderBy('line.name', 'ASC')
            ->addOrderBy('batch.id', 'ASC')
        ;

        if (null !== $lineId) {
            $queryBuilder->andWhere('line.id = :lineId')->setParameter('lineId', $lineId);
        }

        if (null !== $poolId) {
            $queryBuilder->andWhere('pool.id = :poolId')->setParameter('poolId', $poolId);
        }

        if (null !== $roomId) {
            $queryBuilder->andWhere('room.id = :roomId')->setParameter('roomId', $roomId);
        }

        if (null !== $plateauId) {
            $queryBuilder->andWhere('plateau.id = :plateauId')->setParameter('plateauId', $plateauId);
        }

        if (null !== $lifecycleStatus) {
            $queryBuilder->andWhere('batch.lifecycleStatus = :lifecycleStatus')->setParameter('lifecycleStatus', $lifecycleStatus);
        }

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(CONCAT(batch.id, \' \', COALESCE(line.name, \'\'), \' \', COALESCE(room.name, \'\'), \' \', COALESCE(rack.code, \'\'), \' \', COALESCE(pool.position, \'\'), \' \', COALESCE(plateau.code, \'\'), \' \', batch.lifecycleStatus)) LIKE :search')
                ->setParameter('search', '%' . $search . '%')
            ;
        }

        /** @var list<ZebrafishBatch> $batches */
        $batches = $queryBuilder->getQuery()->getResult();

        return array_map(function (ZebrafishBatch $batch): array {
            $summary = $this->summarizeBatch($batch);

            return [
                'batchId' => $summary['batchId'],
                'lineName' => $summary['lineName'],
                'birthDate' => $summary['birthDate'],
                'ageDays' => $summary['ageDays'],
                'ageLabel' => $summary['ageLabel'],
                'lifecycleStatus' => $summary['lifecycleStatus'],
                'population' => $summary['population'],
                'initialPopulation' => $summary['initialPopulation'],
                'mortalityCount' => $summary['mortalityCount'],
                'system' => $summary['system'],
                'room' => $summary['room'],
                'tankLocation' => $summary['tankLocation'],
            ];
        }, $batches);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, int|float|string>>
     */
    private function buildLineStatisticsRows(User $user, array $filters): array
    {
        $search = $this->normalizedSearch($filters['search'] ?? '');
        $status = $this->enumOrNull(ZebrafishLineStatus::class, $filters['status'] ?? null);

        if ('' !== trim((string) ($filters['status'] ?? '')) && null === $status) {
            return [];
        }

        $queryBuilder = $this->entityManager->getRepository(ZebrafishLine::class)
            ->createQueryBuilder('line')
            ->leftJoin('line.batches', 'batch')->addSelect('batch')
            ->leftJoin('batch.mortalityRecords', 'mortalityRecord')->addSelect('mortalityRecord')
            ->andWhere('line.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('line.name', 'ASC')
        ;

        if (null !== $status) {
            $queryBuilder->andWhere('line.status = :status')->setParameter('status', $status);
        }

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(CONCAT(COALESCE(line.name, \'\'), \' \', COALESCE(line.genotype, \'\'), \' \', COALESCE(line.purpose, \'\'), \' \', line.status)) LIKE :search')
                ->setParameter('search', '%' . $search . '%')
            ;
        }

        /** @var list<ZebrafishLine> $lines */
        $lines = $queryBuilder->getQuery()->getResult();

        return array_map(function (ZebrafishLine $line): array {
            $summary = $this->summarizeLine($line);

            return [
                'lineId' => $summary['lineId'],
                'name' => $summary['name'],
                'genotype' => $summary['genotype'],
                'status' => $summary['status'],
                'totalAnimals' => $summary['totalAnimals'],
                'activeBatches' => $summary['activeBatches'],
                'mortalityRate' => $summary['mortalityRate'],
                'reproductionStatus' => $summary['reproductionStatus'],
                'createdAt' => $summary['createdAt'],
                'batchCount' => $summary['batchCount'],
                'mortalityCount' => $summary['mortalityCount'],
            ];
        }, $lines);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, int|float|string>>
     */
    private function buildMortalityRows(User $user, array $filters): array
    {
        $search = $this->normalizedSearch($filters['search'] ?? '');
        $batchId = $this->parseUuidFilter($filters['batchID'] ?? null, 'batchID');
        $lineId = $this->parseUuidFilter($filters['lineID'] ?? null, 'lineID');
        $roomId = $this->parseUuidFilter($filters['roomID'] ?? null, 'roomID');
        $plateauId = $this->parseUuidFilter($filters['plateauID'] ?? null, 'plateauID');

        $queryBuilder = $this->entityManager->getRepository(MortalityRecord::class)
            ->createQueryBuilder('record')
            ->innerJoin('record.batch', 'batch')->addSelect('batch')
            ->innerJoin('batch.line', 'line')->addSelect('line')
            ->innerJoin('batch.pool', 'pool')->addSelect('pool')
            ->innerJoin('pool.rack', 'rack')->addSelect('rack')
            ->innerJoin('rack.room', 'room')->addSelect('room')
            ->innerJoin('room.plateau', 'plateau')->addSelect('plateau')
            ->innerJoin('record.user', 'recorder')->addSelect('recorder')
            ->andWhere('record.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('record.date', 'DESC')
            ->addOrderBy('record.id', 'DESC')
        ;

        if (null !== $batchId) {
            $queryBuilder->andWhere('batch.id = :batchId')->setParameter('batchId', $batchId);
        }

        if (null !== $lineId) {
            $queryBuilder->andWhere('line.id = :lineId')->setParameter('lineId', $lineId);
        }

        if (null !== $roomId) {
            $queryBuilder->andWhere('room.id = :roomId')->setParameter('roomId', $roomId);
        }

        if (null !== $plateauId) {
            $queryBuilder->andWhere('plateau.id = :plateauId')->setParameter('plateauId', $plateauId);
        }

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(CONCAT(record.count, \' \', COALESCE(record.notes, \'\'), \' \', record.reason, \' \', COALESCE(batch.id, \'\'), \' \', COALESCE(line.name, \'\'), \' \', COALESCE(room.name, \'\'), \' \', COALESCE(rack.code, \'\'), \' \', COALESCE(plateau.code, \'\'), \' \', COALESCE(recorder.name, \'\'), \' \', COALESCE(recorder.email, \'\'))) LIKE :search')
                ->setParameter('search', '%' . $search . '%')
            ;
        }

        /** @var list<MortalityRecord> $records */
        $records = $queryBuilder->getQuery()->getResult();

        return array_map(static fn (MortalityRecord $record): array => [
            'date' => $record->getDate()->format(\DATE_ATOM),
            'batchId' => $record->getBatch()->getId()?->toRfc4122() ?? '',
            'lineName' => $record->getBatch()->getLine()->getName(),
            'count' => $record->getCount(),
            'reason' => $record->getReason()->value,
            'recorder' => $record->getRecorder()->getName() ?? $record->getRecorder()->getEmail(),
            'system' => $record->getBatch()->getPool()->getRack()->getSystem()?->getCode() ?? '',
            'room' => $record->getBatch()->getPool()->getRack()->getRoom()->getName(),
            'rack' => $record->getBatch()->getPool()->getRack()->getCode(),
            'pool' => $record->getBatch()->getPool()->getPosition(),
            'notes' => $record->getNotes() ?? '',
        ], $records);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, int|float|string>>
     */
    private function buildCryoInventoryRows(User $user, array $filters): array
    {
        $search = $this->normalizedSearch($filters['search'] ?? '');
        $lineId = $this->parseUuidFilter($filters['lineID'] ?? null, 'lineID');
        $status = $this->enumOrNull(CryoRecordStatus::class, $filters['status'] ?? null);

        if ('' !== trim((string) ($filters['status'] ?? '')) && null === $status) {
            return [];
        }

        $queryBuilder = $this->entityManager->getRepository(CryoRecord::class)
            ->createQueryBuilder('cryo')
            ->innerJoin('cryo.line', 'line')->addSelect('line')
            ->andWhere('cryo.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('cryo.storageDate', 'DESC')
            ->addOrderBy('cryo.id', 'DESC')
        ;

        if (null !== $lineId) {
            $queryBuilder->andWhere('line.id = :lineId')->setParameter('lineId', $lineId);
        }

        if (null !== $status) {
            $queryBuilder->andWhere('cryo.status = :status')->setParameter('status', $status);
        }

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(CONCAT(COALESCE(line.name, \'\'), \' \', COALESCE(cryo.storageLocation, \'\'), \' \', COALESCE(cryo.protocolUsed, \'\'), \' \', COALESCE(cryo.notes, \'\'), \' \', cryo.status)) LIKE :search')
                ->setParameter('search', '%' . $search . '%')
            ;
        }

        /** @var list<CryoRecord> $records */
        $records = $queryBuilder->getQuery()->getResult();

        return array_map(static fn (CryoRecord $record): array => [
            'cryoId' => $record->getId()?->toRfc4122() ?? '',
            'lineId' => $record->getLine()?->getId()?->toRfc4122() ?? '',
            'lineName' => $record->getLine()?->getName() ?? '',
            'storageDate' => $record->getStorageDate()->format('Y-m-d'),
            'storageLocation' => $record->getStorageLocation(),
            'protocolUsed' => $record->getProtocolUsed(),
            'status' => $record->getStatus()->value,
            'notes' => $record->getNotes() ?? '',
        ], $records);
    }

    /**
     * @return array<string, int|float|string>
     */
    private function summarizeLine(ZebrafishLine $line): array
    {
        $relatedBatches = $line->getBatches()->toArray();
        $totalAnimals = array_reduce(
            $relatedBatches,
            fn (int $sum, ZebrafishBatch $batch): int => $sum + $this->statistics->getCurrentPopulation($batch),
            0
        );
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

        return [
            'lineId' => $line->getId()?->toRfc4122() ?? '',
            'name' => $line->getName(),
            'genotype' => $line->getGenotype() ?? 'Not specified',
            'origin' => 'Persisted Zefix colony',
            'description' => $line->getPurpose() ?? 'No description provided.',
            'createdAt' => $line->getCreatedAt()->format(\DATE_ATOM),
            'responsibleScientist' => 'Unassigned',
            'status' => $line->getStatus()->value,
            'totalAnimals' => $totalAnimals,
            'activeBatches' => $activeBatches,
            'mortalityRate' => $mortalityRate,
            'reproductionStatus' => $reproductionStatus,
            'batchCount' => \count($relatedBatches),
            'mortalityCount' => $mortalityCount,
        ];
    }

    /**
     * @return array<string, int|float|string>
     */
    private function summarizeBatch(ZebrafishBatch $batch): array
    {
        $pool = $batch->getPool();
        $rack = $pool->getRack();
        $room = $rack->getRoom();
        $plateau = $room->getPlateau();
        $systemID = $rack->getSystem()?->getCode() ?? $rack->getCode();
        $ageDays = max(0, $batch->getBirthDate()->diff(new \DateTimeImmutable('today'))->days);

        return [
            'batchId' => $batch->getId()?->toRfc4122() ?? '',
            'lineId' => $batch->getLine()->getId()?->toRfc4122() ?? '',
            'lineName' => $batch->getLine()->getName(),
            'birthDate' => $batch->getBirthDate()->format('Y-m-d'),
            'ageDays' => $ageDays,
            'ageLabel' => $this->formatAgeLabel($ageDays),
            'lifecycleStatus' => $batch->getLifecycleStatus()->value,
            'population' => $batch->getTotalPopulation(),
            'initialPopulation' => $this->statistics->getInitialPopulation($batch),
            'mortalityCount' => $this->statistics->getMortalityCount($batch),
            'system' => $systemID,
            'room' => $room->getName(),
            'rack' => $rack->getCode(),
            'pool' => $pool->getPosition(),
            'plateau' => $plateau->getCode(),
            'tankLocation' => \sprintf(
                '%s / %s / %s / %s / %s',
                $batch->getAccount()->getName(),
                $plateau->getCode(),
                $room->getName(),
                $rack->getCode(),
                $pool->getPosition(),
            ),
            'capacity' => $pool->getCapacity(),
            'occupancyPercent' => 0 === $pool->getCapacity() ? 0.0 : round(($batch->getTotalPopulation() / $pool->getCapacity()) * 100, 1),
        ];
    }

    private function loadLineForReport(User $user, string $lineID): ZebrafishLine
    {
        $uuid = $this->parseUuidOrFail($lineID, 'lineID');

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
            ->setParameter('lineId', $uuid)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$line instanceof ZebrafishLine) {
            throw new NotFoundHttpException('Zefix line not found.');
        }

        return $line;
    }

    private function occupyingBatchForPool(Pool $pool): ?ZebrafishBatch
    {
        foreach ($pool->getBatches() as $batch) {
            if (\in_array($batch->getLifecycleStatus(), [ZebrafishBatchLifecycleStatus::ACTIVE, ZebrafishBatchLifecycleStatus::QUARANTINED], true)) {
                return $batch;
            }
        }

        return null;
    }

    private function poolMatchesSearch(Plateau $plateau, Room $room, Rack $rack, Pool $pool, ?ZebrafishBatch $batch, string $search): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $plateau->getName(),
            $plateau->getCode(),
            $room->getName(),
            $rack->getCode(),
            $rack->getSystem()?->getCode(),
            $pool->getPosition(),
            $batch?->getId()?->toRfc4122(),
            $batch?->getLine()->getName(),
        ])));

        return str_contains($haystack, $search);
    }

    private function csvResponse(string $filename, array $headers, array $rows): Response
    {
        return new Response(
            $this->csvContent($headers, $rows),
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => \sprintf('attachment; filename="%s"', $filename),
                'Cache-Control' => 'no-store, private',
            ]
        );
    }

    private function csvContent(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Unable to allocate CSV buffer.');
        }

        fputcsv($handle, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(static fn (int|float|string $value): string => (string) $value, array_values($row)), ',', '"', '');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return false === $content ? '' : $content;
    }

    /**
     * @param list<string> $lines
     */
    private function pdfResponse(string $filename, string $title, array $lines): Response
    {
        return new Response(
            $this->pdfDocument($title, $lines),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => \sprintf('attachment; filename="%s"', $filename),
                'Cache-Control' => 'no-store, private',
            ]
        );
    }

    /**
     * @param list<string> $lines
     */
    private function pdfDocument(string $title, array $lines): string
    {
        $contentLines = [
            'BT',
            '/F1 16 Tf',
            '50 780 Td',
            '(' . $this->escapePdfText($title) . ') Tj',
            '/F1 10 Tf',
            '0 -24 Td',
        ];

        foreach ($lines as $line) {
            $contentLines[] = '(' . $this->escapePdfText($line) . ') Tj';
            $contentLines[] = '0 -14 Td';
        }

        $contentLines[] = 'ET';
        $stream = implode("\n", $contentLines) . "\n";

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => '<< /Length ' . \strlen($stream) . " >>\nstream\n" . $stream . 'endstream',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = \strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = \strlen($pdf);
        $pdf .= 'xref' . "\n";
        $pdf .= '0 ' . (\count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($objects as $number => $body) {
            $pdf .= \sprintf('%010d 00000 n %s', $offsets[$number], "\n");
        }

        $pdf .= 'trailer << /Size ' . (\count($objects) + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n";
        $pdf .= $xrefOffset . "\n";
        $pdf .= '%%EOF' . "\n";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
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

    private function normalizedSearch(mixed $search): string
    {
        return trim(mb_strtolower((string) $search));
    }

    private function parseUuidFilter(mixed $value, string $name): ?Uuid
    {
        if (null === $value || '' === trim((string) $value)) {
            return null;
        }

        return $this->parseUuidOrFail((string) $value, $name);
    }

    private function parseUuidOrFail(string $value, string $name): Uuid
    {
        try {
            return Uuid::fromString($value);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException(\sprintf('Invalid %s value "%s".', $name, $value), previous: $exception);
        }
    }

    private function enumOrNull(string $enumClass, mixed $value): ?\BackedEnum
    {
        if (null === $value || '' === trim((string) $value)) {
            return null;
        }

        if (!method_exists($enumClass, 'tryFrom')) {
            return null;
        }

        return $enumClass::tryFrom((string) $value);
    }
}
