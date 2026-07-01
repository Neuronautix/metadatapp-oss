<?php

declare(strict_types=1);

namespace App\Crosswalk\Export;

use App\Entity\CanonicalForm;
use App\Entity\CommonDataElement;
use App\Entity\FieldCrosswalk;
use App\Entity\TemplateFormCrosswalk;
use App\Enum\MappingStatus;

/**
 * Produces enriched RO-Crate / JSON-LD from a crosswalk. Original ELN metadata is
 * NEVER mutated or overwritten — only ADD or ENRICH. Only ACCEPTED field
 * crosswalks contribute; REJECTED ones are excluded entirely.
 */
final class CrosswalkJsonLdExporter
{
    /**
     * Full enriched RO-Crate: deep copy of the original metadata with added nodes.
     *
     * @return array<string, mixed>
     */
    public function exportEnrichedRoCrate(TemplateFormCrosswalk $crosswalk): array
    {
        $template = $crosswalk->getExternalTemplate();
        $metadata = $template->getRoCrateMetadata() ?? ['@context' => 'https://w3id.org/ro/crate/1.1/context', '@graph' => []];

        // Deep copy so original nodes are never touched.
        $metadata = $this->deepCopy($metadata);

        $graph = $metadata['@graph'] ?? [];
        if (!\is_array($graph)) {
            $graph = [];
        }

        $existingIds = [];
        foreach ($graph as $node) {
            if (\is_array($node) && isset($node['@id']) && \is_string($node['@id'])) {
                $existingIds[$node['@id']] = true;
            }
        }

        foreach ($this->buildAddedNodes($crosswalk) as $node) {
            $id = $node['@id'];
            if (isset($existingIds[$id])) {
                continue; // never overwrite an original node
            }
            $graph[] = $node;
            $existingIds[$id] = true;
        }

        $metadata['@graph'] = array_values($graph);

        return $metadata;
    }

