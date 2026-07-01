<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AccountAwareInterface;
use App\Entity\Procedure;
use App\Entity\Project;
use App\Entity\Subject;
use App\Entity\Treatment;
use App\Entity\User;
use App\Entity\WeightMeasurement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
final class ProjectWorkspaceController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/projects/activity', name: 'api_projects_activity', methods: ['GET'])]
    #[Route('/investigation/activity', name: 'api_investigation_activity', methods: ['GET'])]
    #[Route('/project-workspace/activity', name: 'api_project_workspace_activity', methods: ['GET'])]
    public function activity(): JsonResponse
    {
        $user = $this->getCurrentUser();
        /** @var list<Project> $projects */
        $projects = $this->entityManager->getRepository(Project::class)
            ->createQueryBuilder('p')
            ->andWhere('p.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('p.startAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;

        $items = array_map(static fn (Project $project): array => [
            'id' => 'project-' . $project->getId(),
            'investigationId' => (string) $project->getId(),
            'investigationName' => $project->getName(),
            'at' => ($project->getStartAt() ?? new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'title' => 'Investigation available',
            'detail' => $project->getDescription() ?? $project->getGoal() ?? 'Investigation metadata is ready for review.',
            'actor' => $project->getUser()->getName(),
        ], $projects);

        return $this->json(['data' => $items]);
    }

    #[Route('/keyboard-shortcuts', name: 'api_keyboard_shortcuts', methods: ['GET'])]
    public function keyboardShortcuts(): JsonResponse
    {
        return $this->json([
            'data' => [
                ['id' => 'animal-next', 'keys' => 'ArrowDown', 'label' => 'Focus next animal'],
                ['id' => 'animal-prev', 'keys' => 'ArrowUp', 'label' => 'Focus previous animal'],
                ['id' => 'animal-open', 'keys' => 'Enter', 'label' => 'Open focused animal'],
                ['id' => 'animal-favorite', 'keys' => 'F', 'label' => 'Toggle favorite animal'],
            ],
        ]);
    }

    #[Route('/projects/{id}/tags', name: 'api_project_tags_update', methods: ['PATCH'])]
    #[Route('/investigation/{id}/tags', name: 'api_investigation_tags_update', methods: ['PATCH'])]
    public function updateTags(string $id, Request $request): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        $payload = json_decode($request->getContent(), true);
        $tags = \is_array($payload) && \is_array($payload['tags'] ?? null)
            ? array_values(array_filter($payload['tags'], static fn (mixed $tag): bool => \is_string($tag) && '' !== trim($tag)))
            : [];

        return $this->json($this->projectSummary($project, $tags));
    }

    #[Route('/projects/{id}/dashboard', name: 'api_project_dashboard', methods: ['GET'])]
    #[Route('/investigation/{id}/dashboard', name: 'api_investigation_dashboard', methods: ['GET'])]
    public function dashboard(string $id): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        $animals = $this->subjectsForProject($project);
        $animalPayloads = array_map(fn (Subject $subject): array => $this->animalPayload($subject, $project), $animals);

        return $this->json([
            'investigationId' => (string) $project->getId(),
            'animalsAtRisk' => array_values(array_filter(
                array_map(fn (array $animal): ?array => $this->atRiskAnimalPayload($animal), $animalPayloads)
            )),
            'groupWeights' => $this->groupWeightPoints($animals),
            'markerTrends' => $this->markerTrends($project, $animals),
            'recentActions' => $this->recentActions($project, $animals),
        ]);
    }

    #[Route('/projects/{id}/animals', name: 'api_project_animals', methods: ['GET'])]
    #[Route('/investigation/{id}/animals', name: 'api_investigation_animals', methods: ['GET'])]
    #[Route('/study/{id}/animals', name: 'api_investigation_animals_study_alias', methods: ['GET'])]
    #[Route('/experiments/{id}/animals', name: 'api_project_animals_legacy_experiments_alias', methods: ['GET'])]
    public function animals(string $id): JsonResponse
    {
        $project = $this->findAccessibleProject($id);

        return $this->json([
            'data' => array_map(fn (Subject $subject): array => $this->animalPayload($subject, $project), $this->subjectsForProject($project)),
        ]);
    }

    #[Route('/projects/{id}/animals', name: 'api_project_animals_assign', methods: ['POST'])]
    #[Route('/investigation/{id}/animals', name: 'api_investigation_animals_assign', methods: ['POST'])]
    public function assignAnimals(string $id): JsonResponse
    {
        $project = $this->findAccessibleProject($id);

        return $this->json($this->projectSummary($project));
    }

    #[Route('/projects/{id}/animals/export', name: 'api_project_animals_export_status', methods: ['GET'])]
    #[Route('/investigation/{id}/animals/export', name: 'api_investigation_animals_export_status', methods: ['GET'])]
    public function animalExportStatus(string $id): JsonResponse
    {
        $project = $this->findAccessibleProject($id);

        return $this->json([
            'investigationId' => (string) $project->getId(),
            'rowCount' => \count($this->subjectsForProject($project)),
        ]);
    }

    #[Route('/projects/{id}/animals/export', name: 'api_project_animals_export', methods: ['POST'])]
    #[Route('/investigation/{id}/animals/export', name: 'api_investigation_animals_export', methods: ['POST'])]
    public function animalExport(string $id): JsonResponse
    {
        $project = $this->findAccessibleProject($id);
        $animals = array_map(fn (Subject $subject): array => $this->animalPayload($subject, $project), $this->subjectsForProject($project));

        $rows = ['id,tagId,nickname,cage,lastWeight'];
        foreach ($animals as $animal) {
            $rows[] = implode(',', array_map($this->escapeCsv(...), [
                $animal['id'],
                $animal['tagId'],
                $animal['nickname'],
                $animal['cage'],
                (string) $animal['lastWeight'],
            ]));
        }

        return $this->json([
            'exportedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'rowCount' => \count($animals),
            'csv' => implode("\n", $rows),
        ]);
    }

    #[Route('/animals/{id}', name: 'api_animal_detail', methods: ['GET'])]
    public function animalDetail(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);

        return $this->json($this->animalPayload($subject, $this->projectForSubject($subject)));
    }

    #[Route('/animals/{id}/note', name: 'api_animal_note', methods: ['PATCH'])]
    public function updateAnimalNote(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);

        return $this->json($this->animalPayload($subject, $this->projectForSubject($subject)));
    }

    #[Route('/animals/{id}/favorite', name: 'api_animal_favorite', methods: ['PATCH'])]
    public function updateAnimalFavorite(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);

        return $this->json($this->animalPayload($subject, $this->projectForSubject($subject)));
    }

    #[Route('/animals/{id}/weights', name: 'api_animal_weights', methods: ['GET'])]
    public function animalWeights(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);
        $weights = $subject->getWeightMeasurements()->toArray();
        usort($weights, static fn (WeightMeasurement $a, WeightMeasurement $b): int => $a->getMeasuredAt() <=> $b->getMeasuredAt());

        return $this->json([
            'data' => array_map(fn (WeightMeasurement $measurement): array => $this->weightPayload($subject, $measurement), $weights),
        ]);
    }

    #[Route('/animals/{id}/weights', name: 'api_animal_weight_create', methods: ['POST'])]
    public function createAnimalWeight(string $id, Request $request): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);
        $payload = json_decode($request->getContent(), true);
        $grams = \is_array($payload) && is_numeric($payload['grams'] ?? null) ? (float) $payload['grams'] : ($subject->getWeight() ?? 0.0);
        $measuredAt = \is_array($payload) && \is_string($payload['at'] ?? null) ? new \DateTime($payload['at']) : new \DateTime();

        $measurement = (new WeightMeasurement())
            ->setSubject($subject)
            ->setWeight($grams)
            ->setMeasuredAt($measuredAt)
            ->setUser($this->getCurrentUser())
            ->setAccount($subject->getAccount())
        ;
        $this->entityManager->persist($measurement);
        $this->entityManager->flush();

        return $this->json($this->weightPayload($subject, $measurement), 201);
    }

    #[Route('/animals/{id}/weight-range', name: 'api_animal_weight_range', methods: ['GET'])]
    public function animalWeightRange(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);
        $weights = array_map(fn (WeightMeasurement $measurement): float => $this->weightInGrams($measurement->getWeight()), $subject->getWeightMeasurements()->toArray());
        $target = [] !== $weights ? round(array_sum($weights) / \count($weights), 2) : $this->weightInGrams($subject->getWeight() ?? 25.0);

        return $this->json([
            'min' => round(max(0.0, $target - 3.0), 2),
            'max' => round($target + 3.0, 2),
            'target' => $target,
        ]);
    }

    #[Route('/animals/{id}/treatments', name: 'api_animal_treatments', methods: ['GET'])]
    public function animalTreatments(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);
        $events = [];
        foreach ($subject->getProcedures() as $procedure) {
            if (!$procedure instanceof Treatment) {
                continue;
            }
            $treatmentType = $procedure->getTreatmentType();

            $events[] = [
                'id' => (string) $procedure->getId(),
                'animalId' => (string) $subject->getId(),
                'drug' => $procedure->getDrug() ?? $procedure->getName(),
                'pathogen' => null !== $treatmentType ? $treatmentType->value : 'treatment',
                'lot' => $procedure->getExternalId() ?? 'N/A',
                'dose' => $procedure->getDose() ?? 0,
                'doseUnit' => $procedure->getDoseUnit() ?? 'mg/kg',
                'operator' => $procedure->getUser()->getName(),
                'administeredAt' => ($procedure->getStartAt() ?? new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'notes' => $procedure->getName(),
            ];
        }

        return $this->json(['data' => $events]);
    }

    #[Route('/animals/{id}/treatments', name: 'api_animal_treatment_create', methods: ['POST'])]
    public function createAnimalTreatment(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);

        return $this->json([
            'id' => 'treatment-' . bin2hex(random_bytes(4)),
            'animalId' => (string) $subject->getId(),
            'drug' => 'Recorded treatment',
            'pathogen' => 'manual',
            'lot' => 'N/A',
            'dose' => 0,
            'doseUnit' => 'mg/kg',
            'operator' => $this->getCurrentUser()->getName(),
            'administeredAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], 201);
    }

    #[Route('/animals/{id}/samples', name: 'api_animal_samples', methods: ['GET'])]
    public function animalSamples(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);
        $project = $this->projectForSubject($subject);

        return $this->json([
            'data' => array_values(array_map(fn (WeightMeasurement $measurement): array => [
                'id' => (string) $measurement->getId(),
                'animalId' => (string) $subject->getId(),
                'investigationId' => $project instanceof Project ? (string) $project->getId() : '',
                'sampleId' => 'BW-' . substr((string) $measurement->getId(), 0, 8),
                'collectedAt' => $measurement->getMeasuredAt()->format(\DateTimeInterface::ATOM),
                'operator' => $measurement->getUser()->getName(),
                'volume' => $this->weightInGrams($measurement->getWeight()),
                'volumeUnit' => 'g',
                'site' => 'body weight',
            ], $subject->getWeightMeasurements()->toArray())),
        ]);
    }

    #[Route('/animals/{id}/samples', name: 'api_animal_sample_create', methods: ['POST'])]
    public function createAnimalSample(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);
        $project = $this->projectForSubject($subject);

        return $this->json([
            'id' => 'sample-' . bin2hex(random_bytes(4)),
            'animalId' => (string) $subject->getId(),
            'investigationId' => $project instanceof Project ? (string) $project->getId() : '',
            'sampleId' => 'MANUAL-' . strtoupper(bin2hex(random_bytes(3))),
            'collectedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'operator' => $this->getCurrentUser()->getName(),
            'volume' => 0,
            'volumeUnit' => 'uL',
            'site' => 'manual',
        ], 201);
    }

    #[Route('/animals/{id}/analysis', name: 'api_animal_analysis', methods: ['GET'])]
    public function animalAnalysis(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);
        $weights = array_map(fn (WeightMeasurement $measurement): float => $this->weightInGrams($measurement->getWeight()), $subject->getWeightMeasurements()->toArray());
        $trend = [] !== $weights ? $weights : [$this->weightInGrams($subject->getWeight() ?? 25.0)];

        return $this->json([
            'flow' => [['id' => 'flow-weight', 'panel' => 'Welfare', 'marker' => 'Weight', 'population' => 'Body mass', 'value' => end($trend), 'unit' => 'g', 'qcStatus' => 'pass']],
            'pcr' => [['id' => 'pcr-genotype', 'target' => $subject->getGenotype() ?? 'genotype', 'ct' => 0, 'deltaCt' => 0, 'qcStatus' => 'pass']],
            'antibody' => [['id' => 'ab-health', 'antigen' => $subject->getHealthStatus()->value, 'titer' => 1, 'unit' => 'status', 'qcStatus' => 'pass']],
            'trends' => [['id' => 'weight-trend', 'label' => 'Weight trend', 'values' => $trend]],
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/animals/{id}/behavior', name: 'api_animal_behavior', methods: ['GET'])]
    public function animalBehavior(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);
        $points = [];
        $anchor = new \DateTimeImmutable('-24 hours');
        for ($index = 0; $index < 12; ++$index) {
            $at = $anchor->modify(\sprintf('+%d hours', $index * 2));
            $points[] = [
                'at' => $at->format(\DateTimeInterface::ATOM),
                'activity' => 45 + (($index * 17 + \strlen($subject->getName())) % 80),
                'phase' => (int) $at->format('H') >= 18 || (int) $at->format('H') < 6 ? 'night' : 'day',
                'anomaly' => 8 === $index,
            ];
        }

        return $this->json([
            'points' => $points,
            'baseline' => 60,
            'circadianPeak' => 'ZT14',
            'circadianLow' => 'ZT4',
        ]);
    }

    #[Route('/animals/{id}/timeline', name: 'api_animal_timeline', methods: ['GET'])]
    public function animalTimeline(string $id): JsonResponse
    {
        $subject = $this->findAccessibleSubject($id);
        $project = $this->projectForSubject($subject);
        $events = [];

        if ($project instanceof Project) {
            $events[] = [
                'id' => 'assignment-' . $subject->getId(),
                'at' => ($project->getStartAt() ?? new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'kind' => 'assignment',
                'title' => 'Assigned to investigation',
                'detail' => $project->getName(),
                'actor' => 'Metadatapp',
            ];
        }

        foreach ($subject->getProcedures() as $procedure) {
            $events[] = [
                'id' => 'procedure-' . $procedure->getId(),
                'at' => ($procedure->getStartAt() ?? new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'kind' => $procedure instanceof Treatment ? 'treatment' : 'behavior',
                'title' => $procedure->getName(),
                'detail' => $procedure->getExperiment()->getName(),
                'actor' => $this->procedureUserName($procedure),
            ];
        }

        foreach ($subject->getWeightMeasurements() as $measurement) {
            $events[] = [
                'id' => 'weight-' . $measurement->getId(),
                'at' => $measurement->getMeasuredAt()->format(\DateTimeInterface::ATOM),
                'kind' => 'weight',
                'title' => 'Weight sample recorded',
                'value' => $this->weightInGrams($measurement->getWeight()) . ' g',
                'actor' => $measurement->getUser()->getName(),
            ];
        }

        usort($events, static fn (array $left, array $right): int => strcmp((string) $right['at'], (string) $left['at']));

        return $this->json(['data' => $events]);
    }

    private function findAccessibleSubject(string $id): Subject
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Animal not found.');
        }

        /** @var Subject|null $subject */
        $subject = $this->entityManager->getRepository(Subject::class)->find(Uuid::fromString($id));
        if (!$subject instanceof Subject) {
            throw $this->createNotFoundException('Animal not found.');
        }

        $this->denyUnlessSameAccount($subject);

        return $subject;
    }

    private function projectForSubject(Subject $subject): ?Project
    {
        foreach ($subject->getProcedures() as $procedure) {
            $project = $procedure->getExperiment()->getProject();
            if ($project instanceof Project) {
                return $project;
            }
        }

        return null;
    }

    private function findAccessibleProject(string $id): Project
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Investigation not found.');
        }

        /** @var Project|null $project */
        $project = $this->entityManager->getRepository(Project::class)->find(Uuid::fromString($id));
        if (!$project instanceof Project) {
            throw $this->createNotFoundException('Investigation not found.');
        }

        $this->denyUnlessSameAccount($project);

        return $project;
    }

    private function denyUnlessSameAccount(AccountAwareInterface $resource): void
    {
        $currentAccountId = $this->getCurrentUser()->getAccount()->getId();
        $resourceAccountId = $resource->getAccount()->getId();
        if (null === $resourceAccountId || null === $currentAccountId || !$resourceAccountId->equals($currentAccountId)) {
            throw $this->createAccessDeniedException('Resource not accessible.');
        }
    }

    /**
     * @return list<Subject>
     */
    private function subjectsForProject(Project $project): array
    {
        return $this->entityManager->getRepository(Subject::class)
            ->createQueryBuilder('s')
            ->distinct()
            ->join('s.procedures', 'procedure')
            ->join('procedure.experiment', 'experiment')
            ->andWhere('experiment.project = :project')
            ->andWhere('s.account = :account')
            ->setParameter('project', $project)
            ->setParameter('account', $project->getAccount())
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param list<Subject> $subjects
     *
     * @return list<array{at: string, average: float, min: float, max: float}>
     */
    private function groupWeightPoints(array $subjects): array
    {
        $points = [];
        foreach ($subjects as $subject) {
            foreach ($subject->getWeightMeasurements() as $measurement) {
                $date = $measurement->getMeasuredAt()->format('Y-m-d');
                $points[$date][] = $this->weightInGrams($measurement->getWeight());
            }
        }

        ksort($points);

        return array_map(static fn (string $date, array $weights): array => [
            'at' => $date . 'T00:00:00+00:00',
            'average' => round(array_sum($weights) / \count($weights), 2),
            'min' => round(min($weights), 2),
            'max' => round(max($weights), 2),
        ], array_keys($points), array_values($points));
    }

    /**
     * @param list<Subject> $subjects
     *
     * @return list<array<string, mixed>>
     */
    private function markerTrends(Project $project, array $subjects): array
    {
        $studyCount = $project->getExperiments()->count();
        $weightCount = array_sum(array_map(static fn (Subject $subject): int => $subject->getWeightMeasurements()->count(), $subjects));

        return [
            [
                'id' => 'animals',
                'label' => 'Linked animals',
                'value' => \count($subjects),
                'delta' => 0,
                'status' => 'flat',
                'history' => [max(0, \count($subjects) - 2), \count($subjects)],
            ],
            [
                'id' => 'studies',
                'label' => 'Linked studies',
                'value' => $studyCount,
                'delta' => 0,
                'status' => 'flat',
                'history' => [max(0, $studyCount - 1), $studyCount],
            ],
            [
                'id' => 'weights',
                'label' => 'Weight records',
                'value' => $weightCount,
                'delta' => 0,
                'status' => 'flat',
                'history' => [max(0, $weightCount - 3), $weightCount],
            ],
        ];
    }

    /**
     * @param list<Subject> $subjects
     *
     * @return list<array<string, string|null>>
     */
    private function recentActions(Project $project, array $subjects): array
    {
        return [
            [
                'id' => 'project-created-' . $project->getId(),
                'at' => ($project->getStartAt() ?? new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'title' => 'Investigation metadata registered',
                'detail' => $project->getName(),
                'actor' => $project->getUser()->getName(),
            ],
            [
                'id' => 'animals-linked-' . $project->getId(),
                'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'title' => 'Animal metadata linked',
                'detail' => \sprintf('%d subject(s) linked through procedures.', \count($subjects)),
                'actor' => 'Metadatapp',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function animalPayload(Subject $subject, ?Project $project): array
    {
        $latest = $this->latestWeightMeasurement($subject);
        $lastWeight = $latest instanceof WeightMeasurement
            ? $this->weightInGrams($latest->getWeight())
            : $this->weightInGrams($subject->getWeight() ?? 0.0);
        $lastWeighedAt = $latest instanceof WeightMeasurement
            ? $latest->getMeasuredAt()->format(\DateTimeInterface::ATOM)
            : $subject->getBirthAt()->format(\DateTimeInterface::ATOM);
        $cage = $subject->getCage();

        return [
            'id' => (string) $subject->getId(),
            'tagId' => $subject->getExternalId() ?? $subject->getSoftmouseExternalId() ?? $subject->getName(),
            'nickname' => $subject->getName(),
            'strain' => $subject->getStrain()->getName(),
            'sex' => 'female' === $subject->getSex()->value ? 'female' : 'male',
            'ageWeeks' => max(0, (int) floor($subject->getBirthAt()->diff(new \DateTimeImmutable())->days / 7)),
            'cage' => $cage?->getExternalId() ?? ($cage ? (string) $cage->getId() : 'Unassigned'),
            'room' => $cage?->getAnimalFacility()?->getName() ?? 'Unknown',
            'cohort' => $subject->getGenotype() ?? $subject->getStrain()->getName(),
            'status' => str_contains(strtolower($subject->getHealthStatus()->value), 'warning') ? 'at-risk' : 'active',
            'baselineWeight' => $lastWeight,
            'lastWeight' => $lastWeight,
            'lastWeighedAt' => $lastWeighedAt,
            'investigationId' => $project instanceof Project ? (string) $project->getId() : null,
            'assignedAt' => $project?->getStartAt()?->format(\DateTimeInterface::ATOM),
            'source' => 'Tick@Lab',
            'badgeStatus' => str_contains(strtolower($subject->getHealthStatus()->value), 'warning') ? 'warning' : 'ok',
            'weightHistory' => array_values(array_map(
                fn (WeightMeasurement $measurement): float => $this->weightInGrams($measurement->getWeight()),
                $subject->getWeightMeasurements()->toArray(),
            )),
            'quickNote' => $subject->getHealthStatus()->value,
            'isFavorite' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function weightPayload(Subject $subject, WeightMeasurement $measurement): array
    {
        return [
            'id' => (string) $measurement->getId(),
            'animalId' => (string) $subject->getId(),
            'grams' => $this->weightInGrams($measurement->getWeight()),
            'at' => $measurement->getMeasuredAt()->format(\DateTimeInterface::ATOM),
            'source' => 'scale',
        ];
    }

    private function procedureUserName(Procedure $procedure): string
    {
        $actor = $procedure->getActor();
        if ($actor instanceof User) {
            return $actor->getName() ?? 'Metadatapp';
        }

        return 'Metadatapp';
    }

    /**
     * @param array<string, mixed> $animal
     *
     * @return array<string, mixed>|null
     */
    private function atRiskAnimalPayload(array $animal): ?array
    {
        if ('at-risk' !== $animal['status']) {
            return null;
        }

        return [
            'id' => $animal['id'],
            'tagId' => $animal['tagId'],
            'nickname' => $animal['nickname'],
            'status' => 'monitor',
            'lastWeight' => $animal['lastWeight'],
            'lastWeighedAt' => $animal['lastWeighedAt'],
            'reason' => $animal['quickNote'] ?? 'Health status requires review.',
        ];
    }

    private function latestWeightMeasurement(Subject $subject): ?WeightMeasurement
    {
        $measurements = $subject->getWeightMeasurements()->toArray();
        usort($measurements, static fn (WeightMeasurement $a, WeightMeasurement $b): int => $b->getMeasuredAt() <=> $a->getMeasuredAt());

        return $measurements[0] ?? null;
    }

    private function weightInGrams(float $value): float
    {
        return round($value < 1.0 ? $value * 1000 : $value, 2);
    }

    /**
     * @param list<string> $tags
     *
     * @return array<string, mixed>
     */
    private function projectSummary(Project $project, array $tags = []): array
    {
        return [
            'id' => (string) $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription() ?? $project->getGoal() ?? '',
            'species' => 'Mouse',
            'startAt' => $project->getStartAt()?->format(\DateTimeInterface::ATOM),
            'endAt' => $project->getEndAt()?->format(\DateTimeInterface::ATOM),
            'assignedOperators' => [[
                'id' => (string) $project->getUser()->getId(),
                'name' => $project->getUser()->getName(),
                'role' => 'Owner',
            ]],
            'assignedAnimalIds' => array_map(static fn (Subject $subject): string => (string) $subject->getId(), $this->subjectsForProject($project)),
            'createdAt' => $project->getStartAt()?->format(\DateTimeInterface::ATOM) ?? (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'updatedAt' => $project->getEndAt()?->format(\DateTimeInterface::ATOM) ?? (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'tags' => $tags,
        ];
    }

    private function escapeCsv(string $value): string
    {
        if (str_contains($value, '"') || str_contains($value, ',') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
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
