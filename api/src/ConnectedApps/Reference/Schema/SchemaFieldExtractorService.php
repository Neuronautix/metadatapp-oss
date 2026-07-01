<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Schema;

use App\ConnectedApps\Reference\ReferenceResult;

final readonly class SchemaFieldExtractorService
{
    /**
     * @param list<ReferenceResult> $items
     *
     * @return list<array{sourceRef: string, sourceName: string, type: string, fields: list<array<string, mixed>>}>
     */
    public function extractAll(array $items): array
    {
        $schemas = [];
        foreach ($items as $item) {
            $fields = $this->extract($item);
            if ([] !== $fields) {
                $schemas[] = [
                    'sourceRef' => $item->id,
                    'sourceName' => $item->sourceName,
                    'type' => $item->type,
                    'fields' => array_map(static fn (SchemaField $f): array => [
                        'label' => $f->label,
                        'description' => $f->description,
                        'datatype' => $f->datatype,
                        'unit' => $f->unit,
                        'ontologyTermIris' => $f->ontologyTermIris,
                        'sourceRef' => $f->sourceRef,
                    ], $fields),
                ];
            }
        }

        return $schemas;
    }

    /**
     * @return list<SchemaField>
     */
    public function extract(ReferenceResult $item): array
    {
        return match ($item->source) {
            'cedar' => $this->extractCedar($item),
            'elabftw' => $this->extractElabftw($item),
            'guidelines_hub' => $this->extractGuideline($item),
            'mnms' => $this->extractMnms($item),
            'osf' => $this->extractOsf($item),
            'nih_cde' => $this->extractNihCdeForm($item),
            default => [],
        };
    }

    /** @return list<SchemaField> */
    private function extractCedar(ReferenceResult $item): array
    {
        $raw = $item->raw;
        $properties = $raw['properties'] ?? [];
        $skippedKeys = ['@context', '@id', '@type', 'schema:name', 'schema:description', 'schema:identifier', '_ui', '_valueConstraints', 'required', '_annotations', 'oslc:modifiedBy', 'pav:createdBy', 'pav:createdOn', 'pav:lastUpdatedOn', 'bibo:status', 'pav:version', 'schema:isBasedOn'];
        $fields = [];

        foreach ($properties as $key => $node) {
            if (\in_array($key, $skippedKeys, true) || !\is_array($node)) {
                continue;
            }
            $label = (string) ($node['schema:name'] ?? $key);
            $description = isset($node['schema:description']) && '' !== $node['schema:description'] ? (string) $node['schema:description'] : null;
            $datatype = isset($node['_valueConstraints']['defaultValue']) ? (string) $node['_valueConstraints']['defaultValue'] : null;
            $ontologies = $node['_valueConstraints']['ontologies'] ?? [];
            $iris = [];
            if (\is_array($ontologies)) {
                foreach ($ontologies as $ont) {
                    if (isset($ont['uri'])) {
                        $iris[] = (string) $ont['uri'];
                    }
                }
            }
            $fields[] = new SchemaField(
                label: $label,
                description: $description,
                datatype: $datatype,
                unit: null,
                ontologyTermIris: $iris,
                sourceRef: $item->id . '/properties/' . $key,
                raw: $node,
            );
        }

        return $fields;
    }

    /** @return list<SchemaField> */
    private function extractElabftw(ReferenceResult $item): array
    {
        $raw = $item->raw;
        $metadata = $raw['metadata'] ?? null;
        if (\is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        $extraFields = (array) (($metadata['extra_fields'] ?? []) ?: []);
        $fields = [];

        foreach ($extraFields as $key => $fieldDef) {
            if (!\is_array($fieldDef)) {
                continue;
            }
            $label = (string) ($fieldDef['name'] ?? $key);
            $description = isset($fieldDef['description']) && '' !== $fieldDef['description'] ? (string) $fieldDef['description'] : null;
            $datatype = isset($fieldDef['type']) ? (string) $fieldDef['type'] : null;
            $unit = isset($fieldDef['units']) && '' !== $fieldDef['units'] ? (string) $fieldDef['units'] : null;
            $fields[] = new SchemaField(
                label: $label,
                description: $description,
                datatype: $datatype,
                unit: $unit,
                ontologyTermIris: [],
                sourceRef: $item->id . '/extra_fields/' . $key,
                raw: $fieldDef,
            );
        }

        return $fields;
    }

    /** @return list<SchemaField> */
    private function extractGuideline(ReferenceResult $item): array
    {
        // Each guideline result IS a single checklist item — treat it as one field
        return [
            new SchemaField(
                label: $item->title,
                description: $item->description,
                datatype: 'text',
                unit: null,
                ontologyTermIris: [],
                sourceRef: $item->id,
                raw: $item->raw,
            ),
        ];
    }

    /** @return list<SchemaField> */
    private function extractMnms(ReferenceResult $item): array
    {
        $raw = $item->raw;

        return [
            new SchemaField(
                label: $item->title,
                description: $item->description,
                datatype: isset($raw['type']) ? (string) $raw['type'] : null,
                unit: isset($raw['unit']) ? (string) $raw['unit'] : null,
                ontologyTermIris: [],
                sourceRef: $item->id,
                raw: $raw,
            ),
        ];
    }

    /** @return list<SchemaField> */
    private function extractOsf(ReferenceResult $item): array
    {
        $raw = $item->raw;
        $pages = $raw['schema']['pages'] ?? $raw['attributes']['schema']['pages'] ?? [];
        $fields = [];

        foreach ((array) $pages as $page) {
            foreach ((array) ($page['questions'] ?? []) as $q) {
                $label = (string) ($q['title'] ?? $q['nav'] ?? '');
                if ('' === $label) {
                    continue;
                }
                $fields[] = new SchemaField(
                    label: $label,
                    description: isset($q['description']) && '' !== $q['description'] ? (string) $q['description'] : null,
                    datatype: isset($q['type']) ? (string) $q['type'] : null,
                    unit: null,
                    ontologyTermIris: [],
                    sourceRef: $item->id . '/questions/' . rawurlencode($label),
                    raw: $q,
                );
            }
        }

        return $fields;
    }

    /** @return list<SchemaField> */
    private function extractNihCdeForm(ReferenceResult $item): array
    {
        if ('template' !== $item->type) {
            return [];
        }
        $formElements = $item->raw['formElements'] ?? [];
        $fields = [];

        foreach ((array) $formElements as $el) {
            $designation = $el['question']['cde']['designations'][0]['designation'] ?? $el['label'] ?? null;
            if (null === $designation || '' === $designation) {
                continue;
            }
            $fields[] = new SchemaField(
                label: (string) $designation,
                description: null,
                datatype: null,
                unit: null,
                ontologyTermIris: [],
                sourceRef: $item->id . '/elements/' . rawurlencode((string) $designation),
                raw: $el,
            );
        }

        return $fields;
    }
}
