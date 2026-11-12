<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Synchronizer;

use App\ConnectedApps\Apps\SoftMouse\Client\ClientInterface;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\AnimalDto;
use App\ConnectedApps\Apps\SoftMouse\Mapper\AnimalMapper;
use App\Entity\ConnectedApp;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\Subject;
use App\Repository\SubjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\Uid\Uuid;

/**
 * Synchronizer to pull data from SoftMouse via the SoftMouseClientService
 * and persist it locally using repository services.
 */
readonly class SubjectSynchronizer
{
    public function __construct(
        private ClientInterface $softMouse,
        private EntityManagerInterface $em,
        private SubjectRepository $subjectRepository,
        private AnimalMapper $animalMapper,
    ) {
    }

    /**
     * Sync subject from SoftMouse animals to the local database.
     * Sync animals since the last sync timestamp on the ConnectedApp entity.
     */
    public function syncSubjects(ConnectedApp $connectedApp): ?array
    {
        // Determine the 'since' timestamp (defaults to epoch if none)
        //        $since = $connectedApp->getLastSyncAt();
        // Prepare query parameters
        $params = [];
        //        if ($since) {
        //            $params['sinceDateTime'] = $since->format('Y-m-d\TH:i:s\Z');
        //        }

        $subjects = [];
        $page = 1;
        $limit = 500;
        do {
            // Fetch DTOs from SoftMouse
            $animals = $this->softMouse
                ->animals()
                ->search($params, $limit, $page)
            ;

            if ([] === $animals) {
                break;
            }

            foreach ($animals as $animalDto) {
                $sid = $animalDto->guiSid; // SoftMouse SID
                $subject = $this->subjectRepository
                    ->findOneBy(['softmouseExternalId' => $sid])
                ;
                if (!$subject) {
                    $subject = new Subject();
                    $subject->setSoftmouseExternalId((string) $sid);
                    $this->em->persist($subject);
                }

                $this->syncSubject($animalDto, $subject, $connectedApp);

                $subjects[] = $subject;
            }
            ++$page;
        } while (\count($animals) === $limit);

        return [] === $subjects ? null : $subjects;
    }

    /**
     * @throws \DateMalformedStringException
     * @throws RandomException
     */
    private function syncSubject(AnimalDto $animalDto, Subject $subject, ConnectedApp $connectedApp): void
    {
        $this->animalMapper->mapFromDto($animalDto, $subject);
        $subject->setAccount($connectedApp->getAccount());
        $subject->getCage()?->setAccount($connectedApp->getAccount());
        $this->em->persist($subject);
    }

    public function syncOneFrom(ConnectedApp $connectedApp, Uuid $entityId): ?ConnectedAppEntityInterface
    {
        $subject = $this->subjectRepository->find($entityId);
        if (!$subject) {
            return null;
        }
        $animalDto = $this->softMouse
            ->animals()
            ->get((string) $subject->getSoftmouseExternalId())
        ;
        if (!$animalDto) {
            return null;
        }
        $this->syncSubject($animalDto, $subject, $connectedApp);

        return $subject;
    }
}
