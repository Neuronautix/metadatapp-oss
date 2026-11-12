<?php

declare(strict_types=1);

namespace App\Curation\UI;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

final class ResolutionConflictSummary
{
    public function __construct(
        #[Groups(['import_session.read'])]
        public Uuid $session_id,
        /** @var array{total: int, resolved: int, unresolved: int, ambiguous: int} */
        #[Groups(['import_session.read'])]
        public array $row_stats,
        /** @var array{existing_to_update: int, new_to_create: int} */
        #[Groups(['import_session.read'])]
        public array $subject_stats,
    ) {
    }
}
