<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Experiment;
use App\Entity\Procedure;
use App\Entity\Subject;
use App\Entity\User;
use App\Entity\WeightMeasurement;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/calendar', name: 'api_calendar_')]
#[IsGranted(Roles::ROLE_USER)]
final class CalendarController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/events', name: 'events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        $start = $this->parseRequiredDate($request, 'start');
        $end = $this->parseRequiredDate($request, 'end');

        if ($end < $start) {
            return $this->json([
                'message' => 'Invalid calendar range.',
                'detail' => 'The end date must be greater than or equal to the start date.',
            ], 400);
        }

        $events = [
            ...$this->loadStudyEvents($start, $end),
            ...$this->loadProtocolEvents($start, $end),
            ...$this->loadSampleEvents($start, $end),
        ];

        usort(
            $events,
            static fn (array $left, array $right): int => strcmp((string) $left['start'], (string) $right['start'])
        );

        return $this->json(['data' => $events]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadStudyEvents(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $experiments = $this->entityManager->createQueryBuilder()
            ->select('experiment')
            ->from(Experiment::class, 'experiment')
            ->andWhere('experiment.account = :account')
            ->andWhere('experiment.startAt IS NOT NULL')
            ->andWhere('experiment.startAt <= :end')
            ->andWhere('(experiment.endAt IS NULL OR experiment.endAt >= :start)')
            ->setParameter('account', $this->getCurrentUser()->getAccount())
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('experiment.startAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return array_values(array_map(function (Experiment $experiment): array {
            $startAt = $this->requireEventStart($experiment->getStartAt());

            return [
                'id' => 'study-' . $experiment->getId()?->toRfc4122(),
                'title' => $experiment->getName(),
                'start' => $startAt->format(\DateTimeInterface::ATOM),
                'end' => $this->resolveEventEnd($startAt, $experiment->getEndAt())->format(\DateTimeInterface::ATOM),
                'status' => $this->resolveTimedStatus($startAt, $experiment->getEndAt()),
                'kind' => 'study',
                'resource' => [
                    'type' => 'study',
                    'id' => $experiment->getId()?->toRfc4122(),
                    'label' => $experiment->getName(),
                ],
            ];
        }, $experiments));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadProtocolEvents(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $procedures = $this->entityManager->createQueryBuilder()
            ->select('procedure')
            ->from(Procedure::class, 'procedure')
            ->andWhere('procedure.account = :account')
            ->andWhere('procedure.startAt IS NOT NULL')
            ->andWhere('procedure.startAt <= :end')
            ->andWhere('(procedure.endAt IS NULL OR procedure.endAt >= :start)')
            ->setParameter('account', $this->getCurrentUser()->getAccount())
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('procedure.startAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return array_values(array_map(function (Procedure $procedure): array {
            $startAt = $this->requireEventStart($procedure->getStartAt());

            return [
                'id' => 'protocol-' . $procedure->getId()?->toRfc4122(),
                'title' => $procedure->getName(),
                'start' => $startAt->format(\DateTimeInterface::ATOM),
                'end' => $this->resolveEventEnd($startAt, $procedure->getEndAt())->format(\DateTimeInterface::ATOM),
                'status' => $this->resolveProcedureStatus($procedure->getState()),
                'kind' => 'protocol',
                'resource' => [
                    'type' => 'protocol',
                    'id' => $procedure->getId()?->toRfc4122(),
                    'label' => $procedure->getName(),
                ],
                'subjects' => array_values(array_map(static fn (Subject $subject): array => [
                    'id' => $subject->getId()?->toRfc4122(),
                    'label' => $subject->getName(),
                ], $procedure->getSubjects()->toArray())),
            ];
        }, $procedures));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadSampleEvents(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $measurements = $this->entityManager->createQueryBuilder()
            ->select('measurement', 'subject')
            ->from(WeightMeasurement::class, 'measurement')
            ->join('measurement.subject', 'subject')
            ->andWhere('measurement.account = :account')
            ->andWhere('measurement.measuredAt >= :start')
            ->andWhere('measurement.measuredAt <= :end')
            ->setParameter('account', $this->getCurrentUser()->getAccount())
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('measurement.measuredAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return array_values(array_map(static fn (WeightMeasurement $measurement): array => [
            'id' => 'sample-' . $measurement->getId()?->toRfc4122(),
            'title' => 'Weight sample - ' . $measurement->getSubject()->getName(),
            'start' => $measurement->getMeasuredAt()->format(\DateTimeInterface::ATOM),
            'end' => \DateTimeImmutable::createFromInterface($measurement->getMeasuredAt())->modify('+15 minutes')->format(\DateTimeInterface::ATOM),
            'status' => 'completed',
            'kind' => 'sample',
            'resource' => [
                'type' => 'sample',
                'id' => $measurement->getId()?->toRfc4122(),
                'label' => 'Weight sample - ' . $measurement->getSubject()->getName(),
            ],
            'subject' => [
                'id' => $measurement->getSubject()->getId()?->toRfc4122(),
                'label' => $measurement->getSubject()->getName(),
            ],
        ], $measurements));
    }

    private function parseRequiredDate(Request $request, string $key): \DateTimeImmutable
    {
        $value = trim((string) $request->query->get($key, ''));
        if ('' === $value) {
            throw new BadRequestHttpException(\sprintf('Missing required calendar parameter "%s".', $key));
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            throw new BadRequestHttpException(\sprintf('Invalid calendar parameter "%s".', $key));
        }
    }

    private function requireEventStart(?\DateTimeInterface $startAt): \DateTimeImmutable
    {
        if (!$startAt instanceof \DateTimeInterface) {
            throw new \LogicException('Calendar events must have a start date.');
        }

        return \DateTimeImmutable::createFromInterface($startAt);
    }

    private function resolveEventEnd(?\DateTimeInterface $startAt, ?\DateTimeInterface $endAt): \DateTimeImmutable
    {
        if ($endAt instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($endAt);
        }

        if ($startAt instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($startAt)->modify('+1 hour');
        }

        return new \DateTimeImmutable();
    }

    private function resolveTimedStatus(?\DateTimeInterface $startAt, ?\DateTimeInterface $endAt): string
    {
        $now = new \DateTimeImmutable();

        if ($endAt instanceof \DateTimeInterface && \DateTimeImmutable::createFromInterface($endAt) < $now) {
            return 'completed';
        }

        if ($startAt instanceof \DateTimeInterface && \DateTimeImmutable::createFromInterface($startAt) > $now) {
            return 'scheduled';
        }

        return 'in-progress';
    }

    private function resolveProcedureStatus(string $state): string
    {
        return match ($state) {
            'completed' => 'completed',
            'in_progress' => 'in-progress',
            'cancelled' => 'blocked',
            default => 'scheduled',
        };
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
