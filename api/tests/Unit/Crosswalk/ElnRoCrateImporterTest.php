<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crosswalk;

use App\Crosswalk\Import\ElnRoCrateImporter;
use App\Crosswalk\Import\ExtractedFieldData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class ElnRoCrateImporterTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../../Fixtures/eln';

    private function importer(): ElnRoCrateImporter
    {
        // No network is ever exercised by these tests; an empty MockHttpClient
        // guarantees a failure if one were attempted.
        return new ElnRoCrateImporter(new MockHttpClient());
    }

    #[Test]
    public function itParsesAValidElnZipAndExtractsRoCrateMetadata(): void
    {
        $bytes = file_get_contents(self::FIXTURE_DIR . '/sample.eln');
        self::assertNotFalse($bytes, 'Unable to read the sample .eln fixture.');

        $parsed = $this->importer()->parseBytes($bytes);

        // The full ro-crate-metadata.json graph is preserved verbatim.
        self::assertArrayHasKey('@graph', $parsed->metadataJson);
        self::assertSame('ELN.community demo sample record', $parsed->title);
        self::assertSame('1.0.0', $parsed->version);
        self::assertSame(
            'https://eln.community/record/019bff9f-df44-71a0-9d3b-11a62730d34c',
            $parsed->externalUrl,
        );
        self::assertSame(hash('sha256', $bytes), $parsed->fileHash);
        self::assertSame('Dataset', $parsed->rootDataset['@type'] ?? null);

        // Every walked node becomes an extracted field — nothing is discarded.
        self::assertNotEmpty($parsed->extractedFields);
        self::assertContainsOnlyInstancesOf(ExtractedFieldData::class, $parsed->extractedFields);
    }

    #[Test]
    public function itExtractsPropertyValueFields(): void
    {
        $parsed = $this->importer()->parseGraph($this->fixtureGraph());

        $byLabel = $this->indexByLabel($parsed->extractedFields);

        self::assertArrayHasKey('molecularWeight', $byLabel);
        $mw = $byLabel['molecularWeight'];
        self::assertSame('molecularWeight', $mw->propertyId);
        self::assertSame('128.12592', $mw->exampleValue);
        self::assertSame('g/mol', $mw->unit);
        self::assertSame('decimal', $mw->datatype);
        self::assertSame('#molecularWeight', $mw->jsonldId);
        self::assertNotNull($mw->jsonldPath);

        self::assertArrayHasKey('sampleIdentifier', $byLabel);
        $sample = $byLabel['sampleIdentifier'];
        self::assertSame('SMP-2026-0042', $sample->exampleValue);
        self::assertSame('string', $sample->datatype);
        self::assertNull($sample->unit);
    }

    #[Test]
    public function itExtractsDefinedTermFieldsWithOntologyTerms(): void
    {
        $parsed = $this->importer()->parseGraph($this->fixtureGraph());

        $byLabel = $this->indexByLabel($parsed->extractedFields);

        self::assertArrayHasKey('Mass spectrometry', $byLabel);
        $technique = $byLabel['Mass spectrometry'];

        self::assertNotEmpty($technique->ontologyTerms, 'DefinedTerm should capture ontology terms.');
        $iris = array_map(static fn (array $t): mixed => $t['iri'] ?? null, $technique->ontologyTerms);
        self::assertContains('http://purl.obolibrary.org/obo/CHMO_0000470', $iris);
    }

    #[Test]
    public function itPreservesTheRawNodeForEachExtractedField(): void
    {
        $parsed = $this->importer()->parseGraph($this->fixtureGraph());
        $byLabel = $this->indexByLabel($parsed->extractedFields);

        $mw = $byLabel['molecularWeight'];
        self::assertSame('#molecularWeight', $mw->rawNode['@id'] ?? null);
        self::assertSame('PropertyValue', $mw->rawNode['@type'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureGraph(): array
    {
        $json = file_get_contents(self::FIXTURE_DIR . '/ro-crate-metadata.json');
        self::assertNotFalse($json, 'Unable to read the ro-crate-metadata.json fixture.');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param list<ExtractedFieldData> $fields
     *
     * @return array<string, ExtractedFieldData>
     */
    private function indexByLabel(array $fields): array
    {
        $byLabel = [];
        foreach ($fields as $field) {
            $byLabel[$field->label] = $field;
        }

        return $byLabel;
    }
}
