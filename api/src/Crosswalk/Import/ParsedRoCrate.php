<?php

declare(strict_types=1);

namespace App\Crosswalk\Import;

/**
 * The result of parsing an `.eln` / RO-Crate: the full metadata graph, the root
 * dataset node, a content hash, top-level descriptors, and the list of extracted
 * fields. The original metadata is preserved verbatim in {@see $metadataJson}.
 */
final readonly class ParsedRoCrate
{
    /**
     * @param array<string, mixed>     $metadataJson    the full ro-crate-metadata.json
     * @param array<string, mixed>     $rootDataset     the root Dataset node
     * @param list<ExtractedFieldData> $extractedFields
     */
    public function __construct(
        public array $metadataJson,
        public array $rootDataset,
        public string $fileHash,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $version = null,
        public ?string $externalUrl = null,
        public array $extractedFields = [],
    ) {
    }
}
