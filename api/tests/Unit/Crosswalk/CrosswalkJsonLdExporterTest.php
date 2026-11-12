<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crosswalk;

use App\Crosswalk\Export\CrosswalkJsonLdExporter;
use App\Entity\CanonicalForm;
use App\Entity\CommonDataElement;
use App\Entity\ExternalTemplate;
use App\Entity\ExtractedTemplateField;
use App\Entity\FieldCrosswalk;
use App\Entity\TemplateFormCrosswalk;
use App\Enum\CdeSource;
use App\Enum\MappingStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CrosswalkJsonLdExporterTest extends TestCase
{
    /**
     * A known original Dataset node, asserted to survive the export untouched.
     *
     * @var array<string, mixed>
     */
    private const ORIGINAL_DATASET = [
        '@id' => './',
        '@type' => 'Dataset',
        'name' => 'Original ELN dataset',
        'description' => 'Must not be mutated by the exporter.',
        'variableMeasured' => [['@id' => '#molecularWeight']],
    ];

    #[Test]
    public function exportJsonLdContainsCdeFieldAndCrosswalkNodes(): void
    {
        $crosswalk = $this->buildCrosswalk(MappingStatus::ACCEPTED);

        $graph = (new CrosswalkJsonLdExporter())->exportJsonLd($crosswalk);

        self::assertSame('https://w3id.org/ro/crate/1.1/context', $graph['@context']);
        $nodes = $graph['@graph'];
        self::assertIsArray($nodes);

        $byId = $this->indexById($nodes);

        // Enriched PropertyValue field node.
        self::assertArrayHasKey('#field-molecular-weight', $byId);
        $fieldNode = $byId['#field-molecular-weight'];
        self::assertSame('PropertyValue', $fieldNode['@type']);
        self::assertSame('Molecular weight', $fieldNode['name']);
        self::assertSame('metadatapp:cde:molecular_weight', $fieldNode['propertyID']);
        self::assertSame('g/mol', $fieldNode['unitCode']);
        self::assertSame(['@id' => 'metadatapp:cde:molecular_weight'], $fieldNode['subjectOf']);

        // CDE DefinedTerm / PropertyValueSpecification node.
        self::assertArrayHasKey('metadatapp:cde:molecular_weight', $byId);
        $cdeNode = $byId['metadatapp:cde:molecular_weight'];
        self::assertSame(['DefinedTerm', 'PropertyValueSpecification'], $cdeNode['@type']);
        self::assertSame('Molecular weight', $cdeNode['name']);

        // First-class crosswalk CreativeWork node.
        $crosswalkNode = $this->findByType($nodes, 'CreativeWork');
        self::assertNotNull($crosswalkNode);
        self::assertStringStartsWith('metadatapp:crosswalk:', $crosswalkNode['@id']);
        self::assertSame('accepted', $crosswalkNode['mappingStatus']);
        self::assertContains(['@id' => '#field-molecular-weight'], $crosswalkNode['hasPart']);
    }

    #[Test]
    public function enrichedRoCratePreservesOriginalNodesVerbatim(): void
    {
        $crosswalk = $this->buildCrosswalk(MappingStatus::ACCEPTED);

        $enriched = (new CrosswalkJsonLdExporter())->exportEnrichedRoCrate($crosswalk);

        $byId = $this->indexById($enriched['@graph']);

        // The original dataset node is deep-equal to its pristine definition.
        self::assertArrayHasKey('./', $byId);
        self::assertSame(self::ORIGINAL_DATASET, $byId['./']);

        // The added CDE/field/crosswalk nodes are present alongside the original.
        self::assertArrayHasKey('#field-molecular-weight', $byId);
        self::assertArrayHasKey('metadatapp:cde:molecular_weight', $byId);
    }

    #[Test]
    public function rejectedFieldCrosswalksAreExcludedFromTheExport(): void
    {
        $crosswalk = $this->buildCrosswalk(MappingStatus::REJECTED);

        $graph = (new CrosswalkJsonLdExporter())->exportJsonLd($crosswalk);
        $byId = $this->indexById($graph['@graph']);

        self::assertArrayNotHasKey('#field-molecular-weight', $byId);
        self::assertArrayNotHasKey('metadatapp:cde:molecular_weight', $byId);
    }

    private function buildCrosswalk(MappingStatus $fieldStatus): TemplateFormCrosswalk
    {
        $template = (new ExternalTemplate())
            ->setTitle('Demo ELN template')
            ->setExternalUrl('https://eln.community/record/demo')
            ->setRoCrateMetadata([
                '@context' => 'https://w3id.org/ro/crate/1.1/context',
                '@graph' => [self::ORIGINAL_DATASET],
            ]);

        $elnField = (new ExtractedTemplateField())
            ->setLabel('molecularWeight')
            ->setExampleValue('128.12592')
            ->setUnit('g/mol')
            ->setJsonldId('#molecularWeight');
        $template->addExtractedField($elnField);

        $form = (new CanonicalForm())
            ->setTitle('Chemistry sample form')
            ->setDomain('chemistry');

        $cde = (new CommonDataElement())
            ->setSource(CdeSource::METADATAPP)
            ->setLabel('Molecular weight')
            ->setDefinition('The mass of one mole of a substance.')
            ->setDatatype('decimal')
            ->setUnitConstraint('g/mol')
            ->setLocalKey('molecular_weight');

        $fieldCrosswalk = (new FieldCrosswalk())
            ->setCde($cde)
            ->setElnField($elnField)
            ->setMappingStatus($fieldStatus);

        $crosswalk = (new TemplateFormCrosswalk())
            ->setExternalTemplate($template)
            ->setCanonicalForm($form)
            ->setMappingStatus(MappingStatus::ACCEPTED);
        $crosswalk->addFieldCrosswalk($fieldCrosswalk);

        return $crosswalk;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexById(array $nodes): array
    {
        $byId = [];
        foreach ($nodes as $node) {
            if (isset($node['@id']) && \is_string($node['@id'])) {
                $byId[$node['@id']] = $node;
            }
        }

        return $byId;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     *
     * @return array<string, mixed>|null
     */
    private function findByType(array $nodes, string $type): ?array
    {
        foreach ($nodes as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        return null;
    }
}
