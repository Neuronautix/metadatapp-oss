<?php

declare(strict_types=1);

namespace App\Crosswalk\Cde;

/**
 * Accepts a JSON array of CDE definitions for manual import. Search/get over a
 * registered payload is supported so the same code path as remote providers can
 * normalize and persist custom CDEs.
 */
final class CustomJsonCdeProvider implements CdeProviderInterface
{
    /** @var list<CdeCandidate> */
    private array $cdes = [];

    public function key(): string
    {
        return 'custom_json';
    }

    /**
     * Parse a raw JSON array (or already-decoded array) of CDE definitions into
     * normalized candidates. Returns the candidates and also caches them so
     * subsequent searchCdes()/getCde() calls can resolve them.
     *
     * @param array<int|string, mixed>|string $payload
     *
     * @return list<CdeCandidate>
     */
    public function parse(array|string $payload): array
    {
        if (\is_string($payload)) {
            $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
            if (!\is_array($decoded)) {
                throw new \InvalidArgumentException('Custom CDE payload must decode to an array.');
            }
            $payload = $decoded;
        }

        // Allow either a list of items or a wrapped {cdes: [...]}.
        $items = $payload['cdes'] ?? $payload;
        if (!\is_array($items)) {
            $items = [];
        }

        $candidates = [];
        foreach ($items as $item) {
            if (\is_array($item)) {
                /** @var array<string, mixed> $item */
                $candidates[] = $this->map($item);
            }
        }

        $this->cdes = $candidates;

        return $candidates;
    }

    public function searchCdes(string $query): array
    {
        $needle = trim(strtolower($query));
        if ('' === $needle) {
            return $this->cdes;
        }

        return array_values(array_filter(
            $this->cdes,
            static fn (CdeCandidate $c): bool => str_contains(strtolower($c->label), $needle),
        ));
    }

    public function getCde(string $externalId): ?CdeCandidate
    {
        foreach ($this->cdes as $cde) {
            if ($cde->externalId === $externalId || $cde->localKey === $externalId) {
                return $cde;
            }
        }

        return null;
    }

    public function searchForms(string $query): array
    {
        return [];
    }

    public function getForm(string $externalId): ?FormCandidate
    {
        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function map(array $item): CdeCandidate
    {
        /** @var list<array<string, mixed>> $permissible */
        $permissible = $this->listOfArrays($item['permissibleValues'] ?? []);
        /** @var list<array<string, mixed>> $ontology */
        $ontology = $this->listOfArrays($item['ontologyMappings'] ?? []);

        $label = $this->str($item['label'] ?? $item['name'] ?? null) ?? 'Custom CDE';

        return new CdeCandidate(
            source: $this->str($item['source'] ?? null) ?? 'custom',
            label: $label,
            externalId: $this->str($item['externalId'] ?? null),
            externalUrl: $this->str($item['externalUrl'] ?? null),
            version: $this->str($item['version'] ?? null),
            definition: $this->str($item['definition'] ?? null),
            questionText: $this->str($item['questionText'] ?? null),
            datatype: $this->str($item['datatype'] ?? null),
            unitConstraint: $this->str($item['unitConstraint'] ?? null),
            status: $this->str($item['status'] ?? null),
            localKey: $this->str($item['localKey'] ?? null),
            permissibleValues: $permissible,
            ontologyMappings: $ontology,
            provenance: \is_array($item['provenance'] ?? null) ? $item['provenance'] : ['provider' => 'custom_json'],
            rawPayload: $item,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listOfArrays(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (\is_array($item)) {
                /** @var array<string, mixed> $item */
                $out[] = $item;
            }
        }

        return $out;
    }

    private function str(mixed $value): ?string
    {
        if (\is_string($value)) {
            return '' === $value ? null : $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
