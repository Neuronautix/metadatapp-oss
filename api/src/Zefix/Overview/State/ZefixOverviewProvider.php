<?php

declare(strict_types=1);

namespace App\Zefix\Overview\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\EnvironmentalObservation;
use App\Entity\MortalityRecord;
use App\Entity\User;
use App\Entity\ZebrafishBatch;
use App\Entity\ZebrafishLine;
use App\Enum\AlertStatus;
use App\Enum\EnvironmentalMetric;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Enum\ZebrafishLineStatus;
use App\Repository\AlertRepository;
use App\Zefix\Overview\ZefixOverview;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<ZefixOverview>
 */
final readonly class ZefixOverviewProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlertRepository $alertRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ZefixOverview
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User is not authenticated.');
        }

        $account = $user->getAccount();
        $currentBatches = $this->entityManager->getRepository(ZebrafishBatch::class)
            ->createQueryBuilder('batch')
            ->innerJoin('batch.line', 'line')->addSelect('line')
            ->andWhere('batch.account = :account')
            ->andWhere('batch.lifecycleStatus IN (:statuses)')
            ->setParameter('account', $account)
            ->setParameter('statuses', [
                ZebrafishBatchLifecycleStatus::ACTIVE,
                ZebrafishBatchLifecycleStatus::QUARANTINED,
            ])
            ->getQuery()
            ->getResult()
        ;

        $activeLines = (int) $this->entityManager->getRepository(ZebrafishLine::class)
            ->createQueryBuilder('line')
            ->select('COUNT(line.id)')
            ->andWhere('line.account = :account')
            ->andWhere('line.status = :status')
            ->setParameter('account', $account)
            ->setParameter('status', ZebrafishLineStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $todaysMortalities = (int) $this->entityManager->getRepository(MortalityRecord::class)
            ->createQueryBuilder('record')
            ->select('COALESCE(SUM(record.count), 0)')
            ->andWhere('record.account = :account')
            ->andWhere('record.date >= :today')
            ->setParameter('account', $account)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $populationByLine = $this->buildPopulationByLine($currentBatches);
        $mortalityTrend = $this->buildMortalityTrend($account);
        $temperatureTrend = $this->buildTemperatureTrend($account);
        $breedingOutput = $this->buildBreedingOutput($account);

        return new ZefixOverview(
            totalLines: $activeLines,
            totalBatches: \count($currentBatches),
            totalFish: array_sum(array_map(static fn (ZebrafishBatch $batch): int => $batch->getTotalPopulation(), $currentBatches)),
            todaysMortalities: $todaysMortalities,
            activeAlerts: \count($this->alertRepository->findForAccount($account, AlertStatus::ACTIVE)),
            populationByLine: $populationByLine,
            mortalityTrend: $mortalityTrend,
            temperatureTrend: $temperatureTrend,
            breedingOutput: $breedingOutput,
        );
    }

    /**
     * @param list<ZebrafishBatch> $currentBatches
     *
     * @return list<array{line: string, fish: int}>
     */
    private function buildPopulationByLine(array $currentBatches): array
    {
        $populationByLine = [];
        foreach ($currentBatches as $batch) {
            $lineName = $batch->getLine()->getName();
            $populationByLine[$lineName] = ($populationByLine[$lineName] ?? 0) + $batch->getTotalPopulation();
        }

        ksort($populationByLine);

        return array_map(
            static fn (string $line, int $fish): array => ['line' => $line, 'fish' => $fish],
            array_keys($populationByLine),
            array_values($populationByLine),
        );
    }

    /**
     * @return list<array{date: string, mortalities: int}>
     */
    private function buildMortalityTrend(\App\Entity\Account $account): array
    {
        /** @var list<MortalityRecord> $records */
        $records = $this->entityManager->getRepository(MortalityRecord::class)
            ->createQueryBuilder('record')
            ->andWhere('record.account = :account')
            ->andWhere('record.date >= :start')
            ->setParameter('account', $account)
            ->setParameter('start', new \DateTimeImmutable('-13 days'))
            ->orderBy('record.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        $countsByDay = [];
        foreach ($records as $record) {
            $day = $record->getDate()->format('Y-m-d');
            $countsByDay[$day] = ($countsByDay[$day] ?? 0) + $record->getCount();
        }

        $trend = [];
        for ($index = 13; $index >= 0; --$index) {
            $date = new \DateTimeImmutable(\sprintf('-%d days', $index));
            $day = $date->format('Y-m-d');
            $trend[] = [
                'date' => $date->format(\DATE_ATOM),
                'mortalities' => $countsByDay[$day] ?? 0,
            ];
        }

        return $trend;
    }

    /**
     * @return list<array{timestamp: string, temperature: float}>
     */
    private function buildTemperatureTrend(\App\Entity\Account $account): array
    {
        // Identify the primary system as the one with the most recent temperature observation.
        /** @var EnvironmentalObservation|null $latestObservation */
        $latestObservation = $this->entityManager->getRepository(EnvironmentalObservation::class)
            ->createQueryBuilder('observation')
            ->andWhere('observation.account = :account')
            ->andWhere('observation.metric = :metric')
            ->andWhere('observation.system IS NOT NULL')
            ->setParameter('account', $account)
            ->setParameter('metric', EnvironmentalMetric::TEMPERATURE)
            ->orderBy('observation.timestamp', 'DESC')
            ->addOrderBy('observation.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $latestObservation) {
            return [];
        }

        $primarySystem = $latestObservation->getSystem();
        \assert(null !== $primarySystem, 'System is guaranteed non-null by the IS NOT NULL filter above.');

        /** @var list<EnvironmentalObservation> $observations */
        $observations = $this->entityManager->getRepository(EnvironmentalObservation::class)
            ->createQueryBuilder('observation')
            ->andWhere('observation.account = :account')
            ->andWhere('observation.metric = :metric')
            ->andWhere('observation.system = :system')
            ->setParameter('account', $account)
            ->setParameter('metric', EnvironmentalMetric::TEMPERATURE)
            ->setParameter('system', $primarySystem)
            ->orderBy('observation.timestamp', 'DESC')
            ->addOrderBy('observation.id', 'DESC')
            ->setMaxResults(18)
            ->getQuery()
            ->getResult()
        ;

        $observations = array_reverse($observations);

        return array_map(
            static fn (EnvironmentalObservation $observation): array => [
                'timestamp' => $observation->getTimestamp()->format(\DATE_ATOM),
                'temperature' => $observation->getValue(),
            ],
            $observations,
        );
    }

    /**
     * @return list<array{period: string, births: int}>
     */
    private function buildBreedingOutput(\App\Entity\Account $account): array
    {
        $startMonth = new \DateTimeImmutable('first day of this month -5 months');

        /** @var list<ZebrafishBatch> $batches */
        $batches = $this->entityManager->getRepository(ZebrafishBatch::class)
            ->createQueryBuilder('batch')
            ->andWhere('batch.account = :account')
            ->andWhere('batch.birthDate >= :startMonth')
            ->setParameter('account', $account)
            ->setParameter('startMonth', $startMonth)
            ->orderBy('batch.birthDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        $birthsByMonth = [];
        foreach ($batches as $batch) {
            $monthKey = $batch->getBirthDate()->format('Y-m');
            $birthsByMonth[$monthKey] = ($birthsByMonth[$monthKey] ?? 0) + 1;
        }

        $output = [];
        for ($index = 0; $index < 6; ++$index) {
            $month = $startMonth->modify(\sprintf('+%d months', $index));
            $monthKey = $month->format('Y-m');
            $output[] = [
                'period' => $month->format('M Y'),
                'births' => $birthsByMonth[$monthKey] ?? 0,
            ];
        }

        return $output;
    }
}
