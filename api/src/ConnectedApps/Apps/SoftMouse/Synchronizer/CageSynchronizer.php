<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Synchronizer;

use App\ConnectedApps\Apps\SoftMouse\Client\ClientInterface;
use App\ConnectedApps\Apps\SoftMouse\Mapper\CageMapper;
use App\ConnectedApps\Shared\SynchronizerFromInterface;
use App\Entity\Cage;
use App\Entity\ConnectedApp;
use App\Entity\ConnectedAppEntityInterface;
use App\Repository\CageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class CageSynchronizer implements SynchronizerFromInterface
{
    public function __construct(
        private readonly ClientInterface $softMouse,
        private readonly EntityManagerInterface $em,
        private readonly CageRepository $cageRepository,
        private readonly CageMapper $cageMapper,
    ) {
    }

    public function syncCage(ConnectedApp $connectedApp, Uuid $entityId): ?ConnectedAppEntityInterface
    {
        $cage = $this->cageRepository->find($entityId);
        if (!$cage) {
            throw new \Exception('Cage not found');
        }
        if (!($cageSid = $cage->getSoftmouseExternalId())) {
            throw new \Exception('Cage external id not found');
        }

        $cageDto = $this->softMouse->cages()->get($cageSid);
        if (!$cageDto) {
            throw new \Exception('Cage not found in SoftMouse');
        }
        $this->cageMapper->mapFromExternal($cageDto, $cage);

        $cage->setAccount($connectedApp->getAccount());
        $this->em->persist($cage);

        return $cage;
    }

    public function syncOneFrom(ConnectedApp $connectedApp, Uuid $entityId): ?ConnectedAppEntityInterface
    {
        return $this->syncCage($connectedApp, $entityId);
    }

    public function syncCages(ConnectedApp $connectedApp): ?array
    {
        //        $since = $connectedApp->getLastSyncAt();
        // Prepare query parameters
        $params = [];
        //        if ($since) {
        //            // Warning BUG
        // //            $params['sinceDateTime'] = $since->format('Y-m-d\TH:i:s\Z');
        //        }
        $cages = [];
        $page = 1;
        $limit = 500;
        do {
            $cageDtos = $this->softMouse
                ->cages()
                ->search($params, $limit, $page)
            ;

            if ([] === $cageDtos) {
                break;
            }

            // Map and save each DTO locally
            foreach ($cageDtos as $cageDto) {
                $sid = $cageDto->cageSid; // SoftMouse SID
                // Try to find an existing Animal by SID or create a new one: refactor this to a method in the CageRepository
                $cage = $this->cageRepository
                    ->findOneBy(['softmouseExternalId' => $sid])
                ;
                if (!$cage) {
                    $cage = new Cage();
                }

                $this->cageMapper->mapFromExternal($cageDto, $cage);
                $cage->setAccount($connectedApp->getAccount());

                $this->em->persist($cage);
                $cages[] = $cage;
            }
            ++$page;
        } while (\count($cageDtos) === $limit);

        return [] === $cages ? null : $cages;
    }

    /**
     * @return array<Cage|ConnectedAppEntityInterface>|null
     */
    public function syncAllFrom(ConnectedApp $connectedApp): ?array
    {
        return $this->syncCages($connectedApp);
    }
}
