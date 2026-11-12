<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Tecniplast\Import;

final class DvcImportResult
{
    public function __construct(
        public int $processedRows = 0,
        public int $importedRows = 0,
        public int $skippedRows = 0,
    ) {
    }
}
