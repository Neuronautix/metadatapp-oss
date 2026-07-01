<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Standardization;

use App\AI\Gateway\ModelGatewayInterface;
use App\AI\Gateway\ModelGatewayRegistry;
use App\AI\Gateway\ModelRequest;
use App\AI\Gateway\ModelResponse;
use App\AI\Gateway\NullModelGateway;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Runtime\AssistantTaskMapper;
use App\ConnectedApps\Reference\Standardization\OntologyGroundingService;
use App\ConnectedApps\Reference\Standardization\ReferenceStandardizationService;
use App\CurateGpt\Client\CurateGptClientInterface;
use App\Lookup\ExternalLookupService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ReferenceStandardizationServiceTest extends TestCase
{
    #[Test]
    public function itAssemblesCrossSourceClustersFromTheGoldenSet(): void
    {
        $golden = $this->loadGolden();
        $response = $this->modelResponse(['structured_output' => ['clusters' => $golden['modelClusters']]]);
        // Lift the per-call cap so the whole golden set (all concepts) is assembled.
        $service = $this->service(enabled: true, response: $response, maxItems: \count($golden['items']));

        $result = $service->standardize($golden['items']);

        self::assertTrue($result->aiAvailable);
        self::assertCount($golden['expected']['clusterCount'], $result->clusters);

        // Every expected cross-source equivalence is reproduced exactly: perfect
        // recall + precision over the deterministic assembly of the model output.
        $byIri = [];
        foreach ($result->clusters as $cluster) {
            self::assertNotNull($cluster->canonicalTerm, 'AI clusters carry a canonical term.');
            $byIri[$cluster->canonicalTerm->iri] = $cluster->memberRefIds;
        }

        foreach ($golden['expected']['clusters'] as $iri => $expectedMembers) {
            self::assertArrayHasKey($iri, $byIri, \sprintf('Expected a cluster for %s.', $iri));
            sort($expectedMembers);
            $actual = $byIri[$iri];
            sort($actual);
            self::assertSame($expectedMembers, $actual);
        }
    }

    #[Test]
    public function itDropsMemberIdsThatWereNotSubmitted(): void
    {
        $items = [
            ['id' => 'jax_phenome:measure:1', 'type' => 'measure', 'title' => 'Blood glucose'],
        ];
        $response = $this->modelResponse(['structured_output' => ['clusters' => [[
            'clusterId' => 'glucose',
            'canonicalTerm' => ['iri' => 'http://purl.obolibrary.org/obo/VT_0000188', 'label' => 'blood glucose level', 'ontology' => 'vt', 'confidence' => 0.9],
            'memberRefIds' => ['jax_phenome:measure:1', 'hallucinated:measure:999'],
            'evidence' => ['x'],
        ]]]]);

        $result = $this->service(enabled: true, response: $response)->standardize($items);

        self::assertCount(1, $result->clusters);
        self::assertSame(['jax_phenome:measure:1'], $result->clusters[0]->memberRefIds);
    }

    #[Test]
    public function itFallsBackToHeuristicGroupingWhenTheAssistantIsDisabled(): void
    {
        $items = [
            ['id' => 'jax_phenome:measure:4', 'type' => 'measure', 'title' => 'Body weight'],
            ['id' => 'impc:measure:5', 'type' => 'measure', 'title' => 'Body weight'],
            ['id' => 'impc:measure:6', 'type' => 'measure', 'title' => 'Grip strength'],
        ];

        $result = $this->service(enabled: false)->standardize($items);

        self::assertFalse($result->aiAvailable);
        self::assertNotEmpty($result->warnings);
        // Identical titles collapse; the distinct one stays on its own.
        self::assertCount(2, $result->clusters);
        foreach ($result->clusters as $cluster) {
            self::assertNull($cluster->canonicalTerm);
        }
    }

    #[Test]
    public function itCapsTheNumberOfItemsAndWarns(): void
    {
        $items = [];
        for ($i = 0; $i < 9; ++$i) {
            $items[] = ['id' => 'src:measure:' . $i, 'type' => 'measure', 'title' => 'Trait ' . $i];
        }

        $result = $this->service(enabled: false)->standardize($items);

        self::assertNotEmpty($result->warnings);
        self::assertStringContainsString('first 8', $result->warnings[0]);
        $members = array_merge(...array_map(static fn ($c): array => $c->memberRefIds, $result->clusters));
        self::assertCount(8, $members);
    }

    #[Test]
    public function itHarmonizesAgreeingFieldsAndFlagsConflicts(): void
    {
        $items = [
            ['id' => 'jax:1', 'source' => 'jax_phenome', 'sourceName' => 'JAX', 'type' => 'measure', 'title' => 'Body weight', 'raw' => ['unit' => 'g', 'method' => 'scale']],
            ['id' => 'impc:2', 'source' => 'impc', 'sourceName' => 'IMPC', 'type' => 'measure', 'title' => 'Body weight', 'raw' => ['unit' => 'g', 'method' => 'balance']],
        ];

        $result = $this->service(enabled: false)->standardize($items);

        self::assertCount(1, $result->clusters, 'Same normalized title collapses to one cluster.');
        $fields = $this->byField($result->clusters[0]->harmonizedFields);

        self::assertSame('g', $fields['unit']->value);
        self::assertFalse($fields['unit']->conflict, 'Agreeing sources are not a conflict.');

        self::assertTrue($fields['method']->conflict, 'Disagreeing sources flag a conflict.');
        self::assertContains($fields['method']->value, ['scale', 'balance']);
        self::assertCount(2, $fields['method']->sourceValues, 'Both per-source values are preserved.');
    }

    #[Test]
    public function itAppliesModelFieldOverridesAndRationale(): void
    {
        $items = [
            ['id' => 'jax:1', 'source' => 'jax_phenome', 'sourceName' => 'JAX', 'type' => 'measure', 'title' => 'Body weight', 'raw' => ['method' => 'scale']],
            ['id' => 'impc:2', 'source' => 'impc', 'sourceName' => 'IMPC', 'type' => 'measure', 'title' => 'Body weight', 'raw' => ['method' => 'balance']],
        ];
        $response = $this->modelResponse(['structured_output' => ['clusters' => [[
            'clusterId' => 'bw',
            'canonicalTerm' => ['iri' => 'http://purl.obolibrary.org/obo/VT_0001259', 'label' => 'body weight', 'ontology' => 'vt', 'confidence' => 0.9],
            'memberRefIds' => ['jax:1', 'impc:2'],
            'evidence' => ['Same trait.'],
            'harmonizedFields' => [['field' => 'method', 'value' => 'weighing scale', 'rationale' => 'Canonical lab method.']],
        ]]]]);

        $result = $this->service(enabled: true, response: $response)->standardize($items);
        $fields = $this->byField($result->clusters[0]->harmonizedFields);

        self::assertSame('weighing scale', $fields['method']->value, 'Model value overrides the deterministic pick.');
        self::assertSame('Canonical lab method.', $fields['method']->rationale);
        self::assertTrue($fields['method']->conflict, 'Deterministic conflict flag is preserved under the override.');
        self::assertCount(2, $fields['method']->sourceValues);
    }

    /**
     * @param list<\App\ConnectedApps\Reference\Standardization\HarmonizedField> $harmonized
     *
     * @return array<string, \App\ConnectedApps\Reference\Standardization\HarmonizedField>
     */
    private function byField(array $harmonized): array
    {
        $out = [];
        foreach ($harmonized as $field) {
            $out[$field->field] = $field;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $output
     */
    private function modelResponse(array $output): ModelResponse
    {
        return new ModelResponse(
            provider: 'test',
            model: 'test-model.v1',
            outputSchemaVersion: 'ai.reference.standardize.v1',
            output: $output,
        );
    }

    private function service(bool $enabled, ?ModelResponse $response = null, int $maxItems = 8): ReferenceStandardizationService
    {
        $flags = new AiFeatureFlags(
            assistantEnabled: $enabled,
            provider: $enabled ? 'test' : 'null',
            defaultModel: 'test-model.v1',
            enabledTasks: ['standardize_references'],
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

        $lookup = new ExternalLookupService(new MockHttpClient(
            static fn (): MockResponse => new MockResponse((string) json_encode(['response' => ['docs' => []]])),
        ));
        $curateGpt = $this->createStub(CurateGptClientInterface::class);
        $curateGpt->method('search')->willReturn([]);
        $grounding = new OntologyGroundingService($lookup, $curateGpt, new NullLogger());

        return new ReferenceStandardizationService(
            $flags,
            new AssistantTaskMapper($flags),
            $registry,
            $grounding,
            new ArrayAdapter(),
            new NullLogger(),
            $maxItems,
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, modelClusters: list<array<string, mixed>>, expected: array{clusterCount: int, clusters: array<string, list<string>>}}
     */
    private function loadGolden(): array
    {
        $json = file_get_contents(__DIR__ . '/fixtures/standardization_golden.json');
        self::assertIsString($json);

        /** @var array{items: list<array<string, mixed>>, modelClusters: list<array<string, mixed>>, expected: array{clusterCount: int, clusters: array<string, list<string>>}} $data */
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }
}
