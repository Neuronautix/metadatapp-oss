<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Mapper;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\StudyWithDataDto;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\Experiment;
use App\Entity\Procedure;
use App\Entity\Treatment;
use App\Entity\WeightMeasurement;
use App\Repository\ProcedureRepository;
use App\Repository\SubjectRepository;
use App\Repository\WeightMeasurementRepository;
use Psr\Log\LoggerInterface;
use Random\RandomException;

class ExperimentMapper
{
    // Fetch the proper 'Project', 'Study' and 'Task' in Soft Mouse (and Map it to Project, Experiment, Procedure in MAPP)
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly WeightMeasurementRepository $weightMeasurementRepository,
        private readonly ProcedureRepository $procedureRepository,
        private readonly SubjectRepository $subjectRepository,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     * @throws RandomException
     */
    public function mapFromExternal(StudyWithDataDto $dto, Experiment|ConnectedAppEntityInterface $entity): bool
    {
        if (!$entity instanceof Experiment) {
            throw new \InvalidArgumentException(\sprintf('ExperimentMapper expects an Experiment, got %s', get_debug_type($entity)));
        }

        $study = $dto->study;
        $studyData = $dto->studyData;
        if (null === $studyData?->taskId) {
            $this->logger->info('No task ID in study data, skipping Experiment synchronization', [
                'experiment_id' => $entity->getId()?->toString(),
            ]);

            return false;
        }

        $entity
            ->setName($studyData->studyName ?? 'Missing Name')
            ->setStartAt($study->startDate ?? new \DateTime())
            ->setEndAt($study->endDate)
        ;

        $procedure = $this->procedureRepository->findOneBy(['softmouseExternalId' => $studyData->taskId]);
        if (null === $procedure) {
            // todo refactor procedures data model or create the procedure type based on studyData->studyType if null we set treatment just for demo

            $procedure = (new Treatment())
                ->setName($studyData->taskName ?? '')
                ->setSoftmouseExternalId((string) $studyData->taskId)
                ->setAccount($entity->getAccount())
                ->setUser($entity->getUser())
            ;
        }
        if ($procedure instanceof Procedure) {
            $entity->addProcedure($procedure);
        }

        // Iterate over all occurrences (timepoints) of this task
        foreach (($studyData->taskOccurence ?? []) as $occurence) {
            $dateTime = $occurence->scheduleDate;

            // Each data entry corresponds to one subject at this timepoint
            foreach ($occurence->data as $w) {
                // You already chose to use animalSID as Subject.softmouseExternalId = good, matches SubjectSynchronizer
                $subject = $this->subjectRepository->findOneBy([
                    'softmouseExternalId' => $w->animalSID,
                ]);

                if (null === $subject) {
                    // No locally synced subject for this animal, skip
                    continue;
                }

                if (null === $w->value || '' === $w->value) {
                    // No numeric value, skip (empty measurement, etc.)
                    continue;
                }

                // Idempotent: one WeightMeasurement per (subject, date)
                $start = (clone $dateTime)->sub(new \DateInterval('PT24H'));
                $end = (clone $dateTime);

                $weightMeasurement = $this->weightMeasurementRepository->createQueryBuilder('wm')
                    ->andWhere('wm.subject = :subject')
                    ->andWhere('wm.measuredAt BETWEEN :start AND :end')
                    ->setParameter('subject', $subject)
                    ->setParameter('start', $start)
                    ->setParameter('end', $end)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult()
                ;

                if (!$weightMeasurement) {
                    $weightMeasurement = (new WeightMeasurement())
                        ->setMeasuredAt($dateTime)
                        ->setAccount($entity->getAccount())
                        ->setSubject($subject)
                        ->setUser($entity->getUser())
                    ;

                    $subject->addWeightMeasurement($weightMeasurement);
                }

                $weightMeasurement->setWeight((float) $w->value);

                if ($procedure instanceof Procedure) {
                    $subject->addProcedure($procedure);
                }
            }
        }

        return true;
    }
}
