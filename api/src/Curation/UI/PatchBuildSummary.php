<?php

declare(strict_types=1);

namespace App\Curation\UI;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

final class PatchBuildSummary
{
    public function __construct(
        #[Groups(['import_session.read'])]
        public Uuid $session_id,
        #[Groups(['import_session.read'])]
        public int $total_patches,
        #[Groups(['import_session.read'])]
        public int $ready_to_apply,
        #[Groups(['import_session.read'])]
        public int $subject_changes_count,
        /** @var array<array{subject_id: string, patch_count: int, fields: array}> */
        #[Groups(['import_session.read'])]
        public array $subject_deltas,
    ) {
    }
}
