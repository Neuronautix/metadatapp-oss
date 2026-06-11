<?php

declare(strict_types=1);

namespace App\Zefix\Systems\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\EnvironmentalObservation;
use App\Entity\System;
use App\Entity\User;
use App\Enum\EnvironmentalMetric;
use App\Repository\AlertRepository;
use App\Repository\EnvironmentalObservationRepository;
use App\Zefix\Systems\ZefixSystemHistoryPoint;
use App\Zefix\Systems\ZefixSystemSnapshot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<ZefixSystemSnapshot>
 */
final readonly class ZefixSystemProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private EnvironmentalObservationRepository $observationRepository,
        private AlertRepository $alertRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|ZefixSystemSnapshot
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User is not authenticated.');
        }

        /** @phpstan-ignore-next-line */
        if (isset($uriVariables['systemID']) && str_contains((string) $operation->getUriTemplate(), '/history')) {
            return $this->provideHistory((string) $uriVariables['systemID'], $user);
        }

        if (isset($uriVariables['systemID'])) {
            return $this->provideItem((string) $uriVariables['systemID'], $user);
        }

        return $this->provideCollection($user);
    }

    /**
     * @return list<ZefixSystemSnapshot>
     */
    private function provideCollection(User $user): array
    {
        /** @var list<System> $systems */
        $systems = $this->entityManager->getRepository(System::class)
            ->createQueryBuilder('system')
            ->innerJoin('system.room', 'room')->addSelect('room')
            ->leftJoin('system.racks', 'rack')->addSelect('rack')
            ->leftJoin('rack.pools', 'pool')->addSelect('pool')
            ->leftJoin('pool.batches', 'batch')->addSelect('batch')
            ->andWhere('system.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('room.name', 'ASC')
            ->addOrderBy('system.code', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        $latestObservations = $this->observationRepository->findLatestPerSystemMetric($systems);
        $alertCounts = $this->alertRepository->countActivePerSystem($systems);

        return array_map(
            fn (System $system): ZefixSystemSnapshot => $this->buildSnapshot($system, $latestObservations[$system->getCode()] ?? [], $alertCounts[$system->getCode()] ?? 0),
            $systems
        );
    }

    private function provideItem(string $systemID, User $user): ZefixSystemSnapshot
    {
        $system = $this->entityManager->getRepository(System::class)
            ->createQueryBuilder('system')
            ->innerJoin('system.room', 'room')->addSelect('room')
            ->leftJoin('system.racks', 'rack')->addSelect('rack')
            ->leftJoin('rack.pools', 'pool')->addSelect('pool')
            ->leftJoin('pool.batches', 'batch')->addSelect('batch')
            ->andWhere('system.account = :account')
            ->andWhere('system.code = :systemID')
            ->setParameter('account', $user->getAccount())
            ->setParameter('systemID', $systemID)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$system instanceof System) {
            throw new NotFoundHttpException('Zefix system not found.');
        }

        return $this->buildSnapshot($system);
    }

    /**
     * @return list<ZefixSystemHistoryPoint>
     */
    private function provideHistory(string $systemID, User $user): array
    {
        $system = $this->entityManager->getRepository(System::class)
            ->createQueryBuilder('system')
            ->innerJoin('system.room', 'room')->addSelect('room')
            ->andWhere('system.account = :account')
            ->andWhere('system.code = :systemID')
            ->setParameter('account', $user->getAccount())
            ->setParameter('systemID', $systemID)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$system instanceof System) {
            throw new NotFoundHttpException('Zefix system not found.');
        }

        $observations = $this->observationRepository->findForSystem($system);
        $currentTemperature = 0.0;
        $currentPH = 0.0;
        $history = [];

        foreach ($observations as $observation) {
            if (EnvironmentalMetric::TEMPERATURE === $observation->getMetric()) {
                $currentTemperature = $observation->getValue();
            }

            if (EnvironmentalMetric::PH === $observation->getMetric()) {
                $currentPH = $observation->getValue();
            }

            $history[] = new ZefixSystemHistoryPoint(
                timestamp: $observation->getTimestamp()->format(\DATE_ATOM),
                temperature: $currentTemperature,
                pH: $currentPH,
            );
        }

        return $history;
    }

    /**
     * @param array<string, EnvironmentalObservation> $preloadedObservations keyed by metric value; when null, DB queries are issued per metric
     */
    private function buildSnapshot(System $system, array $preloadedObservations = [], ?int $preloadedAlertCount = null): ZefixSystemSnapshot
    {
        if ([] !== $preloadedObservations) {
            $latestTemperature = $preloadedObservations[EnvironmentalMetric::TEMPERATURE->value] ?? null;
            $latestPH = $preloadedObservations[EnvironmentalMetric::PH->value] ?? null;
        } else {
            $latestTemperature = $this->observationRepository->findLatestForSystemMetric($system, EnvironmentalMetric::TEMPERATURE);
            $latestPH = $this->observationRepository->findLatestForSystemMetric($system, EnvironmentalMetric::PH);
        }

        $latestObservationAt = null;
        if ($latestTemperature instanceof EnvironmentalObservation || $latestPH instanceof EnvironmentalObservation) {
            $timestamps = array_filter([
                $latestTemperature?->getTimestamp()->format(\DATE_ATOM),
                $latestPH?->getTimestamp()->format(\DATE_ATOM),
            ]);
            rsort($timestamps);
            $latestObservationAt = $timestamps[0] ?? null;
        }

        $systemBatches = [];
        foreach ($system->getRacks() as $rack) {
            foreach ($rack->getPools() as $pool) {
                foreach ($pool->getBatches() as $batch) {
                    $systemBatches[] = $batch;
                }
            }
        }

        $activeBatches = array_reduce(
            $systemBatches,
            static fn (int $count, \App\Entity\ZebrafishBatch $batch): int => \in_array(
                $batch->getLifecycleStatus(),
                [\App\Enum\ZebrafishBatchLifecycleStatus::ACTIVE, \App\Enum\ZebrafishBatchLifecycleStatus::QUARANTINED],
                true
            ) ? $count + 1 : $count,
            0
        );
        $fishCount = array_reduce(
            $systemBatches,
            static fn (int $sum, \App\Entity\ZebrafishBatch $batch): int => $sum + $batch->getTotalPopulation(),
            0
        );

        $activeAlerts = $preloadedAlertCount ?? $this->alertRepository->countActiveForSystem($system);

        return new ZefixSystemSnapshot(
            systemID: $system->getCode(),
            manufacturer: $system->getManufacturer(),
            model: $system->getModel(),
            roomID: $system->getRoom()->getId()?->toRfc4122() ?? '',
            roomName: $system->getRoom()->getName(),
            waterTemperature: $latestTemperature?->getValue() ?? 0.0,
            pH: $latestPH?->getValue() ?? 0.0,
            conductivity: $system->getConductivity(),
            oxygen: $system->getOxygen(),
            waterFlow: $system->getWaterFlow(),
            filtrationStatus: $system->getFiltrationStatus(),
            activeBatches: $activeBatches,
            fishCount: $fishCount,
            activeAlerts: $activeAlerts,
            latestObservationAt: $latestObservationAt,
        );
    }
}
