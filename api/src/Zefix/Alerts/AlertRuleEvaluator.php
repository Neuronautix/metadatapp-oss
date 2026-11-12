<?php

declare(strict_types=1);

namespace App\Zefix\Alerts;

use App\Entity\Alert;
use App\Entity\EnvironmentalObservation;
use App\Entity\MortalityRecord;
use App\Entity\Room;
use App\Entity\System;
use App\Entity\ZebrafishBatch;
use App\Enum\AlertEntityType;
use App\Enum\AlertSeverity;
use App\Enum\AlertStatus;
use App\Enum\AlertType;
use App\Enum\EnvironmentalMetric;
use App\Repository\AlertRepository;
use App\Repository\MortalityRecordRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AlertRuleEvaluator
{
    private const TEMPERATURE_MIN = 26.0;
    private const TEMPERATURE_MAX = 29.0;
    private const TEMPERATURE_CRITICAL_MIN = 25.0;
    private const TEMPERATURE_CRITICAL_MAX = 30.0;
    private const PH_MIN = 6.8;
    private const PH_MAX = 7.6;
    private const PH_CRITICAL_MIN = 6.5;
    private const PH_CRITICAL_MAX = 7.9;
    private const MORTALITY_WARNING_RATIO = 0.18;
    private const MORTALITY_CRITICAL_RATIO = 0.25;

    public function __construct(
        private AlertRepository $alertRepository,
        private MortalityRecordRepository $mortalityRecordRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function evaluateEnvironmentalObservation(EnvironmentalObservation $observation): void
    {
        $system = $observation->getSystem();
        $room = $observation->getRoom();
        if (!$system instanceof System && !$room instanceof Room) {
            return;
        }

        match ($observation->getMetric()) {
            EnvironmentalMetric::TEMPERATURE => $this->evaluateTemperatureObservation($observation, $system, $room),
            EnvironmentalMetric::PH => $this->evaluatePhObservation($observation, $system, $room),
        };
    }

    private function evaluateTemperatureObservation(EnvironmentalObservation $observation, ?System $system, ?Room $room): void
    {
        if ($observation->getValue() < self::TEMPERATURE_MIN || $observation->getValue() > self::TEMPERATURE_MAX) {
            $severity = $observation->getValue() < self::TEMPERATURE_CRITICAL_MIN || $observation->getValue() > self::TEMPERATURE_CRITICAL_MAX
                ? AlertSeverity::CRITICAL
                : AlertSeverity::WARNING;

            $this->openAlert(
                type: AlertType::TEMPERATURE_OUTSIDE_RANGE,
                severity: $severity,
                recommendedAction: 'Check heater/chiller loop and verify calibration probe.',
                createdAt: $observation->getTimestamp(),
                system: $system,
                room: $room,
                batch: null,
            );
        }
    }

    private function evaluatePhObservation(EnvironmentalObservation $observation, ?System $system, ?Room $room): void
    {
        if ($observation->getValue() < self::PH_MIN || $observation->getValue() > self::PH_MAX) {
            $severity = $observation->getValue() < self::PH_CRITICAL_MIN || $observation->getValue() > self::PH_CRITICAL_MAX
                ? AlertSeverity::CRITICAL
                : AlertSeverity::WARNING;

            $this->openAlert(
                type: AlertType::PH_OUTSIDE_RANGE,
                severity: $severity,
                recommendedAction: 'Inspect buffering and dosing, then confirm the pH probe calibration.',
                createdAt: $observation->getTimestamp(),
                system: $system,
                room: $room,
                batch: null,
            );
        }
    }

    public function evaluateMortalityRecord(MortalityRecord $record): void
    {
        $batch = $record->getBatch();
        $initialPopulation = $batch->getMaleCount() + $batch->getFemaleCount() + $batch->getUnknownSexCount();
        if (0 === $initialPopulation) {
            return;
        }

        $mortalityCount = $this->mortalityRecordRepository->sumCountForBatch($batch);
        $ratio = $mortalityCount / $initialPopulation;
        if ($ratio < self::MORTALITY_WARNING_RATIO) {
            return;
        }

        $severity = $ratio >= self::MORTALITY_CRITICAL_RATIO ? AlertSeverity::CRITICAL : AlertSeverity::WARNING;

        $this->openAlert(
            type: AlertType::HIGH_MORTALITY_IN_BATCH,
            severity: $severity,
            recommendedAction: 'Initiate veterinary review and correlate with room and water parameters.',
            createdAt: $record->getDate(),
            system: null,
            room: null,
            batch: $batch,
        );
    }

    private function openAlert(
        AlertType $type,
        AlertSeverity $severity,
        string $recommendedAction,
        \DateTimeImmutable $createdAt,
        ?System $system,
        ?Room $room,
        ?ZebrafishBatch $batch,
    ): void {
        $account = $batch?->getAccount() ?? $system?->getAccount() ?? $room?->getAccount();
        if (null === $account) {
            return;
        }

        $existingAlert = $this->alertRepository->findUnresolvedForScope($account, $type, $system, $room, $batch);
        if ($existingAlert instanceof Alert) {
            if ($this->severityRank($severity) > $this->severityRank($existingAlert->getSeverity())) {
                $existingAlert->setSeverity($severity);
                $this->entityManager->flush();
            }

            return;
        }

        $alert = (new Alert())
            ->setType($type)
            ->setSeverity($severity)
            ->setStatus(AlertStatus::ACTIVE)
            ->setAffectedEntityType($this->affectedEntityType($system, $room, $batch))
            ->setRecommendedAction($recommendedAction)
            ->setCreatedAt($createdAt)
            ->setSystem($system)
            ->setRoom($room)
            ->setBatch($batch)
            ->setAccount($account)
        ;

        $this->entityManager->persist($alert);
        $this->entityManager->flush();
    }

    private function affectedEntityType(?System $system, ?Room $room, ?ZebrafishBatch $batch): AlertEntityType
    {
        if ($batch instanceof ZebrafishBatch) {
            return AlertEntityType::BATCH;
        }

        if ($system instanceof System) {
            return AlertEntityType::SYSTEM;
        }

        if ($room instanceof Room) {
            return AlertEntityType::ROOM;
        }

        throw new \LogicException('Unable to resolve alert scope.');
    }

    private function severityRank(AlertSeverity $severity): int
    {
        return match ($severity) {
            AlertSeverity::INFO => 1,
            AlertSeverity::WARNING => 2,
            AlertSeverity::CRITICAL => 3,
        };
    }
}
