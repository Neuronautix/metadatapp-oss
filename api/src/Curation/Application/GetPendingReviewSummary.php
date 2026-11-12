<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Entity\ImportSession;

final readonly class GetPendingReviewSummary
{
    public function __construct(
    ) {
    }

    public function execute(ImportSession $session): array
    {
        // Example implementation returning counts of 'undecided' proposals
        return [
            'session_id' => $session->getId(),
            'pending_schema_mappings' => 0,
            'pending_normalizations' => 0,
            'pending_ontology_terms' => 0,
            'reviewer_overrides' => 0,
        ];
    }
}
