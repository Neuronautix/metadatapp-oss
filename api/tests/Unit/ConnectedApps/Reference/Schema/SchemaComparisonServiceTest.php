<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Schema;

use App\AI\Gateway\ModelGatewayInterface;
use App\AI\Gateway\ModelGatewayRegistry;
use App\AI\Gateway\ModelRequest;
use App\AI\Gateway\ModelResponse;
use App\AI\Gateway\NullModelGateway;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Runtime\AssistantTask;
use App\AI\Runtime\AssistantTaskMapper;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\Schema\SchemaComparisonService;
use App\ConnectedApps\Reference\Schema\SchemaFieldExtractorService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class SchemaComparisonServiceTest extends TestCase
{
    #[Test]
    public function itFiltersOutNonSchemaItemsWithAWarning(): void
    {
        $items = [
            $this->referenceResult(type: 'template', source: 'nih_cde', title: 'Template A', raw: [
                'formElements' => [
                    ['question' => ['cde' => ['designations' => [['designation' => 'Animal ID']]]]],
                ],
            ]),
            $this->referenceResult(type: 'measure', source: 'mnms', title: 'Body Weight', raw: []),
            $this->referenceResult(type: 'strain', source: 'jax_phenome', title: 'C57BL/6J', raw: []),
        ];

        $result = $this->service(enabled: false)->compare($items);

        self::assertFalse($result->aiAvailable);
        self::assertNotEmpty($result->warnings);
        $allWarnings = implode(' ', $result->warnings);
        self::assertStringContainsString('non-schema', $allWarnings);
    }

    #[Test]
    public function itReturnsEmptyResultWithWarningForNoSchemaItems(): void
    {
        $items = [
            $this->referenceResult(type: 'strain', source: 'jax_phenome', title: 'C57BL/6J', raw: []),
            $this->referenceResult(type: 'measure', source: 'mnms', title: 'Body Weight', raw: []),
        ];

        $result = $this->service(enabled: false)->compare($items);

        self::assertFalse($result->aiAvailable);
        self::assertSame([], $result->fieldGroups);
        $allWarnings = implode(' ', $result->warnings);
        self::assertStringContainsString('No schema', $allWarnings);
    }

    #[Test]
    public function itRunsDeterministicComparisonWhenAiIsDisabled(): void
    {
        $items = [
            $this->referenceResult(type: 'guideline', source: 'guidelines_hub', title: 'Randomization', description: 'Animals should be randomized.', raw: []),
            $this->referenceResult(type: 'guideline', source: 'guidelines_hub', title: 'Randomization', description: 'Same thing.', raw: []),
            $this->referenceResult(type: 'guideline', source: 'guidelines_hub', title: 'Blinding', description: 'Experimenters should be blinded.', raw: []),
        ];

        $result = $this->service(enabled: false)->compare($items);

        self::assertFalse($result->aiAvailable);
        // "Randomization" appears in both items — deterministic grouping collapses them
        // "Blinding" is distinct
        self::assertGreaterThanOrEqual(1, \count($result->fieldGroups));
        // Find the Randomization group: it should have 2 members
        $randomizationGroup = null;
        foreach ($result->fieldGroups as $group) {
            if ($group->canonicalLabel === 'Randomization') {
                $randomizationGroup = $group;
                break;
            }
        }
        self::assertNotNull($randomizationGroup, 'Expected a field group for Randomization.');
        self::assertCount(2, $randomizationGroup->members);
    }

    #[Test]
    public function itGroupsIdenticalLabelsAsExactMatchInDeterministicMode(): void
    {
        $items = [
            $this->referenceResult(type: 'template', source: 'elabftw', title: 'Template', raw: [
                'metadata' => ['extra_fields' => [
                    'animal_id' => ['name' => 'Animal ID', 'type' => 'text'],
                ]],
            ]),
            $this->referenceResult(type: 'template', source: 'elabftw', title: 'Template 2', raw: [
                'metadata' => ['extra_fields' => [
                    'animal_id' => ['name' => 'Animal ID', 'type' => 'text'],
                ]],
            ]),
        ];

        $result = $this->service(enabled: false)->compare($items);

        self::assertFalse($result->aiAvailable);
        // Both items contribute an "Animal ID" field — should be grouped together
        $animalIdGroup = null;
        foreach ($result->fieldGroups as $group) {
            if ($group->canonicalLabel === 'Animal ID') {
                $animalIdGroup = $group;
                break;
            }
        }
        self::assertNotNull($animalIdGroup, 'Expected a field group for Animal ID.');
        self::assertGreaterThanOrEqual(2, \count($animalIdGroup->members));
        // The primary occurrence is exact, subsequent are close
        self::assertSame('exact', $animalIdGroup->members[0]->mappingType);
        self::assertSame('close', $animalIdGroup->members[1]->mappingType);
    }

    #[Test]
    public function itReturnsCachedResultOnSecondCall(): void
    {
        $items = [
            $this->referenceResult(type: 'guideline', source: 'guidelines_hub', title: 'Blinding', description: 'Blind the experimenter.', raw: []),
        ];

        $callCounter = new \stdClass();
        $callCounter->count = 0;
        $countingResponse = $this->modelResponse(['structured_output' => ['fieldGroups' => [
            [
                'groupId' => 'fg-1',
                'canonicalLabel' => 'Blinding',
                'canonicalDatatype' => null,
                'canonicalUnit' => null,
                'confidence' => 0.9,
                'members' => [
                    ['sourceRef' => 'guidelines_hub:guideline:test-id', 'mappingType' => 'exact', 'label' => 'Blinding', 'evidence' => 'Same concept.'],
                ],
                'conflicts' => [],
            ],
        ]]]);
        $countingGateway = new class($countingResponse, $callCounter) implements ModelGatewayInterface {
            public function __construct(private readonly ModelResponse $response, private readonly \stdClass $counter)
            {
            }

            public function invoke(ModelRequest $request): ModelResponse
            {
                ++$this->counter->count;

                return $this->response;
            }
        };

        $flags = new AiFeatureFlags(
            assistantEnabled: true,
            provider: 'test',
            defaultModel: 'test-model.v1',
            enabledTasks: [AssistantTask::COMPARE_SCHEMAS],
            sandboxEnabled: false,
            humanApprovalRequired: true,
            writeActionsEnabled: false,
        );
        $registry = new ModelGatewayRegistry(['test' => $countingGateway], new NullModelGateway(), 'test');
        $cache = new ArrayAdapter();
        $service = new SchemaComparisonService($flags, new AssistantTaskMapper($flags), $registry, new SchemaFieldExtractorService(), $cache, new NullLogger());

        $first = $service->compare($items);
        $callsAfterFirst = $callCounter->count;
        $second = $service->compare($items);

        self::assertTrue($first->aiAvailable);
        self::assertCount(1, $first->fieldGroups);
        // Second call: cache hit — gateway must not be invoked again
        self::assertSame($callsAfterFirst, $callCounter->count, 'Gateway must not be called again on cache hit.');
        self::assertSame($first->fieldGroups[0]->canonicalLabel, $second->fieldGroups[0]->canonicalLabel);
    }

    #[Test]
    public function itParsesAiFieldGroupsFromModelResponse(): void
    {
        $items = [
            $this->referenceResult(type: 'template', source: 'elabftw', title: 'Template', raw: [
                'metadata' => ['extra_fields' => [
                    'weight' => ['name' => 'Body Weight', 'type' => 'number', 'units' => 'g'],
                ]],
            ]),
        ];

        $response = $this->modelResponse(['structured_output' => ['fieldGroups' => [
            [
                'groupId' => 'fg-weight',
                'canonicalLabel' => 'Body Weight',
                'canonicalDatatype' => 'number',
                'canonicalUnit' => 'g',
                'confidence' => 0.95,
                'members' => [
                    [
                        'sourceRef' => 'elabftw:template:test-id/extra_fields/weight',
                        'mappingType' => 'exact',
                        'label' => 'Body Weight',
                        'evidence' => 'Direct match.',
                    ],
                ],
                'conflicts' => [],
            ],
        ]]]);

        $result = $this->service(enabled: true, response: $response)->compare($items);

        self::assertTrue($result->aiAvailable);
        self::assertCount(1, $result->fieldGroups);
        $group = $result->fieldGroups[0];
        self::assertSame('fg-weight', $group->groupId);
        self::assertSame('Body Weight', $group->canonicalLabel);
        self::assertSame('number', $group->canonicalDatatype);
        self::assertSame('g', $group->canonicalUnit);
        self::assertEqualsWithDelta(0.95, $group->confidence, 0.001);
        self::assertCount(1, $group->members);
        self::assertSame('exact', $group->members[0]->mappingType);
    }

    #[Test]
    public function itFallsBackToDeterministicWhenGatewayThrows(): void
    {
        $items = [
            $this->referenceResult(type: 'guideline', source: 'guidelines_hub', title: 'Blinding', description: null, raw: []),
        ];

        $throwingGateway = new class implements ModelGatewayInterface {
            public function invoke(ModelRequest $request): ModelResponse
            {
                throw new \RuntimeException('Gateway error');
            }
        };

        $flags = new AiFeatureFlags(
            assistantEnabled: true,
            provider: 'test',
            defaultModel: 'test-model.v1',
            enabledTasks: [AssistantTask::COMPARE_SCHEMAS],
            sandboxEnabled: false,
            humanApprovalRequired: true,
            writeActionsEnabled: false,
        );

        $registry = new ModelGatewayRegistry(['test' => $throwingGateway], new NullModelGateway(), 'test');
        $service = new SchemaComparisonService(
            $flags,
            new AssistantTaskMapper($flags),
            $registry,
            new SchemaFieldExtractorService(),
            new ArrayAdapter(),
            new NullLogger(),
        );

        $result = $service->compare($items);

        self::assertFalse($result->aiAvailable);
        self::assertNotEmpty($result->warnings);
        $allWarnings = implode(' ', $result->warnings);
        self::assertStringContainsString('error', strtolower($allWarnings));
    }

    /**
     * @param array<string, mixed> $output
     */
    private function modelResponse(array $output): ModelResponse
    {
        return new ModelResponse(
            provider: 'test',
            model: 'test-model.v1',
            outputSchemaVersion: 'ai.reference.compare-schemas.v1',
            output: $output,
        );
    }

    private function service(
        bool $enabled,
        ?ModelResponse $response = null,
        ?ArrayAdapter $cache = null,
        ?SchemaFieldExtractorService $extractor = null,
    ): SchemaComparisonService {
        $flags = new AiFeatureFlags(
            assistantEnabled: $enabled,
            provider: $enabled ? 'test' : 'null',
            defaultModel: 'test-model.v1',
            enabledTasks: [AssistantTask::COMPARE_SCHEMAS],
            sandboxEnabled: false,
            humanApprovalRequired: true,
            writeActionsEnabled: false,
        );

        $gateway = new class($response) implements ModelGatewayInterface {
            public function __construct(private readonly ?ModelResponse $response)
            {
            }

            public function invoke(ModelRequest $request): ModelResponse
            {
                return $this->response ?? throw new \LogicException('No response configured.');
            }
        };

        $registry = new ModelGatewayRegistry(['test' => $gateway], new NullModelGateway(), $enabled ? 'test' : 'null');

        return new SchemaComparisonService(
            $flags,
            new AssistantTaskMapper($flags),
            $registry,
            $extractor ?? new SchemaFieldExtractorService(),
            $cache ?? new ArrayAdapter(),
            new NullLogger(),
        );
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function referenceResult(
        string $type,
        string $source,
        string $title,
        array $raw,
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
}