    /**
     * The JSON-LD graph fragment of only the added/enriched nodes.
     *
     * @return array<string, mixed>
     */
    public function exportJsonLd(TemplateFormCrosswalk $crosswalk): array
    {
        return [
            '@context' => 'https://w3id.org/ro/crate/1.1/context',
            '@graph' => $this->buildAddedNodes($crosswalk),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildAddedNodes(TemplateFormCrosswalk $crosswalk): array
    {
        $template = $crosswalk->getExternalTemplate();
        $canonicalForm = $crosswalk->getCanonicalForm();
        $formSlug = $this->slug($canonicalForm->getTitle());
        $domain = $canonicalForm->getDomain() ?? 'general';

        $fieldNodes = [];
        $cdeNodes = [];
        $cdeSeen = [];
        $partRefs = [];

        foreach ($crosswalk->getFieldCrosswalks() as $fieldCrosswalk) {
            if (MappingStatus::ACCEPTED !== $fieldCrosswalk->getMappingStatus()) {
                continue; // rejected/suggested excluded from export
            }
            $cde = $fieldCrosswalk->getCde();
            if (null === $cde) {
                continue;
            }

            $localKey = $cde->getLocalKey() ?? $this->slug($cde->getLabel());
            $cdeId = 'metadatapp:cde:' . $localKey;
            $fieldId = '#field-' . str_replace('_', '-', $localKey);

            $fieldNodes[] = $this->buildFieldNode($fieldCrosswalk, $cde, $cdeId, $fieldId, $template->getExternalUrl(), $formSlug);
            $partRefs[] = ['@id' => $fieldId];

            if (!isset($cdeSeen[$cdeId])) {
                $cdeNodes[] = $this->buildCdeNode($cde, $cdeId, $domain);
                $cdeSeen[$cdeId] = true;
            }
        }

        $crosswalkSlug = $this->slug($template->getTitle() . '-' . $canonicalForm->getTitle());
        $crosswalkNode = $this->buildCrosswalkNode($crosswalk, $crosswalkSlug, $formSlug, $partRefs);
        $formNode = $this->buildFormNode($canonicalForm, $formSlug);

        return array_merge([$crosswalkNode, $formNode], $cdeNodes, $fieldNodes);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFieldNode(
        FieldCrosswalk $fieldCrosswalk,
        CommonDataElement $cde,
        string $cdeId,
        string $fieldId,
        ?string $templateUrl,
        string $formSlug,
    ): array {
        $elnField = $fieldCrosswalk->getElnField();
        $value = $elnField?->getExampleValue();
        $unit = $elnField?->getUnit() ?? $cde->getUnitConstraint();
        $propertyId = $cde->getLocalKey() ? 'metadatapp:cde:' . $cde->getLocalKey() : ($fieldCrosswalk->getElnPropertyId() ?? $cdeId);

        $node = [
            '@id' => $fieldId,
            '@type' => 'PropertyValue',
            'name' => $cde->getLabel(),
            'propertyID' => $propertyId,
        ];

        if (null !== $value && '' !== $value) {
            $node['value'] = $this->coerceValue($value, $cde->getDatatype());
        }
        if (null !== $unit && '' !== $unit) {
            $node['unitCode'] = $unit;
        }
        $isBasedOn = $fieldCrosswalk->getElnJsonldPath() ?? $elnField?->getJsonldId() ?? $templateUrl;
        if (null !== $isBasedOn) {
            $node['isBasedOn'] = ['@id' => $isBasedOn];
        }
        $node['conformsTo'] = ['@id' => 'metadatapp:form:' . $formSlug];
        $node['subjectOf'] = ['@id' => $cdeId];

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCdeNode(CommonDataElement $cde, string $cdeId, string $domain): array
    {
        $node = [
            '@id' => $cdeId,
            '@type' => ['DefinedTerm', 'PropertyValueSpecification'],
            'name' => $cde->getLabel(),
        ];
        if (null !== $cde->getDefinition()) {
            $node['description'] = $cde->getDefinition();
        }
        $node['valueRequired'] = false;
        if (null !== $cde->getDatatype()) {
            $node['valuePattern'] = $cde->getDatatype();
        }
        if (null !== $cde->getUnitConstraint()) {
            $node['unitCode'] = $cde->getUnitConstraint();
        }
        if ([] !== $cde->getPermissibleValues()) {
            $node['valueOption'] = array_values(array_map(
                static fn (array $pv): mixed => $pv['value'] ?? null,
                array_filter($cde->getPermissibleValues(), 'is_array'),
            ));
        }
        if (null !== $cde->getExternalUrl()) {
            $node['url'] = $cde->getExternalUrl();
        }
        $node['inDefinedTermSet'] = ['@id' => 'metadatapp:cde-registry:' . $this->slug($domain)];

        return $node;
    }

    /**
     * @param list<array{'@id': string}> $partRefs
     *
     * @return array<string, mixed>
     */
    private function buildCrosswalkNode(TemplateFormCrosswalk $crosswalk, string $crosswalkSlug, string $formSlug, array $partRefs): array
    {
        $template = $crosswalk->getExternalTemplate();

        return [
            '@id' => 'metadatapp:crosswalk:' . $crosswalkSlug,
            '@type' => 'CreativeWork',
            'name' => \sprintf('Crosswalk between %s and %s', $template->getTitle(), $crosswalk->getCanonicalForm()->getTitle()),
            'source' => ['@id' => $template->getExternalUrl() ?? ('metadatapp:template:' . $this->slug($template->getTitle()))],
            'target' => ['@id' => 'metadatapp:form:' . $formSlug],
            'version' => $crosswalk->getVersion(),
            'mappingStatus' => $crosswalk->getMappingStatus()->value,
            'hasPart' => $partRefs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFormNode(CanonicalForm $form, string $formSlug): array
    {
        $node = [
            '@id' => 'metadatapp:form:' . $formSlug,
            '@type' => ['CreativeWork', 'DefinedTermSet'],
            'name' => $form->getTitle(),
            'version' => $form->getVersion(),
        ];
        if (null !== $form->getDescription()) {
            $node['description'] = $form->getDescription();
        }
        if (null !== $form->getDomain()) {
            $node['about'] = $form->getDomain();
        }

        return $node;
    }

    private function coerceValue(string $value, ?string $datatype): mixed
    {
        $d = strtolower((string) $datatype);
        if (str_contains($d, 'int')) {
            return is_numeric($value) ? (int) $value : $value;
        }
        if (str_contains($d, 'decimal') || str_contains($d, 'float') || str_contains($d, 'double') || str_contains($d, 'number') || str_contains($d, 'numeric')) {
            return is_numeric($value) ? (float) $value : $value;
        }
        if (str_contains($d, 'bool')) {
            return \in_array(strtolower($value), ['true', '1', 'yes'], true);
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $data
     *
     * @return array<int|string, mixed>
     */
    private function deepCopy(array $data): array
    {
        $decoded = json_decode(json_encode($data, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;

        return trim($slug, '-');
    }
}
