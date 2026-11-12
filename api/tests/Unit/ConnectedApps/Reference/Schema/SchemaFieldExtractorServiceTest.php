<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Schema;

use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\Schema\SchemaField;
use App\ConnectedApps\Reference\Schema\SchemaFieldExtractorService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaFieldExtractorServiceTest extends TestCase
{
    private SchemaFieldExtractorService $service;

    protected function setUp(): void
    {
        $this->service = new SchemaFieldExtractorService();
    }

    #[Test]
    public function itExtractsCedarProperties(): void
    {
        $item = $this->makeResult(
            source: 'cedar',
            type: 'template',
            raw: [
                'properties' => [
                    '@context' => ['@type' => 'object'],  // should be skipped
                    'animalId' => [
                        'schema:name' => 'Animal ID',
                        'schema:description' => 'Unique animal identifier',
                        '_valueConstraints' => [
                            'ontologies' => [
                                ['uri' => 'http://purl.obolibrary.org/obo/NCBITaxon_10090'],
                            ],
                        ],
                    ],
                    'weight' => [
                        'schema:name' => 'Body Weight',
                        'schema:description' => '',
                        '_valueConstraints' => ['defaultValue' => 'decimal'],
                    ],
                ],
            ],
        );

        $fields = $this->service->extract($item);

        self::assertCount(2, $fields);

        $byLabel = $this->byLabel($fields);
        self::assertArrayHasKey('Animal ID', $byLabel);
        self::assertSame('Unique animal identifier', $byLabel['Animal ID']->description);
        self::assertSame(['http://purl.obolibrary.org/obo/NCBITaxon_10090'], $byLabel['Animal ID']->ontologyTermIris);
        self::assertNull($byLabel['Animal ID']->datatype);

        self::assertArrayHasKey('Body Weight', $byLabel);
        self::assertNull($byLabel['Body Weight']->description);
        self::assertSame('decimal', $byLabel['Body Weight']->datatype);
        self::assertSame([], $byLabel['Body Weight']->ontologyTermIris);
        self::assertStringContainsString('/properties/weight', $byLabel['Body Weight']->sourceRef);
    }

    #[Test]
    public function itSkipsCedarReservedKeys(): void
    {
        $item = $this->makeResult(
            source: 'cedar',
            type: 'template',
            raw: [
                'properties' => [
                    '@context' => [],
                    '@id' => [],
                    '@type' => [],
                    'schema:name' => [],
                    'schema:description' => [],
                    'pav:createdBy' => [],
                    'realField' => ['schema:name' => 'A real field'],
                ],
            ],
        );

        $fields = $this->service->extract($item);

        self::assertCount(1, $fields);
        self::assertSame('A real field', $fields[0]->label);
    }

    #[Test]
    public function itExtractsElabftwExtraFields(): void
    {
        $metadata = json_encode([
            'extra_fields' => [
                'mouse_weight' => [
                    'name' => 'Mouse Weight',
                    'description' => 'Body weight in grams',
                    'type' => 'number',
                    'units' => 'g',
                ],
                'protocol_step' => [
                    'name' => 'Protocol Step',
                    'description' => '',
                    'type' => 'text',
                    'units' => '',
                ],
            ],
        ]);

        $item = $this->makeResult(
            source: 'elabftw',
            type: 'template',
            raw: ['metadata' => $metadata],
        );

        $fields = $this->service->extract($item);

        self::assertCount(2, $fields);
        $byLabel = $this->byLabel($fields);

        self::assertArrayHasKey('Mouse Weight', $byLabel);
        self::assertSame('Body weight in grams', $byLabel['Mouse Weight']->description);
        self::assertSame('number', $byLabel['Mouse Weight']->datatype);
        self::assertSame('g', $byLabel['Mouse Weight']->unit);

        self::assertArrayHasKey('Protocol Step', $byLabel);
        self::assertNull($byLabel['Protocol Step']->description);
        self::assertNull($byLabel['Protocol Step']->unit);
        self::assertStringContainsString('/extra_fields/protocol_step', $byLabel['Protocol Step']->sourceRef);
    }

    #[Test]
    public function itExtractsElabftwMetadataAsArray(): void
    {
        $item = $this->makeResult(
            source: 'elabftw',
            type: 'template',
            raw: [
                'metadata' => [
                    'extra_fields' => [
                        'cage_id' => ['name' => 'Cage ID', 'type' => 'text'],
                    ],
                ],
            ],
        );

        $fields = $this->service->extract($item);

        self::assertCount(1, $fields);
        self::assertSame('Cage ID', $fields[0]->label);
    }

    #[Test]
    public function itExtractsGuidelineAsSingleField(): void
    {
        $item = $this->makeResult(
            source: 'guidelines_hub',
            type: 'guideline',
            title: 'Randomization of animals',
            description: 'Animals should be randomized to treatment groups.',
            raw: ['section' => 'Design'],
        );

        $fields = $this->service->extract($item);

        self::assertCount(1, $fields);
        self::assertSame('Randomization of animals', $fields[0]->label);
        self::assertSame('Animals should be randomized to treatment groups.', $fields[0]->description);
        self::assertSame('text', $fields[0]->datatype);
        self::assertNull($fields[0]->unit);
    }

    #[Test]
    public function itExtractsMnmsAsSingleField(): void
    {
        $item = $this->makeResult(
            source: 'mnms',
            type: 'measure',
            title: 'Grip Strength',
            description: 'Forelimb grip strength measurement',
            raw: ['type' => 'float', 'unit' => 'N'],
        );

        $fields = $this->service->extract($item);

        self::assertCount(1, $fields);
        self::assertSame('Grip Strength', $fields[0]->label);
        self::assertSame('float', $fields[0]->datatype);
        self::assertSame('N', $fields[0]->unit);
    }

    #[Test]
    public function itExtractsOsfQuestionsFromPages(): void
    {
        $item = $this->makeResult(
            source: 'osf',
            type: 'schema',
            raw: [
                'schema' => [
                    'pages' => [
                        [
                            'questions' => [
                                ['title' => 'Animal species', 'type' => 'choose'],
                                ['title' => 'Sample size', 'description' => 'Number of animals', 'type' => 'number'],
                                ['title' => '', 'type' => 'text'],  // empty title — skip
                            ],
                        ],
                    ],
                ],
            ],
        );

        $fields = $this->service->extract($item);

        self::assertCount(2, $fields);
        $byLabel = $this->byLabel($fields);
        self::assertArrayHasKey('Animal species', $byLabel);
        self::assertSame('choose', $byLabel['Animal species']->datatype);
        self::assertArrayHasKey('Sample size', $byLabel);
        self::assertSame('Number of animals', $byLabel['Sample size']->description);
    }

    #[Test]
    public function itExtractsNihCdeFormElements(): void
    {
        $item = $this->makeResult(
            source: 'nih_cde',
            type: 'template',
            raw: [
                'formElements' => [
                    [
                        'question' => [
                            'cde' => [
                                'designations' => [
                                    ['designation' => 'Animal ID Number'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'label' => 'Sex of animal',
                        'question' => ['cde' => ['designations' => []]],
                    ],
                    [
                        'question' => ['cde' => ['designations' => [['designation' => '']]]],
                    ],
                ],
            ],
        );

        $fields = $this->service->extract($item);

        // First element uses designation, second falls back to label, third is empty — skip
        self::assertCount(2, $fields);
        $byLabel = $this->byLabel($fields);
        self::assertArrayHasKey('Animal ID Number', $byLabel);
        self::assertArrayHasKey('Sex of animal', $byLabel);
    }

    #[Test]
    public function itSkipsNihCdeFormElementsWhenTypeIsNotTemplate(): void
    {
        $item = $this->makeResult(
            source: 'nih_cde',
            type: 'cde_element',
            raw: ['formElements' => [['question' => ['cde' => ['designations' => [['designation' => 'Some field']]]]]]],
        );

        $fields = $this->service->extract($item);

        self::assertSame([], $fields);
    }

    #[Test]
    public function itReturnsEmptyForUnknownSource(): void
    {
        $item = $this->makeResult(source: 'unknown_source', type: 'schema', raw: ['fields' => ['x']]);

        $fields = $this->service->extract($item);

        self::assertSame([], $fields);
    }

    #[Test]
    public function extractAllSkipsItemsWithNoFields(): void
    {
        $items = [
            $this->makeResult(source: 'unknown_source', type: 'schema', raw: []),
            $this->makeResult(
                source: 'mnms',
                type: 'measure',
                title: 'Weight',
                raw: [],
            ),
        ];

        $schemas = $this->service->extractAll($items);

        // Only the mnms item produces a field
        self::assertCount(1, $schemas);
        self::assertSame('mnms:measure:test-id', $schemas[0]['sourceRef']);
        self::assertCount(1, $schemas[0]['fields']);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function makeResult(
        string $source,
        string $type,
        array $raw = [],
        string $title = 'Test',
        ?string $description = null,
    ): ReferenceResult {
        return new ReferenceResult(
            id: $source . ':' . $type . ':test-id',
            source: $source,
            sourceName: ucfirst($source),
            type: $type,
            title: $title,
            description: $description,
            raw: $raw,
        );
    }

    /**
     * @param list<SchemaField> $fields
     * @return array<string, SchemaField>
     */
    private function byLabel(array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            $out[$field->label] = $field;
        }

        return $out;
    }
}
