<?php

declare(strict_types=1);

namespace App\Application\Service\Impc;

use Psr\Log\LoggerInterface;

class SolrImporter
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function import(int $limit = 100): void
    {
        $this->logger->info('Starting IMPC Solr import');

        // Placeholder for real logic from Step 316
        // Fetch from Solr and persist
    }
}
