<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Schema;

final readonly class SchemaComparisonResult
{
    /**
     * @param list<FieldGroup>     $fieldGroups
     * @param list<string>         $warnings
     * @param array<string, mixed> $usage
     */
    public function __construct(
        public array $fieldGroups,
        public bool $aiAvailable,
        public array $warnings = [],
        public array $usage = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fieldGroups' => array_map(static fn (FieldGroup $g): array => $g->toArray(), $this->fieldGroups),
            'aiAvailable' => $this->aiAvailable,
            'warnings' => $this->warnings,
            'usage' => $this->usage,
        ];
    }
}
