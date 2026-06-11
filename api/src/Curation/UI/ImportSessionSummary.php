<?php

declare(strict_types=1);

namespace App\Curation\UI;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

final class ImportSessionSummary
{
    public function __construct(
        #[Groups(['import_session.read'])]
        public Uuid $id,
        #[Groups(['import_session.read'])]
        public string $status,
        #[Groups(['import_session.read'])]
        public int $unresolved_row_count,
        #[Groups(['import_session.read'])]
        public int $ambiguous_row_count,
        /** @var array<string> */
        #[Groups(['import_session.read'])]
        public array $next_allowed_actions,
        /** @var array<array{id: string, column_id: string, header: string, target_field: string, status: string}> */
        #[Groups(['import_session.read'])]
        public array $schema_proposals,
        /** @var array{profiled_at: ?string, schema_generated_at: ?string, patched_built_at: ?string, applied_at: ?string} */
        #[Groups(['import_session.read'])]
        public array $timestamps,
    ) {
    }
}
