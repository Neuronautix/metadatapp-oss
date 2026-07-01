<?php

declare(strict_types=1);

namespace App\Guideline\Schema;

/**
 * One unified Template (§2.1). Every standard — ARRIVE 2.0, PREPARE, EQIPD,
 * MNMS — is expressed as data of this single shape, with two kinds of fields:
 * {@see RequiredColumn} (CSV-level) and {@see RequiredMetadata} (dataset-level).
 *
 * `conformsTo` drives INHERITANCE (§3): a template inherits the whole field set
 * of each parent (child wins on id collision). A resolved template carries the
 * fully merged, flat field lists.
 */
final readonly class GuidelineTemplate
{
    /**
     * @param list<string>                                   $conformsTo       parent template ids — drives inheritance
     * @param array{iri?: string, prefix?: string}           $ontology         linked-data namespace
     * @param list<RequiredColumn>                           $requiredColumns
     * @param list<RequiredColumn>                           $optionalColumns
     * @param list<RequiredMetadata>                         $requiredMetadata
     * @param array{dimension: string, max_points: int}|null $fairBonus
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $description = '',
        public ?string $identifier = null,
        public array $conformsTo = [],
        public array $ontology = [],
        public array $requiredColumns = [],
        public array $optionalColumns = [],
        public array $requiredMetadata = [],
        public ?array $fairBonus = null,
        public string $source = 'builtin',
    ) {
    }

    /**
     * @param list<RequiredColumn>   $requiredColumns
     * @param list<RequiredMetadata> $requiredMetadata
     */
    public function withMergedFields(array $requiredColumns, array $requiredMetadata): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            version: $this->version,
            description: $this->description,
            identifier: $this->identifier,
            conformsTo: $this->conformsTo,
            ontology: $this->ontology,
            requiredColumns: $requiredColumns,
            optionalColumns: $this->optionalColumns,
            requiredMetadata: $requiredMetadata,
            fairBonus: $this->fairBonus,
            source: $this->source,
        );
    }
}
