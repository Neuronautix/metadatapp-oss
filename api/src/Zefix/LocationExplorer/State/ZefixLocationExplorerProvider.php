<?php

declare(strict_types=1);

namespace App\Zefix\LocationExplorer\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Plateau;
use App\Entity\User;
use App\Entity\ZebrafishBatch;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Zefix\LocationExplorer\ZefixLocationExplorer;
use App\Zefix\LocationExplorer\ZefixLocationExplorerBatchNode;
use App\Zefix\LocationExplorer\ZefixLocationExplorerPlateauNode;
use App\Zefix\LocationExplorer\ZefixLocationExplorerPoolNode;
use App\Zefix\LocationExplorer\ZefixLocationExplorerRackNode;
use App\Zefix\LocationExplorer\ZefixLocationExplorerRoomNode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<ZefixLocationExplorer>
 */
final readonly class ZefixLocationExplorerProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ZefixLocationExplorer
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User is not authenticated.');
        }

        $account = $user->getAccount();

        $plateaux = $this->entityManager->getRepository(Plateau::class)
            ->createQueryBuilder('plateau')
            ->leftJoin('plateau.rooms', 'room')->addSelect('room')
            ->leftJoin('room.racks', 'rack')->addSelect('rack')
            ->leftJoin('rack.pools', 'pool')->addSelect('pool')
            ->andWhere('plateau.account = :account')
            ->setParameter('account', $account)
            ->orderBy('plateau.name', 'ASC')
            ->addOrderBy('room.name', 'ASC')
            ->addOrderBy('rack.code', 'ASC')
            ->addOrderBy('pool.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        $activeBatches = $this->entityManager->getRepository(ZebrafishBatch::class)
            ->createQueryBuilder('batch')
            ->innerJoin('batch.pool', 'pool')->addSelect('pool')
            ->innerJoin('batch.line', 'line')->addSelect('line')
            ->andWhere('batch.account = :account')
            ->andWhere('batch.lifecycleStatus IN (:lifecycleStatuses)')
            ->setParameter('account', $account)
            ->setParameter('lifecycleStatuses', [
                ZebrafishBatchLifecycleStatus::ACTIVE,
                ZebrafishBatchLifecycleStatus::QUARANTINED,
            ])
            ->getQuery()
            ->getResult()
        ;

        $activeBatchByPoolId = [];
        foreach ($activeBatches as $batch) {
            $poolId = $batch->getPool()->getId()?->toRfc4122();
            if (null === $poolId) {
                continue;
            }

            $activeBatchByPoolId[$poolId] = new ZefixLocationExplorerBatchNode(
                batchId: $batch->getId()?->toRfc4122() ?? '',
                lineId: $batch->getLine()->getId()?->toRfc4122() ?? '',
                lineName: $batch->getLine()->getName(),
                birthDate: $batch->getBirthDate()->format('Y-m-d'),
                totalPopulation: $batch->getTotalPopulation(),
                lifecycleStatus: $batch->getLifecycleStatus()->value,
            );
        }

        $plateauNodes = [];
        foreach ($plateaux as $plateau) {
            $roomNodes = [];
            foreach ($plateau->getRooms() as $room) {
                $rackNodes = [];
                foreach ($room->getRacks() as $rack) {
                    $poolNodes = [];
                    foreach ($rack->getPools() as $pool) {
                        $poolId = $pool->getId()?->toRfc4122() ?? '';
                        $batch = $activeBatchByPoolId[$poolId] ?? null;

                        $poolNodes[] = new ZefixLocationExplorerPoolNode(
                            poolId: $poolId,
                            position: $pool->getPosition(),
                            capacity: $pool->getCapacity(),
                            status: $pool->getStatus()->value,
                            occupied: null !== $batch,
                            batch: $batch,
                        );
                    }

                    $rackNodes[] = new ZefixLocationExplorerRackNode(
                        rackId: $rack->getId()?->toRfc4122() ?? '',
                        code: $rack->getCode(),
                        systemId: $rack->getSystem()?->getCode(),
                        pools: $poolNodes,
                    );
                }

                $roomNodes[] = new ZefixLocationExplorerRoomNode(
                    roomId: $room->getId()?->toRfc4122() ?? '',
                    name: $room->getName(),
                    racks: $rackNodes,
                );
            }

            $plateauNodes[] = new ZefixLocationExplorerPlateauNode(
                plateauId: $plateau->getId()?->toRfc4122() ?? '',
                name: $plateau->getName(),
                code: $plateau->getCode(),
                rooms: $roomNodes,
            );
        }

        return new ZefixLocationExplorer(
            facility: $account->getName(),
            plateaux: $plateauNodes,
        );
    }
}
