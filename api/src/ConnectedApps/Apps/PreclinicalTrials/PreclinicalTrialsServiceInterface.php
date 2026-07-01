<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\PreclinicalTrials;

use App\Entity\ConnectedApp;
use Symfony\Component\Uid\Uuid;

interface PreclinicalTrialsServiceInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listProtocolSummaries(ConnectedApp $connectedApp): array;

    /**
     * @return array<string, mixed>
     */
    public function importSelectedProtocol(ConnectedApp $connectedApp, string $protocolId, ?Uuid $targetInvestigationId = null): array;

    /**
     * @return array<string, mixed>
     */
    public function linkSelectedProtocolToStudy(ConnectedApp $connectedApp, string $protocolId, Uuid $targetStudyId): array;
}
