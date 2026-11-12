<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crosswalk;

use App\AI\Gateway\ModelGatewayInterface;
use App\AI\Gateway\ModelGatewayRegistry;
use App\AI\Gateway\ModelRequest;
use App\AI\Gateway\ModelResponse;
use App\AI\Gateway\NullModelGateway;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Runtime\AssistantTaskMapper;
use App\Crosswalk\Cde\CdeCandidate;
use App\Crosswalk\Mapping\LlmMappingSuggestionRefiner;
use App\Crosswalk\Mapping\MappingSuggestion;
use App\Entity\ExtractedTemplateField;
use App\Enum\CrosswalkMappingType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class LlmMappingSuggestionRefinerTest extends TestCase
{
    #[Test]
    public function itReRanksAndAttachesEvidenceWhenAiIsAvailable(): void
    {
        $response = $this->modelResponse(['structured_output' => ['rankings' => [
            ['ref' => 1, 'confidence' => 0.91, 'evidence' => ['Body mass is the canonical biological term.']],
            ['ref' => 0, 'confidence' => 0.30, 'evidence' => []],
        ]]]);

        $refined = $this->refiner(enabled: true, response: $response)->refine($this->suggestions(), $this->field());

        self::assertSame('Body mass', $this->label($refined[0]));
        self::assertSame('Body weight', $this->label($refined[1]));
        self::assertEqualsWithDelta(0.91, $refined[0]->confidence, 0.0001);
        self::assertEqualsWithDelta(0.30, $refined[1]->confidence, 0.0001);
        self::assertContains('AI: Body mass is the canonical biological term.', $refined[0]->reasons);
        // The deterministic reasons are preserved alongside the AI evidence.
        self::assertContains('partial label token overlap', $refined[0]->reasons);
        self::assertContains('deterministic-reason', $refined[1]->reasons);
    }

    #[Test]
    public function itAppendsCandidatesTheModelOmitted(): void
    {
        $response = $this->modelResponse(['structured_output' => ['rankings' => [
            ['ref' => 0, 'confidence' => 0.8, 'evidence' => []],
        ]]]);

        $refined = $this->refiner(enabled: true, response: $response)->refine($this->suggestions(), $this->field());

        self::assertCount(2, $refined);
        self::assertSame('Body weight', $this->label($refined[0]));
        // The omitted candidate keeps its deterministic place at the end, never dropped.
        self::assertSame('Body mass', $this->label($refined[1]));
        self::assertEqualsWithDelta(0.4, $refined[1]->confidence, 0.0001);
    }

    #[Test]
    public function itReturnsDeterministicSuggestionsUnchangedWhenAiIsDisabled(): void
    {
        $suggestions = $this->suggestions();

        $refined = $this->refiner(enabled: false)->refine($suggestions, $this->field());

        self::assertSame($suggestions, $refined);
    }

    #[Test]
    public function itDegradesToDeterministicSuggestionsWhenTheGatewayErrors(): void
    {
        // enabled, but the gateway has no response configured and throws on invoke.
        $suggestions = $this->suggestions();

        $refined = $this->refiner(enabled: true, response: null)->refine($suggestions, $this->field());

        self::assertSame($suggestions, $refined);
    }

    #[Test]
    public function itLeavesASingleSuggestionUntouchedWithoutCallingTheModel(): void
    {
        $single = [$this->suggestions()[0]];

        // response:null would throw if the gateway were invoked — proving the short-circuit.
        $refined = $this->refiner(enabled: true, response: null)->refine($single, $this->field());

        self::assertSame($single, $refined);
    }

    private function field(): ExtractedTemplateField
    {
        return (new ExtractedTemplateField())->setLabel('Body weight')->setDatatype('decimal')->setUnit('g');
    }

    /**
     * @return list<MappingSuggestion>
     */
    private function suggestions(): array
    {
        return [
            new MappingSuggestion(
                cde: new CdeCandidate(source: 'metadatapp', label: 'Body weight', localKey: 'body_weight'),
                confidence: 0.5,
                reasons: ['deterministic-reason'],
                risks: [],
                mappingType: CrosswalkMappingType::CLOSE,
            ),
            new MappingSuggestion(
                cde: new CdeCandidate(source: 'metadatapp', label: 'Body mass', localKey: 'body_mass'),
                confidence: 0.4,
                reasons: ['partial label token overlap'],
                risks: [],
                mappingType: CrosswalkMappingType::RELATED,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $output
     */
    private function modelResponse(array $output): ModelResponse
    {
        return new ModelResponse(
            provider: 'test',
            model: 'test-model.v1',
            outputSchemaVersion: 'ai.crosswalk.mapping.v1',
            output: $output,
        );
    }

    private function refiner(bool $enabled, ?ModelResponse $response = null): LlmMappingSuggestionRefiner
    {
        $flags = new AiFeatureFlags(
            assistantEnabled: $enabled,
            provider: $enabled ? 'test' : 'null',
            defaultModel: 'test-model.v1',
            enabledTasks: ['refine_crosswalk_mapping'],
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

        return new LlmMappingSuggestionRefiner($flags, new AssistantTaskMapper($flags), $registry, new ArrayAdapter(), new NullLogger());
    }

    private function label(MappingSuggestion $suggestion): string
    {
        $cde = $suggestion->cde;

        return $cde instanceof CdeCandidate ? $cde->label : $cde->getLabel();
    }
}
