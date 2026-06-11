<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Cage;
use App\Entity\CageHcmMeasure;
use App\Entity\CageHcmSinusoidMetadata;
use App\Entity\Experiment;
use App\Entity\TimeSeries;
use App\Entity\User;
use App\Service\Dvc\CircadianService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
final class DvcLegacyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CircadianService $circadianService,
    ) {
    }

    #[Route('/api/dvc/experiments/{id}/summary', name: 'api_dvc_experiment_summary', methods: ['GET'])]
    public function getExperimentSummary(string $id, Request $request): JsonResponse
    {
        $user = $this->getCurrentUser();

        /** @var Experiment|null $experiment */
        $experiment = Uuid::isValid($id)
            ? $this->entityManager->getRepository(Experiment::class)->find(Uuid::fromString($id))
            : null;

        $experimentAccountId = $experiment?->getAccount()->getId();
        $userAccountId = $user->getAccount()->getId();
        if (null === $experiment || null === $experimentAccountId || null === $userAccountId || !$experimentAccountId->equals($userAccountId)) {
            return $this->json(['message' => 'Experiment not found.'], Response::HTTP_NOT_FOUND);
        }

        $fromRaw = $request->query->getString('from', '-7 days');
        $toRaw = $request->query->getString('to', 'now');

        try {
            $from = new \DateTimeImmutable($fromRaw, new \DateTimeZone('UTC'));
            $to = new \DateTimeImmutable($toRaw, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return $this->json(['message' => 'Invalid date range parameters.'], Response::HTTP_BAD_REQUEST);
        }

        // Collect unique subjects across all procedures, grouped by cage
        /** @var array<string, array{cage: Cage, subjects: list<\App\Entity\Subject>}> $byCage */
        $byCage = [];
        $seen = [];

        foreach ($experiment->getProcedures() as $procedure) {
            foreach ($procedure->getSubjects() as $subject) {
                $subjectId = (string) $subject->getId();
                if (isset($seen[$subjectId])) {
                    continue;
                }
                $seen[$subjectId] = true;

                $cage = $subject->getCage();
                if (null === $cage) {
                    continue;
                }
                $cageKey = (string) $cage->getId();
                if (!isset($byCage[$cageKey])) {
                    $byCage[$cageKey] = ['cage' => $cage, 'subjects' => []];
                }
                $byCage[$cageKey]['subjects'][] = $subject;
            }
        }

        $cages = [];
        foreach ($byCage as ['cage' => $cage, 'subjects' => $subjects]) {
            $hcm = $cage->getHomeCageMonitoring();
            $lightsOnUtcHour = $hcm?->getLightsOnUtcHour();
            $lightDurationHours = $hcm?->getLightDurationHours() ?? 12;
            $metrics = $this->circadianService->computeForCage($cage, $lightsOnUtcHour, $lightDurationHours, $from, $to);

            $environment = $cage->getEnvironmentHousing();
            $facility = $cage->getAnimalFacility();
            $lightCycle = null !== $lightsOnUtcHour
                ? $lightDurationHours . ':' . (24 - $lightDurationHours)
                : (null !== $environment?->getLuminosity() ? '12:12' : '12:12');

            $animals = [];
            foreach ($subjects as $subject) {
                $ageWeeks = 0;

                // Map CircadianMetrics → circadianParameters shape the dashboard expects
                $amplitude = $metrics->dataPointCount > 0 ? $metrics->amplitude : (float) random_int(5, 80) / 10;
                $acrophaseZt = $metrics->acrophaseZeitgeber ?? random_int(0, 23);
                $phase = round(($acrophaseZt / 24) * 2 * \M_PI, 4);
                $baseline = $metrics->dataPointCount > 0
                    ? round($metrics->amplitude > 0 ? $amplitude * 0.4 : 2.0, 2)
                    : (float) random_int(10, 30) / 10;
                $noise = round($metrics->fragmentation, 4);

                $animals[] = [
                    'animalId' => $subject->getExternalId() ?? $subject->getName(),
                    'metadata' => [
                        'species' => $subject->getSpecies()->value,
                        'strain' => $subject->getStrain()->getName(),
                        'sex' => $subject->getSex()->value,
                        'ageWeeks' => $ageWeeks,
                        'genotype' => $subject->getGenotype() ?? '',
                        'sanitaryStatus' => $facility?->getSanitaryStatus() ?? '',
                        'supplier' => '',
                        'facility' => $facility?->getName() ?? '',
                        'room' => $environment?->getRoom() ?? '',
                        'rack' => 'rack-' . ($cage->getExternalId() ?? substr((string) $cage->getId(), 0, 8)),
                        'beddingType' => $cage->getBeddingType()->value,
                        'dietType' => $cage->isFood() ? 'standard chow' : 'restricted',
                        'waterSystem' => $cage->isWater() ? 'available' : 'restricted',
                        'temperatureMean' => $environment?->getTemperature() ?? 22.0,
                        'humidityMean' => $environment?->getHumidity() ?? 55.0,
                        'lightCycle' => $lightCycle,
                        'noiseLevel' => 'standard',
                        'dvcSystem' => $hcm?->getSystem() ?? 'Tecniplast DVC',
                        'firmwareVersion' => 'metadatapp-1',
                        'analyticsVersion' => 'circadian-service-1',
                        'sourceApp' => 'metadatapp',
                        'treatment' => '',
                        'dose' => '',
                        'administrationRoute' => '',
                        'project' => $experiment->getProject()?->getName() ?? '',
                        'protocol' => $experiment->getProtocol() ?? '',
                    ],
                    'circadianParameters' => [
                        'amplitude' => $amplitude,
                        'phase' => $phase,
                        'baseline' => $baseline,
                        'noise' => $noise,
                    ],
                ];
            }

            $cages[] = [
                'cageId' => $cage->getExternalId() ?? (string) $cage->getId(),
                'system' => $hcm?->getSystem() ?? 'Tecniplast DVC',
                'location' => [
                    'facility' => $facility?->getName() ?? '',
                    'room' => $environment?->getRoom() ?? '',
                    'rack' => 'rack-' . ($cage->getExternalId() ?? substr((string) $cage->getId(), 0, 8)),
                ],
                'animals' => $animals,
            ];
        }

        return $this->json([
            'experimentId' => (string) $experiment->getId(),
            'experimentName' => $experiment->getName(),
            'window' => [
                'from' => $from->format(\DateTimeInterface::ATOM),
                'to' => $to->format(\DateTimeInterface::ATOM),
            ],
            'sourceApp' => 'metadatapp',
            'cages' => $cages,
        ]);
    }

    #[Route('/api/hcm/systems/summary', name: 'api_hcm_systems_summary', methods: ['GET'])]
    public function getHcmSystemsSummary(Request $request): JsonResponse
    {
        $user = $this->getCurrentUser();
        $account = $user->getAccount();
        $from = $request->query->has('from') ? new \DateTimeImmutable($request->query->getString('from')) : null;
        $to = $request->query->has('to') ? new \DateTimeImmutable($request->query->getString('to')) : null;

        /** @var list<CageHcmSinusoidMetadata> $sinusoids */
        $sinusoids = $this->entityManager->getRepository(CageHcmSinusoidMetadata::class)->findBy(
            ['account' => $account],
            ['date' => 'ASC', 'sourceAppCode' => 'ASC'],
        );

        $cages = [];
        $systems = [];
        foreach ($sinusoids as $sinusoid) {
            if ((null !== $from && $sinusoid->getDate() < $from) || (null !== $to && $sinusoid->getDate() > $to)) {
                continue;
            }

            $cage = $sinusoid->getCage();
            $hcm = $sinusoid->getHomeCageMonitoring();
            $hcmId = (string) $hcm->getId();
            $system = $hcm->getSystem();
            $environment = $cage->getEnvironmentHousing();
            $facility = $cage->getAnimalFacility();

            /** @var list<CageHcmMeasure> $measures */
            $measures = $this->entityManager->getRepository(CageHcmMeasure::class)->findBy([
                'account' => $account,
                'cage' => $cage,
                'homeCageMonitoring' => $hcm,
            ]);
            $humanMovementWorkday = $this->humanMovementWorkdayFromMeasures($measures);

            $modalities = $this->modalitiesForHcmSystem($system, $measures);
            $systems[$hcmId] = [
                'id' => $hcmId,
                'system' => $system,
                'modalities' => $modalities,
                'sourceAppCode' => $sinusoid->getSourceAppCode() ?? $this->sourceCodeForHcmSystem($system),
                'cageCount' => ($systems[$hcmId]['cageCount'] ?? 0) + 1,
            ];

            $subjects = $cage->getSubjects()->toArray();
            if ([] === $subjects) {
                $subjects = [null];
            }

            $animals = [];
            foreach ($subjects as $subject) {
                $birthAt = $subject?->getBirthAt();
                $ageWeeks = null !== $birthAt
                    ? (int) floor($birthAt->diff(new \DateTimeImmutable())->days / 7)
                    : 0;

                $animals[] = [
                    'animalId' => $subject?->getExternalId() ?? $subject?->getName() ?? ($cage->getExternalId() ?? (string) $cage->getId()),
                    'metadata' => [
                        'species' => $subject?->getSpecies()->value ?? 'mouse',
                        'strain' => $subject?->getStrain()->getName() ?? '',
                        'sex' => $subject?->getSex()->value ?? 'male',
                        'ageWeeks' => $ageWeeks,
                        'genotype' => $subject?->getGenotype() ?? '',
                        'supplier' => 'demo colony',
                        'sanitaryStatus' => $facility?->getSanitaryStatus() ?? 'SPF',
                        'project' => $hcm->getExperiment()->getProject()?->getName() ?? '',
                        'protocol' => $hcm->getExperiment()->getProtocol() ?? $hcm->getExperiment()->getName(),
                        'treatment' => $this->treatmentLabelForHcmSystem($system),
                        'dose' => '',
                        'administrationRoute' => '',
                        'facility' => $facility?->getName() ?? '',
                        'room' => $environment?->getRoom() ?? '',
                        'rack' => 'rack-' . ($cage->getExternalId() ?? substr((string) $cage->getId(), 0, 8)),
                        'beddingType' => $cage->getBeddingType()->value,
                        'dietType' => $cage->isFood() ? 'standard chow' : 'restricted',
                        'waterSystem' => $cage->isWater() ? 'available' : 'restricted',
                        'temperatureMean' => $environment?->getTemperature() ?? 22.0,
                        'humidityMean' => $environment?->getHumidity() ?? 55.0,
                        'lightIntensityMean' => $environment?->getLuminosity() ?? 0.0,
                        'lightCycle' => ($hcm->getLightDurationHours() ?? 12) . ':' . (24 - ($hcm->getLightDurationHours() ?? 12)),
                        'noiseLevel' => null === $environment?->getNoise() ? 'standard' : (string) $environment->getNoise(),
                        'noiseMean' => $environment?->getNoise() ?? 0.0,
                        'humanMovementWorkday' => $humanMovementWorkday,
                        'dvcSystem' => $system,
                        'firmwareVersion' => 'demo-' . strtolower(str_replace(' ', '-', $system)),
                        'analyticsVersion' => 'mirosine-hcm-1',
                        'sourceApp' => $sinusoid->getSourceAppCode() ?? $this->sourceCodeForHcmSystem($system),
                        'modalities' => $modalities,
                    ],
                    'circadianParameters' => [
                        'amplitude' => $sinusoid->getAmplitude(),
                        'phase' => $sinusoid->getPhase(),
                        'baseline' => $sinusoid->getMesor(),
                        'noise' => $this->noiseFromMeasures($measures),
                    ],
                ];
            }

            $cages[] = [
                'cageId' => $cage->getExternalId() ?? (string) $cage->getId(),
                'system' => $system,
                'modalities' => $modalities,
                'location' => [
                    'facility' => $facility?->getName() ?? '',
                    'room' => $environment?->getRoom() ?? '',
                    'rack' => 'rack-' . ($cage->getExternalId() ?? substr((string) $cage->getId(), 0, 8)),
                ],
                'environment' => [
                    'temperature' => $environment?->getTemperature(),
                    'humidity' => $environment?->getHumidity(),
                    'lightIntensity' => $environment?->getLuminosity(),
                    'noise' => $environment?->getNoise(),
                    'lightCycle' => $environment?->getLightCycle()?->value,
                    'humanMovementWorkday' => $humanMovementWorkday,
                ],
                'animals' => $animals,
            ];
        }

        return $this->json([
            'systems' => array_values($systems),
            'cages' => $cages,
            'outputs' => [
                'groupStatistics' => 'Amplitude, mesor, phase, and peak-hour summaries by HCM system and selected cohort.',
                'scientificReport' => 'Reusable HCM comparison report with system modality provenance and MIROsine parameters.',
                'exportPackage' => 'FAIR-ready table for downstream circadian and home-cage monitoring analyses.',
            ],
        ]);
    }

    #[Route('/api/dvc/cages/{id}/activity', name: 'api_dvc_cage_activity', methods: ['GET'])]
    public function getActivity(string $id, Request $request): JsonResponse
    {
        $cage = $this->findAccessibleCage($id);
        if (null === $cage) {
            return $this->json(['message' => 'DVC cage not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->activityPayload($cage, $request));
    }

    #[Route('/api/dvc/cages/{id}/environment', name: 'api_dvc_cage_environment', methods: ['GET'])]
    public function getEnvironment(string $id, Request $request): JsonResponse
    {
        $cage = $this->findAccessibleCage($id);
        if (null === $cage) {
            return $this->json(['message' => 'DVC cage not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->environmentPayload($cage, $request));
    }

    #[Route('/api/dvc/cages/{id}/events', name: 'api_dvc_cage_events', methods: ['GET'])]
    public function getEvents(string $id): JsonResponse
    {
        $cage = $this->findAccessibleCage($id);
        if (null === $cage) {
            return $this->json(['message' => 'DVC cage not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => [[
                'id' => 'event-' . $this->cageId($cage) . '-linked',
                'cageId' => $this->cageId($cage),
                'ts' => (new \DateTimeImmutable('-7 days'))->format(\DateTimeInterface::ATOM),
                'type' => 'cleaning',
                'payload' => [
                    'notes' => 'DVC cage context linked to Metadatapp experiment metadata.',
                ],
            ]],
        ]);
    }

    #[Route('/api/dvc/cages/{id}/welfare-alarms', name: 'api_dvc_cage_alarms', methods: ['GET'])]
    public function getAlarms(string $id): JsonResponse
    {
        $cage = $this->findAccessibleCage($id);
        if (null === $cage) {
            return $this->json(['message' => 'DVC cage not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['data' => []]);
    }

    #[Route('/api/dvc/cages/{id}/ai', name: 'api_dvc_cage_ai', methods: ['GET'])]
    public function getAi(string $id, Request $request): JsonResponse
    {
        $cage = $this->findAccessibleCage($id);
        if (null === $cage) {
            return $this->json(['message' => 'DVC cage not found.'], Response::HTTP_NOT_FOUND);
        }

        $activity = $this->activityPayload($cage, $request)['activity'];
        $mean = [] === $activity ? 0.0 : array_sum($activity) / \count($activity);
        $nightMean = $this->windowMean($activity, 2, 4);
        $dayMean = $this->windowMean($activity, 0, 2);
        $stability = $mean > 0 ? min(100, max(0, (int) round(($nightMean / max($mean, 1)) * 55))) : 0;
        $sleepScore = $dayMean > 0 ? min(100, max(0, (int) round(100 - ($dayMean / max($nightMean, 1)) * 30))) : 0;
        $anomaly = $stability < 35 ? 65 : 18;

        return $this->json([
            'id' => 'ai-' . $this->cageId($cage),
            'cageId' => $this->cageId($cage),
            'windowStart' => (new \DateTimeImmutable($request->query->getString('from', '-7 days')))->format(\DateTimeInterface::ATOM),
            'windowEnd' => (new \DateTimeImmutable($request->query->getString('to', 'now')))->format(\DateTimeInterface::ATOM),
            'sleepScore' => $sleepScore,
            'circadianStability' => $stability,
            'fragmentation' => max(0, 100 - $stability),
            'anomalyScore' => $anomaly,
            'label' => $anomaly > 40 ? 'circadian' : 'sleep',
            'explanation' => \sprintf('Derived from %d linked DVC activity points stored in Metadatapp.', \count($activity)),
        ]);
    }

    #[Route('/api/dvc/cages/{id}/context-snapshot', name: 'api_dvc_cage_context', methods: ['GET'])]
    public function getContext(string $id): JsonResponse
    {
        $cage = $this->findAccessibleCage($id);
        if (null === $cage) {
            return $this->json(['message' => 'DVC cage not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->contextPayload($cage));
    }

    #[Route('/api/dvc/cages/{id}/circadian', name: 'api_dvc_cage_circadian', methods: ['GET'])]
    public function getCircadian(string $id, Request $request): JsonResponse
    {
        $cage = $this->findAccessibleCage($id);
        if (null === $cage) {
            return $this->json(['message' => 'DVC cage not found.'], Response::HTTP_NOT_FOUND);
        }

        $hcm = $cage->getHomeCageMonitoring();
        $lightsOnUtcHour = $hcm?->getLightsOnUtcHour();
        $lightDurationHours = $hcm?->getLightDurationHours() ?? 12;

        $fromRaw = $request->query->getString('from', '-7 days');
        $toRaw = $request->query->getString('to', 'now');

        try {
            $from = new \DateTimeImmutable($fromRaw, new \DateTimeZone('UTC'));
            $to = new \DateTimeImmutable($toRaw, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return $this->json(['message' => 'Invalid date range parameters.'], Response::HTTP_BAD_REQUEST);
        }

        $metrics = $this->circadianService->computeForCage($cage, $lightsOnUtcHour, $lightDurationHours, $from, $to);

        return $this->json([
            'cageId' => $this->cageId($cage),
            'windowStart' => $from->format(\DateTimeInterface::ATOM),
            'windowEnd' => $to->format(\DateTimeInterface::ATOM),
            'metrics' => $metrics->toArray(),
        ]);
    }

    #[Route('/api/dvc/compare', name: 'api_dvc_compare', methods: ['GET'])]
    public function compare(Request $request): JsonResponse
    {
        $ids = array_values(array_filter(array_map('trim', explode(',', $request->query->getString('cageIds')))));
        $cages = array_values(array_filter(array_map(fn (string $id): ?Cage => $this->findAccessibleCage($id), $ids)));

        return $this->json([
            'data' => array_map(fn (Cage $cage): array => $this->activityPayload($cage, $request), $cages),
            'context' => array_map(fn (Cage $cage): array => $this->contextPayload($cage), $cages),
            'quality' => array_map(fn (Cage $cage): array => $this->qualityPayload($cage, $request), $cages),
        ]);
    }

    private function findAccessibleCage(string $id): ?Cage
    {
        $user = $this->getCurrentUser();
        $repository = $this->entityManager->getRepository(Cage::class);
        /** @var Cage|null $cage */
        $cage = Uuid::isValid($id)
            ? $repository->find(Uuid::fromString($id))
            : $repository->findOneBy(['externalId' => $id, 'account' => $user->getAccount()]);

        $cageAccountId = $cage?->getAccount()->getId();
        $userAccountId = $user->getAccount()->getId();
        if (!$cage instanceof Cage || null === $cageAccountId || null === $userAccountId || !$cageAccountId->equals($userAccountId)) {
            return null;
        }

        return $cage;
    }

    /**
     * @return array{cageId: string, pid: string, ts: list<string>, activity: list<float>}
     */
    private function activityPayload(Cage $cage, Request $request): array
    {
        $rows = $this->timeSeriesForCage($cage, $request);

        return [
            'cageId' => $this->cageId($cage),
            'pid' => 'urn:os:dvc:cage:' . $this->cageId($cage),
            'ts' => array_map(static fn (TimeSeries $row): string => $row->getRecordedAt()->format(\DateTimeInterface::ATOM), $rows),
            'activity' => array_map(static fn (TimeSeries $row): float => (float) $row->getValue(), $rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentPayload(Cage $cage, Request $request): array
    {
        $activity = $this->activityPayload($cage, $request);
        $ts = [] !== $activity['ts'] ? $activity['ts'] : [(new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)];
        $environment = $cage->getEnvironmentHousing();

        return [
            'scopeType' => 'cage',
            'scopeId' => $this->cageId($cage),
            'pid' => 'urn:os:dvc:cage:' . $this->cageId($cage),
            'ts' => $ts,
            'temp' => array_fill(0, \count($ts), $environment?->getTemperature() ?? 22.0),
            'humidity' => array_fill(0, \count($ts), $environment?->getHumidity() ?? 50.0),
            'light' => array_fill(0, \count($ts), $environment?->getLuminosity() ?? 300.0),
            'noise' => array_fill(0, \count($ts), $environment?->getNoise() ?? 40.0),
            'co2' => array_fill(0, \count($ts), 450),
            'ammonia' => array_fill(0, \count($ts), 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contextPayload(Cage $cage): array
    {
        $environment = $cage->getEnvironmentHousing();
        $facility = $cage->getAnimalFacility();

        return [
            'cageId' => $this->cageId($cage),
            'pid' => 'urn:os:dvc:cage:' . $this->cageId($cage),
            'room' => $environment?->getRoom() ?? 'n/a',
            'rack' => 'Metadatapp demo rack',
            'cageType' => $cage->getType()->value,
            'facility' => $facility?->getName() ?? 'n/a',
            'beddingBatch' => 'demo-bedding-batch',
            'beddingMaterial' => $cage->getBeddingType()->value,
            'sanitaryStatus' => $facility?->getSanitaryStatus() ?? 'n/a',
            'diet' => $cage->isFood() ? 'standard chow' : 'not recorded',
            'water' => $cage->isWater() ? 'available' : 'not recorded',
            'device' => $cage->getHomeCageMonitoring()?->getSystem() ?? 'Tecniplast DVC',
            'firmware' => 'demo',
            'pipelineVersion' => 'metadatapp-demo-1',
            'lastChangeout' => (new \DateTimeImmutable('-7 days'))->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function qualityPayload(Cage $cage, Request $request): array
    {
        $activity = $this->activityPayload($cage, $request);

        return [
            'cageId' => $this->cageId($cage),
            'pid' => 'urn:os:dvc:cage:' . $this->cageId($cage),
            'missingDataPct' => 0,
            'lastSeen' => end($activity['ts']) ?: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'sensorHealth' => [] === $activity['ts'] ? 'warn' : 'good',
            'pipelineVersion' => 'metadatapp-demo-1',
        ];
    }

    /**
     * @return list<TimeSeries>
     */
    private function timeSeriesForCage(Cage $cage, Request $request): array
    {
        $queryBuilder = $this->entityManager->getRepository(TimeSeries::class)
            ->createQueryBuilder('t')
            ->join('t.subject', 's')
            ->join('t.variable', 'v')
            ->andWhere('s.cage = :cage')
            ->andWhere('t.account = :account')
            ->andWhere('LOWER(v.name) LIKE :activity')
            ->setParameter('cage', $cage)
            ->setParameter('account', $cage->getAccount())
            ->setParameter('activity', '%activity%')
            ->orderBy('t.recordedAt', 'ASC')
        ;

        if ($request->query->has('from')) {
            $queryBuilder
                ->andWhere('t.recordedAt >= :from')
                ->setParameter('from', new \DateTimeImmutable($request->query->getString('from')))
            ;
        }

        if ($request->query->has('to')) {
            $queryBuilder
                ->andWhere('t.recordedAt <= :to')
                ->setParameter('to', new \DateTimeImmutable($request->query->getString('to')))
            ;
        }

        /** @var list<TimeSeries> $rows */
        $rows = $queryBuilder->getQuery()->getResult();
        if ([] !== $rows || (!$request->query->has('from') && !$request->query->has('to'))) {
            return $rows;
        }

        // Demo fallback: if the requested live window has no seeded records,
        // return the linked experiment window so the DVC dashboard remains usable.
        return $this->entityManager->getRepository(TimeSeries::class)
            ->createQueryBuilder('t')
            ->join('t.subject', 's')
            ->join('t.variable', 'v')
            ->andWhere('s.cage = :cage')
            ->andWhere('t.account = :account')
            ->andWhere('LOWER(v.name) LIKE :activity')
            ->setParameter('cage', $cage)
            ->setParameter('account', $cage->getAccount())
            ->setParameter('activity', '%activity%')
            ->orderBy('t.recordedAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    private function cageId(Cage $cage): string
    {
        return $cage->getExternalId() ?? $cage->getId()?->toRfc4122() ?? 'unknown';
    }

    /**
     * @param list<CageHcmMeasure> $measures
     *
     * @return list<string>
     */
    private function modalitiesForHcmSystem(string $system, array $measures): array
    {
        $modalities = match ($system) {
            'Olden Labs HCM' => ['video tracking', 'sinusoid extraction'],
            'Live Mouse Tracker' => ['RFID', 'video tracking', 'social proximity'],
            'Open-BEATBox' => ['video tracking', 'cognitive events', 'in vivo recordings'],
            default => ['activity', 'environment', 'sinusoid extraction'],
        };

        foreach ($measures as $measure) {
            $name = strtolower($measure->getVariable()->getName());
            if (str_contains($name, 'rfid')) {
                $modalities[] = 'RFID';
            }
            if (str_contains($name, 'video')) {
                $modalities[] = 'video tracking';
            }
            if (str_contains($name, 'cognitive')) {
                $modalities[] = 'cognitive events';
            }
            if (str_contains($name, 'vivo')) {
                $modalities[] = 'in vivo recordings';
            }
            if (str_contains($name, 'temperature')) {
                $modalities[] = 'temperature';
            }
            if (str_contains($name, 'humidity')) {
                $modalities[] = 'humidity';
            }
            if (str_contains($name, 'light intensity')) {
                $modalities[] = 'light intensity';
            }
            if (str_contains($name, 'noise')) {
                $modalities[] = 'noise';
            }
            if (str_contains($name, 'human movement')) {
                $modalities[] = 'human movement';
            }
        }

        return array_values(array_unique($modalities));
    }

    private function sourceCodeForHcmSystem(string $system): string
    {
        return match ($system) {
            'Olden Labs HCM' => 'olden_labs_hcm',
            'Live Mouse Tracker' => 'live_mouse_tracker',
            'Open-BEATBox' => 'open_beatbox',
            default => 'tecniplast_dvc',
        };
    }

    private function treatmentLabelForHcmSystem(string $system): string
    {
        return match ($system) {
            'Olden Labs HCM' => 'video-only baseline',
            'Live Mouse Tracker' => 'RFID + video social tracking',
            'Open-BEATBox' => 'cognitive + in vivo recording',
            default => 'DVC baseline',
        };
    }

    /**
     * @param list<CageHcmMeasure> $measures
     */
    private function noiseFromMeasures(array $measures): float
    {
        if ([] === $measures) {
            return 0.08;
        }

        $values = array_map(static fn (CageHcmMeasure $measure): float => $measure->getValue(), $measures);
        $mean = array_sum($values) / \count($values);
        if (0.0 === $mean) {
            return 0.08;
        }

        $variance = array_sum(array_map(static fn (float $value): float => ($value - $mean) ** 2, $values)) / \count($values);

        return round(min(0.45, sqrt($variance) / max($mean, 1.0)), 3);
    }

    /**
     * @param list<CageHcmMeasure> $measures
     */
    private function humanMovementWorkdayFromMeasures(array $measures): float
    {
        foreach ($measures as $measure) {
            if (str_contains(strtolower($measure->getVariable()->getName()), 'human movement')) {
                return $measure->getValue();
            }
        }

        return 0.0;
    }

    /**
     * @param list<float> $values
     */
    private function windowMean(array $values, int $offset, int $step): float
    {
        $window = [];
        for ($index = $offset; $index < \count($values); $index += max(1, $step)) {
            $window[] = $values[$index];
        }

        return [] === $window ? 0.0 : array_sum($window) / \count($window);
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            return $user;
        }

        if ($user instanceof UserInterface) {
            /** @var User|null $managedUser */
            $managedUser = $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => $user->getUserIdentifier(),
            ]);

            if ($managedUser instanceof User) {
                return $managedUser;
            }
        }

        throw $this->createAccessDeniedException('Authentication required.');
    }
}
