<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Synchronizer;

use App\ConnectedApps\Apps\SoftMouse\Client\ClientInterface;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\StudyWithDataDto;
use App\ConnectedApps\Apps\SoftMouse\Mapper\ExperimentMapper;
use App\ConnectedApps\Shared\SynchronizerFromInterface;
use App\Entity\ConnectedApp;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Repository\ExperimentRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Synchronizer to pull data from SoftMouse via the SoftMouseClientService
 * and persist it locally using repository services.
 */
readonly class ExperimentSynchronizer implements SynchronizerFromInterface
{
    public function __construct(
        private ClientInterface $softMouse,
        private EntityManagerInterface $em, // Should move in the service or not ?
        private ExperimentRepository $repository,
        private ProjectRepository $projectRepository,
        private ExperimentMapper $mapper,
        private LoggerInterface $logger,
    ) {
    }

    private function logMappingFailure(Experiment $experiment, StudyWithDataDto $studyWithData): void
    {
        $this->logger->warning('Skipping experiment sync due to mapping failure', [
            'softmouse_external_id' => $experiment->getSoftmouseExternalId(),
            'experiment_id' => $experiment->getId()?->toString(),
            'study_title' => $studyWithData->study->title ?? 'Unknown',
        ]);
    }

    public function syncAllFrom(ConnectedApp $connectedApp): ?array
    {
        $params = [];
        //        $since = $connectedApp->getLastSyncAt();
        //        if ($since) {
        //            $params['sinceDateTime'] = $since->format('Y-m-d\TH:i:s\Z');
        //        }
        $alreadyLoadedProjects = [];
        $experiments = [];
        $page = 1;
        $limit = 500;
        do {
            $studies = $this->softMouse
                ->studies()
                ->search($params, $limit, $page)
            ;

            if ([] === $studies) {
                break;
            }

            foreach ($studies as $study) {
                $code = $study->code; // SoftMouse code is its softMouseExternalId (they use code to get a study in their api)
                $studyData = $this->softMouse->studyData()->get($code);
                if (null === $studyData) {
                    // No study data found for this study, skip it
                    continue;
                }

                $studyWithData = new StudyWithDataDto($study, $studyData);

                // Get or Create new Experiment entity based on the external ID (SID) which is the study code
                $experiment = $this->repository->findOneBy(['softmouseExternalId' => $code]) ?? (new Experiment())->setSoftmouseExternalId($code);
                $experiment->setAccount($connectedApp->getAccount());
                $experiment->setUser($connectedApp->getUser());
                if (!$this->mapper->mapFromExternal($studyWithData, $experiment)) {
                    $this->logger->warning('Skipping experiment sync due to mapping failure', [
                        'study_code' => $code,
                        'study_title' => $studyWithData->study->title,
                    ]);
                    continue;
                }
                $experiment->setName($studyWithData->study->title);
                $project =
                    $alreadyLoadedProjects[$studyWithData->study->projectName] ??
                    $this->projectRepository->findOneBy(['softmouseExternalId' => $studyWithData->study->projectName]) ??
                    (new Project())
                        ->setName($studyWithData->study->projectName)
                        ->setAccount($connectedApp->getAccount())
                        ->setUser($connectedApp->getUser())
                        ->setSoftmouseExternalId($studyWithData->study->projectName)
                ;

                $alreadyLoadedProjects[$project->getSoftmouseExternalId()] = $project;
                $this->em->persist($project);

                $experiment->setProject($project);
                $this->em->persist($experiment);

                $experiments[] = $experiment;
            }
            ++$page;
        } while (\count($studies) === $limit);

        return [] === $experiments ? null : $experiments;
    }

    public function syncOneFrom(ConnectedApp $connectedApp, Uuid $entityId): ?ConnectedAppEntityInterface
    {
        $experiment = $this->repository->find($entityId);
        if (!$experiment) {
            throw new \InvalidArgumentException(\sprintf('Subject with ID %s not found.', $entityId));
        }
        if (!$experiment->getSoftmouseExternalId()) {
            throw new \InvalidArgumentException(\sprintf('Subject with ID %s has no external ID.', $entityId));
        }

        // Fetch Study from SoftMouse
        $studyDto = $this->softMouse->studies()->get($experiment->getSoftmouseExternalId());

        if (!$studyDto) {
            // throw new \RuntimeException(\sprintf('Study with SID %s not found in SoftMouse.', $subject->getSoftmouseExternalId()));
            return null; // No study found for the given SID
        }
        $studyData = $this->softMouse->studyData()->get($studyDto->code);
        $studyWithData = new StudyWithDataDto($studyDto, $studyData);

        if (!$this->mapper->mapFromExternal($studyWithData, $experiment)) {
            $this->logMappingFailure($experiment, $studyWithData);

            return null;
        }
        $this->em->persist($experiment);

        return $experiment; // Return the updated subject entity
    }
}
