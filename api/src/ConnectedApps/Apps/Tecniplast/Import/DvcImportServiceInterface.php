<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Tecniplast\Import;

use App\Entity\ConnectedApp;
use App\Entity\Experiment;

interface DvcImportServiceInterface
{
    public function importFromZip(string $zipFilePath, Experiment $experiment, ConnectedApp $connectedApp, int|string $taskId): DvcImportResult;

    public function importFromCsv(string $csvContent, Experiment $experiment, ConnectedApp $connectedApp, int|string $taskId): DvcImportResult;
}
