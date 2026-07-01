<?php

declare(strict_types=1);

namespace App\Zefix\Alerts;

use App\Entity\Alert;
use App\Enum\AlertEntityType;

final readonly class ZefixAlertViewFactory
{
    public function create(Alert $alert): ZefixAlert
    {
        return new ZefixAlert(
            alertID: $alert->getId()?->toRfc4122() ?? '',
            type: $alert->getType()->value,
            severity: $alert->getSeverity()->value,
            status: $alert->getStatus()->value,
            affectedEntityType: $alert->getAffectedEntityType()->value,
            affectedEntityId: $this->affectedEntityId($alert),
            affectedLabel: $this->affectedLabel($alert),
            timestamp: $alert->getCreatedAt()->format(\DATE_ATOM),
            recommendedAction: $alert->getRecommendedAction(),
            acknowledgedAt: $alert->getAcknowledgedAt()?->format(\DATE_ATOM),
            resolvedAt: $alert->getResolvedAt()?->format(\DATE_ATOM),
        );
    }

    private function affectedEntityId(Alert $alert): string
    {
        return match ($alert->getAffectedEntityType()) {
            AlertEntityType::SYSTEM => $alert->getSystem()?->getCode() ?? '',
            AlertEntityType::ROOM => $alert->getRoom()?->getId()?->toRfc4122() ?? '',
            AlertEntityType::BATCH => $alert->getBatch()?->getId()?->toRfc4122() ?? '',
        };
    }

    private function affectedLabel(Alert $alert): string
    {
        return match ($alert->getAffectedEntityType()) {
            AlertEntityType::SYSTEM => \sprintf(
                'System %s · %s',
                $alert->getSystem()?->getCode() ?? 'Unknown system',
                $alert->getSystem()?->getRoom()?->getName() ?? 'Unknown room',
            ),
            AlertEntityType::ROOM => \sprintf(
                '%s · %s',
                $alert->getRoom()?->getName() ?? 'Unknown room',
                $alert->getRoom()?->getAccount()->getName() ?? 'Unknown facility',
            ),
            AlertEntityType::BATCH => \sprintf(
                'Batch %s · %s',
                $alert->getBatch()?->getId()?->toRfc4122() ?? 'Unknown batch',
                $alert->getBatch()?->getLine()?->getName() ?? 'Unknown line',
            ),
        };
    }
}
