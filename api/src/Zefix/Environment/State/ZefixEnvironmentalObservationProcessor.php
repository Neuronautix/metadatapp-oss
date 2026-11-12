<?php

declare(strict_types=1);

namespace App\Zefix\Environment\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnvironmentalObservation;
use App\Entity\Room;
use App\Entity\System;
use App\Entity\User;
use App\Enum\EnvironmentalMetric;
use App\Enum\EnvironmentalObservationSource;
use App\Zefix\Alerts\AlertRuleEvaluator;
use App\Zefix\Environment\ZefixEnvironmentalObservation;
use App\Zefix\Support\ZefixPermissionChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** @implements ProcessorInterface<ZefixEnvironmentalObservation, ZefixEnvironmentalObservation> */
final readonly class ZefixEnvironmentalObservationProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private AlertRuleEvaluator $alertRuleEvaluator,
        private ZefixPermissionChecker $permissionChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ZefixEnvironmentalObservation
    {
        foreach (['scopeType', 'scopeID', 'metric', 'value', 'timestamp', 'source'] as $field) {
            if (null === $data->{$field}) {
                throw new UnprocessableEntityHttpException(\sprintf('Environmental observation field "%s" is required.', $field));
            }
        }

        $scopeType = (string) $data->scopeType;
        $scopeID = (string) $data->scopeID;
        $metric = (string) $data->metric;
        $value = (float) $data->value;
        $timestamp = (string) $data->timestamp;
        $source = (string) $data->source;

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User is not authenticated.');
        }

        $this->permissionChecker->assertCanEdit($user);

        $managedUser = $this->entityManager->find(User::class, $user->getId());
        if (!$managedUser instanceof User) {
            throw new AccessDeniedHttpException('User could not be loaded from the current entity manager.');
        }

        $metricEnum = EnvironmentalMetric::tryFrom($metric);
        if (null === $metricEnum) {
            throw new UnprocessableEntityHttpException(\sprintf('Environmental observation metric "%s" is invalid.', $metric));
        }

        $sourceEnum = EnvironmentalObservationSource::tryFrom($source);
        if (null === $sourceEnum) {
            throw new UnprocessableEntityHttpException(\sprintf('Environmental observation source "%s" is invalid.', $source));
        }

        try {
            $timestampDateTime = new \DateTimeImmutable($timestamp);
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException(\sprintf('Environmental observation timestamp "%s" is invalid.', $timestamp));
        }

        $observation = new EnvironmentalObservation();
        $observation
            ->setMetric($metricEnum)
            ->setValue($value)
            ->setTimestamp($timestampDateTime)
            ->setSource($sourceEnum)
            ->setNotes($data->notes)
            ->setUser($managedUser)
            ->setAccount($user->getAccount())
        ;

        if ('system' === $scopeType) {
            $system = $this->entityManager->getRepository(System::class)->findOneBy([
                'code' => $scopeID,
                'account' => $user->getAccount(),
            ]);
            if (!$system instanceof System) {
                throw new NotFoundHttpException('Zefix system not found.');
            }

            $observation->setSystem($system);
        } elseif ('room' === $scopeType) {
            $room = $this->entityManager->getRepository(Room::class)->findOneBy([
                'id' => $scopeID,
                'account' => $user->getAccount(),
            ]);
            if (!$room instanceof Room) {
                throw new NotFoundHttpException('Zefix room not found.');
            }

            $observation->setRoom($room);
        } else {
            throw new UnprocessableEntityHttpException('Environmental observation scope must be "system" or "room".');
        }

        $this->entityManager->persist($observation);
        $this->entityManager->flush();
        $this->alertRuleEvaluator->evaluateEnvironmentalObservation($observation);

        return new ZefixEnvironmentalObservation(
            observationID: $observation->getId()?->toRfc4122() ?? '',
            scopeType: $scopeType,
            scopeID: 'system' === $scopeType
                ? $observation->getSystem()?->getCode() ?? ''
                : $observation->getRoom()?->getId()?->toRfc4122() ?? '',
            systemID: $observation->getSystem()?->getCode(),
            roomID: ($observation->getRoom() ?? $observation->getSystem()?->getRoom())?->getId()?->toRfc4122(),
            metric: $observation->getMetric()->value,
            value: $observation->getValue(),
            timestamp: $observation->getTimestamp()->format(\DATE_ATOM),
            source: $observation->getSource()->value,
            notes: $observation->getNotes(),
            recorder: $observation->getRecorder(),
        );
    }
}
