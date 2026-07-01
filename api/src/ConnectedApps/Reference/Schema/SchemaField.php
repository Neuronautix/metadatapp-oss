<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Schema;

final readonly class SchemaField
{
    /**
     * @param list<string>         $ontologyTermIris
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $label,
        public ?string $description,
        public ?string $datatype,
        public ?string $unit,
        public array $ontologyTermIris,
        public string $sourceRef,
        public array $raw,
    ) {
    }
}
