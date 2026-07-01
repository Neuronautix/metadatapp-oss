<?php

declare(strict_types=1);

namespace App\Crosswalk\Import;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Parses an `.eln` archive (a ZIP that contains a `ro-crate-metadata.json`) or a
 * raw RO-Crate metadata JSON into a {@see ParsedRoCrate}. The parser never
 * discards fields: every walked node becomes an {@see ExtractedFieldData}.
 */
final class ElnRoCrateImporter
{
    /** Node @vars that carry crosswalk-relevant field semantics. */
    private const FIELD_TYPES = [
        'Dataset',
        'CreativeWork',
        'File',
        'PropertyValue',
        'DefinedTerm',
        'DefinedTermSet',
        'FormalParameter',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Parse an uploaded or on-disk `.eln` archive.
     */
    public function importFromFile(\SplFileInfo|UploadedFile $file): ParsedRoCrate
    {
        $path = $file instanceof UploadedFile ? $file->getPathname() : $file->getPathname();
        $bytes = @file_get_contents($path);
        if (false === $bytes) {
            throw new \RuntimeException('Unable to read the uploaded ELN file.');
        }

        return $this->parseBytes($bytes);
    }

    /**
     * Download an `.eln` from a URL (e.g. an ELN.community export) and parse it.
     */
    public function importFromUrl(string $url): ParsedRoCrate
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 30,
            ]);
            $bytes = $response->getContent();
        } catch (HttpExceptionInterface $e) {
            throw new \RuntimeException(\sprintf('Unable to download ELN from "%s": %s', $url, $e->getMessage()), 0, $e);
        }

        $parsed = $this->parseBytes($bytes);

        return new ParsedRoCrate(
            metadataJson: $parsed->metadataJson,
            rootDataset: $parsed->rootDataset,
            fileHash: $parsed->fileHash,
            title: $parsed->title,
            description: $parsed->description,
            version: $parsed->version,
            externalUrl: $url,
            extractedFields: $parsed->extractedFields,
        );
    }

    /**
     * Parse raw bytes that are either a ZIP `.eln` archive or a bare
     * `ro-crate-metadata.json` document. Useful for fixtures/tests.
     */
    public function parseBytes(string $bytes): ParsedRoCrate
    {
        $fileHash = hash('sha256', $bytes);
        $metadataJson = $this->extractMetadataJson($bytes);

        return $this->buildParsed($metadataJson, $fileHash);
    }

    /**
     * Parse an already-decoded RO-Crate metadata graph.
     *
     * @param array<string, mixed> $metadataJson
     */
    public function parseGraph(array $metadataJson, ?string $fileHash = null): ParsedRoCrate
    {
        $fileHash ??= hash('sha256', json_encode($metadataJson, \JSON_THROW_ON_ERROR));

        return $this->buildParsed($metadataJson, $fileHash);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractMetadataJson(string $bytes): array
    {
        $trimmed = ltrim($bytes);
        if (str_starts_with($trimmed, '{')) {
            // Bare ro-crate-metadata.json document.
            return $this->decodeJson($trimmed);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'eln_');
        if (false === $tmp) {
            throw new \RuntimeException('Unable to allocate a temporary file for the ELN archive.');
        }

        try {
            file_put_contents($tmp, $bytes);
            $zip = new \ZipArchive();
            if (true !== $zip->open($tmp)) {
                throw new \RuntimeException('The ELN file is not a valid ZIP archive.');
            }

            try {
                $member = $this->locateMetadataMember($zip);
                if (null === $member) {
                    throw new \RuntimeException('No ro-crate-metadata.json found inside the ELN archive.');
                }

                $contents = $zip->getFromName($member);
                if (false === $contents) {
                    throw new \RuntimeException('Unable to read ro-crate-metadata.json from the ELN archive.');
                }

                return $this->decodeJson($contents);
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($tmp);
        }
    }

    private function locateMetadataMember(\ZipArchive $zip): ?string
    {
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if (false !== $name && str_ends_with($name, 'ro-crate-metadata.json')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            throw new \RuntimeException('ro-crate-metadata.json did not decode to an object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $metadataJson
     */
    private function buildParsed(array $metadataJson, string $fileHash): ParsedRoCrate
    {
        $graph = $this->graphOf($metadataJson);
        $byId = $this->indexById($graph);
        $root = $this->findRootDataset($byId, $graph);

        $title = $this->scalar($root['name'] ?? null);
        $description = $this->scalar($root['description'] ?? null);
        $version = $this->scalar($root['version'] ?? null);
        $externalUrl = $this->scalar($root['url'] ?? ($root['@id'] ?? null));
        if (null !== $externalUrl && !str_starts_with($externalUrl, 'http')) {
            $externalUrl = null;
        }

        $fields = $this->extractFields($graph, $byId, $root);

        return new ParsedRoCrate(
            metadataJson: $metadataJson,
            rootDataset: $root,
            fileHash: $fileHash,
            title: $title,
            description: $description,
            version: $version,
            externalUrl: $externalUrl,
            extractedFields: $fields,
        );
    }

    /**
     * @param array<string, mixed> $metadataJson
     *
     * @return list<array<string, mixed>>
     */
    private function graphOf(array $metadataJson): array
    {
        $graph = $metadataJson['@graph'] ?? [];
        if (!\is_array($graph)) {
            return [];
        }

        $nodes = [];
        foreach ($graph as $node) {
            if (\is_array($node)) {
                /** @var array<string, mixed> $node */
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param list<array<string, mixed>> $graph
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexById(array $graph): array
    {
        $byId = [];
        foreach ($graph as $node) {
            $id = $this->scalar($node['@id'] ?? null);
            if (null !== $id) {
                $byId[$id] = $node;
            }
        }

        return $byId;
    }

    /**
     * @param array<string, array<string, mixed>> $byId
     * @param list<array<string, mixed>>          $graph
     *
     * @return array<string, mixed>
     */
    private function findRootDataset(array $byId, array $graph): array
    {
        $descriptor = $byId['ro-crate-metadata.json'] ?? $byId['./ro-crate-metadata.json'] ?? null;
        if (null !== $descriptor) {
            $aboutId = $this->refId($descriptor['about'] ?? null);
            if (null !== $aboutId && isset($byId[$aboutId])) {
                return $byId[$aboutId];
            }
        }

        if (isset($byId['./'])) {
            return $byId['./'];
        }

        foreach ($graph as $node) {
            if (\in_array('Dataset', $this->typesOf($node), true)) {
                return $node;
            }
        }

        return $graph[0] ?? [];
    }

    /**
     * @param list<array<string, mixed>>          $graph
     * @param array<string, array<string, mixed>> $byId
     * @param array<string, mixed>                $root
     *
     * @return list<ExtractedFieldData>
     */
    private function extractFields(array $graph, array $byId, array $root): array
    {
        $fields = [];
        $order = 0;
        $rootId = $this->scalar($root['@id'] ?? null);

        foreach ($graph as $node) {
            $id = $this->scalar($node['@id'] ?? null);
            if (null !== $id && str_ends_with($id, 'ro-crate-metadata.json')) {
                continue;
            }

            $types = $this->typesOf($node);
            if ([] === array_intersect($types, self::FIELD_TYPES)) {
                continue;
            }

            $jsonldPath = $this->pathFor($node, $rootId, $id);
            $fields[] = $this->fieldFromNode($node, $byId, $jsonldPath, $order++);
        }

        // Also surface the root dataset's variableMeasured / instrument /
        // measurementTechnique references that are themselves inline values.
        foreach (['variableMeasured', 'instrument', 'measurementTechnique', 'license', 'creator', 'author', 'contributor', 'conformsTo'] as $prop) {
            foreach ($this->refIds($root[$prop] ?? null) as $refId) {
                if (isset($byId[$refId])) {
                    continue; // already emitted as a graph node above
                }
            }
        }

        return $fields;
    }

    /**
     * @param array<string, mixed>                $node
     * @param array<string, array<string, mixed>> $byId
     */
    private function fieldFromNode(array $node, array $byId, string $jsonldPath, int $order): ExtractedFieldData
    {
        $label = $this->scalar($node['name'] ?? null)
            ?? $this->scalar($node['propertyID'] ?? null)
            ?? $this->scalar($node['@id'] ?? null)
            ?? 'unnamed-field';

        $propertyId = $this->scalar($node['propertyID'] ?? null);

        $value = $node['value'] ?? $node['exampleValue'] ?? $node['defaultValue'] ?? null;
        $exampleValue = $this->scalar($value);

        $unit = $this->scalar($node['unitCode'] ?? $node['unitText'] ?? null);

        $datatype = $this->guessDatatype($node, $exampleValue);

        $ontologyTerms = $this->resolveOntologyTerms($node, $byId);

        return new ExtractedFieldData(
            label: $label,
            propertyId: $propertyId,
            datatype: $datatype,
            exampleValue: $exampleValue,
            unit: $unit,
            jsonldId: $this->scalar($node['@id'] ?? null),
            jsonldPath: $jsonldPath,
            ontologyTerms: $ontologyTerms,
            rawNode: $node,
            orderIndex: $order,
        );
    }

    /**
     * @param array<string, mixed>                $node
     * @param array<string, array<string, mixed>> $byId
     *
     * @return list<array<string, mixed>>
     */
    private function resolveOntologyTerms(array $node, array $byId): array
    {
        $terms = [];

        // DefinedTerm itself: emit its IRI.
        if (\in_array('DefinedTerm', $this->typesOf($node), true)) {
            $iri = $this->scalar($node['termCode'] ?? $node['@id'] ?? null);
            if (null !== $iri) {
                $terms[] = $this->ontologyTerm($iri, $this->scalar($node['name'] ?? null), $this->scalar($node['inDefinedTermSet'] ?? null));
            }
        }

        foreach (['valueReference', 'subjectOf', 'sameAs', 'additionalType'] as $prop) {
            foreach ($this->refIds($node[$prop] ?? null) as $refId) {
                $target = $byId[$refId] ?? null;
                if (null !== $target && \in_array('DefinedTerm', $this->typesOf($target), true)) {
                    $iri = $this->scalar($target['termCode'] ?? $target['@id'] ?? null) ?? $refId;
                    $terms[] = $this->ontologyTerm($iri, $this->scalar($target['name'] ?? null), $this->scalar($target['inDefinedTermSet'] ?? null));
                } elseif (str_starts_with($refId, 'http')) {
                    $terms[] = $this->ontologyTerm($refId, null, null);
                }
            }
        }

        return $terms;
    }

    /**
     * @return array{iri: string, label: ?string, source: ?string}
     */
    private function ontologyTerm(string $iri, ?string $label, ?string $source): array
    {
        return ['iri' => $iri, 'label' => $label, 'source' => $source];
    }

    /**
     * @param array<string, mixed> $node
     */
    private function guessDatatype(array $node, ?string $exampleValue): ?string
    {
        $declared = $this->scalar($node['additionalType'] ?? $node['valuePattern'] ?? null);
        if (null !== $declared) {
            $normalized = strtolower($declared);
            foreach (['number', 'decimal', 'integer', 'string', 'boolean', 'date'] as $candidate) {
                if (str_contains($normalized, $candidate)) {
                    return $candidate;
                }
            }
        }

        if (null === $exampleValue || '' === $exampleValue) {
            return null;
        }

        if (\in_array(strtolower($exampleValue), ['true', 'false'], true)) {
            return 'boolean';
        }

        if (1 === preg_match('/^-?\d+$/', $exampleValue)) {
            return 'integer';
        }

        if (is_numeric($exampleValue)) {
            return 'decimal';
        }

        if (1 === preg_match('/^\d{4}-\d{2}-\d{2}/', $exampleValue)) {
            return 'date';
        }

        return 'string';
    }

    /**
     * @param array<string, mixed> $node
     */
    private function pathFor(array $node, ?string $rootId, ?string $id): string
    {
        $types = $this->typesOf($node);
        $primaryType = $types[0] ?? 'Thing';
        $name = $this->scalar($node['name'] ?? null) ?? $id ?? $primaryType;

        if (null !== $rootId) {
            return \sprintf('%s/%s[%s]', $rootId, lcfirst($primaryType), $name);
        }

        return \sprintf('%s[%s]', lcfirst($primaryType), $name);
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return list<string>
     */
    private function typesOf(array $node): array
    {
        $type = $node['@type'] ?? [];
        if (\is_string($type)) {
            return [$type];
        }

        $out = [];
        if (\is_array($type)) {
            foreach ($type as $t) {
                if (\is_string($t)) {
                    $out[] = $t;
                }
            }
        }

        return $out;
    }

    private function refId(mixed $ref): ?string
    {
        if (\is_array($ref) && isset($ref['@id']) && \is_string($ref['@id'])) {
            return $ref['@id'];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function refIds(mixed $ref): array
    {
        if (null === $ref) {
            return [];
        }

        if ($single = $this->refId($ref)) {
            return [$single];
        }

        $out = [];
        if (\is_array($ref)) {
            foreach ($ref as $item) {
                if ($id = $this->refId($item)) {
                    $out[] = $id;
                } elseif (\is_string($item)) {
                    $out[] = $item;
                }
            }
        }

        return $out;
    }

    private function scalar(mixed $value): ?string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_array($value)) {
            // {"@value": ...} or a single-element list of scalars.
            if (isset($value['@value'])) {
                return $this->scalar($value['@value']);
            }
            if (1 === \count($value) && isset($value[0])) {
                return $this->scalar($value[0]);
            }
        }

        return null;
    }
}
