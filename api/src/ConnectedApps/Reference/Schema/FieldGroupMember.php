<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Schema;

final readonly class FieldGroupMember
{
    public function __construct(
        public string $sourceRef,
        public string $mappingType,
        public string $label,
        public string $evidence,
    ) {
    }
}
