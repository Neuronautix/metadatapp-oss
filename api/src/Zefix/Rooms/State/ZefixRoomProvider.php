<?php

declare(strict_types=1);

namespace App\Zefix\Rooms\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\EnvironmentalObservation;
use App\Entity\Room;
use App\Entity\User;
use App\Enum\EnvironmentalMetric;
use App\Repository\EnvironmentalObservationRepository;
use App\Zefix\Rooms\ZefixRoomSnapshot;
use App\Zefix\Rooms\ZefixRoomTrendPoint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<ZefixRoomSnapshot>
 */
final readonly class ZefixRoomProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private EnvironmentalObservationRepository $observationRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|ZefixRoomSnapshot
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User is not authenticated.');
        }

        /** @phpstan-ignore-next-line */
        if (isset($uriVariables['roomID']) && str_contains((string) $operation->getUriTemplate(), '/history')) {
            return $this->provideHistory((string) $uriVariables['roomID'], $user);
        }

        if (isset($uriVariables['roomID'])) {
            return $this->provideItem((string) $uriVariables['roomID'], $user);
        }

        return $this->provideCollection($user);
    }

    /**
     * @return list<ZefixRoomSnapshot>
     */
    private function provideCollection(User $user): array
    {
        $rooms = $this->entityManager->getRepository(Room::class)
            ->createQueryBuilder('room')
            ->innerJoin('room.plateau', 'plateau')->addSelect('plateau')
            ->andWhere('room.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('plateau.name', 'ASC')
            ->addOrderBy('room.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return array_values(array_map(fn (Room $room): ZefixRoomSnapshot => $this->buildSnapshot($room), $rooms));
    }

    private function provideItem(string $roomID, User $user): ZefixRoomSnapshot
    {
        $room = $this->entityManager->getRepository(Room::class)
            ->createQueryBuilder('room')
            ->innerJoin('room.plateau', 'plateau')->addSelect('plateau')
            ->andWhere('room.account = :account')
            ->andWhere('room.id = :roomID')
            ->setParameter('account', $user->getAccount())
            ->setParameter('roomID', $roomID)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$room instanceof Room) {
            throw new NotFoundHttpException('Zefix room not found.');
        }

        return $this->buildSnapshot($room);
    }

    /**
     * @return list<ZefixRoomTrendPoint>
     */
    private function provideHistory(string $roomID, User $user): array
    {
        $room = $this->entityManager->getRepository(Room::class)
            ->createQueryBuilder('room')
            ->innerJoin('room.plateau', 'plateau')->addSelect('plateau')
            ->andWhere('room.account = :account')
            ->andWhere('room.id = :roomID')
            ->setParameter('account', $user->getAccount())
            ->setParameter('roomID', $roomID)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$room instanceof Room) {
            throw new NotFoundHttpException('Zefix room not found.');
        }

        return array_map(
            static fn (array $point): ZefixRoomTrendPoint => new ZefixRoomTrendPoint(
                timestamp: $point['timestamp'],
                temperature: $point['temperature'],
                pH: $point['pH'],
            ),
            $this->buildTrend($this->observationRepository->findForRoom($room))
        );
    }

    private function buildSnapshot(Room $room): ZefixRoomSnapshot
    {
        $observations = $this->observationRepository->findForRoom($room);
        $temperatureTrend = $this->buildTrend($observations, EnvironmentalMetric::TEMPERATURE);
        $pHTrend = $this->buildTrend($observations, EnvironmentalMetric::PH);

        $latestTemperature = $this->latestValue($observations, EnvironmentalMetric::TEMPERATURE) ?? 0.0;
        $latestObservationAt = $this->latestObservationAt($observations);

        $mortality30d = 0;
        $breedingEvents30d = 0;
        $thresholdDate = new \DateTimeImmutable('-30 days');
        foreach ($room->getRacks() as $rack) {
            foreach ($rack->getPools() as $pool) {
                foreach ($pool->getBatches() as $batch) {
                    if ($batch->getBirthDate() >= $thresholdDate) {
                        ++$breedingEvents30d;
                    }

                    foreach ($batch->getMortalityRecords() as $mortalityRecord) {
                        if ($mortalityRecord->getDate() >= $thresholdDate) {
                            $mortality30d += $mortalityRecord->getCount();
                        }
                    }
                }
            }
        }

        return new ZefixRoomSnapshot(
            roomID: $room->getId()?->toRfc4122() ?? '',
            roomName: $room->getName(),
            facility: $room->getPlateau()->getAccount()->getName(),
            lightCycle: 'Manual',
            roomTemperature: $latestTemperature,
            humidity: 0.0,
            noiseLevel: 0.0,
            mortality30d: $mortality30d,
            breedingEvents30d: $breedingEvents30d,
            temperatureTrend: $temperatureTrend,
            pHTrend: $pHTrend,
            latestObservationAt: $latestObservationAt,
        );
    }

    /**
     * @param list<EnvironmentalObservation> $observations
     *
     * @return list<array{timestamp: string, temperature: float, pH: float}>
     */
    private function buildTrend(array $observations, ?EnvironmentalMetric $metric = null): array
    {
        $trend = [];
        $temperature = 0.0;
        $pH = 0.0;

        foreach ($observations as $observation) {
            if (EnvironmentalMetric::TEMPERATURE === $observation->getMetric()) {
                $temperature = $observation->getValue();
            }

            if (EnvironmentalMetric::PH === $observation->getMetric()) {
                $pH = $observation->getValue();
            }

            if (null === $metric || $metric === $observation->getMetric()) {
                $trend[] = [
                    'timestamp' => $observation->getTimestamp()->format(\DATE_ATOM),
                    'temperature' => $temperature,
                    'pH' => $pH,
                ];
            }
        }

        return $trend;
    }

    /**
     * @param list<EnvironmentalObservation> $observations
     */
    private function latestValue(array $observations, EnvironmentalMetric $metric): ?float
    {
        for ($index = \count($observations) - 1; $index >= 0; --$index) {
            if ($metric === $observations[$index]->getMetric()) {
                return $observations[$index]->getValue();
            }
        }

        return null;
    }

    /**
     * @param list<EnvironmentalObservation> $observations
     */
    private function latestObservationAt(array $observations): ?string
    {
        if ([] === $observations) {
            return null;
        }

        return $observations[\count($observations) - 1]->getTimestamp()->format(\DATE_ATOM);
    }
}
