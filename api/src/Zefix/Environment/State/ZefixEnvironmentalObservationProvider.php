<?php

declare(strict_types=1);

namespace App\Zefix\Environment\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\EnvironmentalObservation;
use App\Entity\User;
use App\Zefix\Environment\ZefixEnvironmentalObservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<ZefixEnvironmentalObservation>
 */
final readonly class ZefixEnvironmentalObservationProvider implements ProviderInterface
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
        $scopeType = trim((string) $request?->query->get('scopeType', ''));
        $scopeID = trim((string) $request?->query->get('scopeID', ''));
        $systemID = trim((string) $request?->query->get('systemID', ''));
        $roomID = trim((string) $request?->query->get('roomID', ''));

        $queryBuilder = $this->entityManager->getRepository(EnvironmentalObservation::class)
            ->createQueryBuilder('observation')
            ->leftJoin('observation.system', 'system')->addSelect('system')
            ->leftJoin('system.room', 'systemRoom')->addSelect('systemRoom')
            ->leftJoin('observation.room', 'room')->addSelect('room')
            ->andWhere('observation.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('observation.timestamp', 'ASC')
            ->addOrderBy('observation.id', 'ASC')
        ;

        if ('system' === $scopeType || '' !== $systemID) {
            $value = '' !== $scopeID ? $scopeID : $systemID;
            $queryBuilder
                ->andWhere('system.code = :systemID')
                ->setParameter('systemID', $value)
            ;
        }

        if ('room' === $scopeType || '' !== $roomID) {
            $value = '' !== $scopeID ? $scopeID : $roomID;
            $queryBuilder
                ->andWhere('room.id = :roomID OR systemRoom.id = :roomID')
                ->setParameter('roomID', $value)
            ;
        }

        /** @var list<EnvironmentalObservation> $observations */
        $observations = $queryBuilder->getQuery()->getResult();

        return array_map([$this, 'buildDto'], $observations);
    }

    private function buildDto(EnvironmentalObservation $observation): ZefixEnvironmentalObservation
    {
        $system = $observation->getSystem();
        $room = $observation->getRoom() ?? $system?->getRoom();
        $scopeType = null !== $system ? 'system' : 'room';
        $scopeID = null !== $system ? $system->getCode() : ($room?->getId()?->toRfc4122() ?? '');

        return new ZefixEnvironmentalObservation(
            observationID: $observation->getId()?->toRfc4122() ?? '',
            scopeType: $scopeType,
            scopeID: $scopeID,
            systemID: $system?->getCode(),
            roomID: $room?->getId()?->toRfc4122(),
            metric: $observation->getMetric()->value,
            value: $observation->getValue(),
            timestamp: $observation->getTimestamp()->format(\DATE_ATOM),
            source: $observation->getSource()->value,
            notes: $observation->getNotes(),
            recorder: $observation->getRecorder(),
        );
    }
}
