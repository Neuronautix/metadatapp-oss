<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Schema;

final readonly class FieldGroup
{
    /**
     * @param list<FieldGroupMember>     $members
     * @param list<array<string, mixed>> $conflicts
     */
    public function __construct(
        public string $groupId,
        public string $canonicalLabel,
        public ?string $canonicalDatatype,
        public ?string $canonicalUnit,
        public float $confidence,
        public array $members,
        public array $conflicts,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'groupId' => $this->groupId,
            'canonicalLabel' => $this->canonicalLabel,
            'canonicalDatatype' => $this->canonicalDatatype,
            'canonicalUnit' => $this->canonicalUnit,
            'confidence' => $this->confidence,
            'members' => array_map(static fn (FieldGroupMember $m): array => [
                'sourceRef' => $m->sourceRef,
                'mappingType' => $m->mappingType,
                'label' => $m->label,
                'evidence' => $m->evidence,
            ], $this->members),
            'conflicts' => $this->conflicts,
        ];
    }
}
