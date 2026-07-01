<?php

declare(strict_types=1);

namespace App\Tests\Unit\Guideline;

use App\AI\Gateway\ModelGatewayInterface;
use App\AI\Gateway\ModelGatewayRegistry;
use App\AI\Gateway\ModelRequest;
use App\AI\Gateway\ModelResponse;
use App\AI\Gateway\NullModelGateway;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Runtime\AssistantTask;
use App\AI\Runtime\AssistantTaskMapper;
use App\Guideline\Evidence\EvidenceBundle;
use App\Guideline\Evidence\EvidenceItem;
use App\Guideline\GuidelineAiFiller;
use App\Guideline\Schema\GuidelineTemplate;
use App\Guideline\Schema\RequiredMetadata;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The evidence-grounded filler must (a) put the provided evidence into the model
 * payload and surface it as citations, and (b) still hand back deterministic
 * suggestions when the gateway is the Null gateway (AI unavailable), so the
 * review queue is useful offline.
 */
final class GuidelineAiFillerEvidenceTest extends TestCase
{
    private function template(): GuidelineTemplate
    {
        return new GuidelineTemplate(
            id: 'arrive',
            name: 'ARRIVE',
            version: '2.0',
            requiredMetadata: [$this->field()],
        );
    }

    private function field(): RequiredMetadata
    {
        return new RequiredMetadata(
            id: 'species',
            arriveSection: '8',
            crosswalk: ['strain'],
            prompt: 'State the species used.',
        );
    }

    private function bundle(): EvidenceBundle
    {
        return new EvidenceBundle([
            new EvidenceItem('species', EvidenceItem::SOURCE_COMPUTED, 'Species', 'mouse', 'computed from 3 subjects', 1.0),
            new EvidenceItem(null, EvidenceItem::SOURCE_COMPUTED, 'Total subjects (n)', '3', 'computed from 3 subjects'),
        ]);
    }

    private function flags(bool $enabled): AiFeatureFlags
    {
        return new AiFeatureFlags(
            assistantEnabled: $enabled,
            provider: 'mock',
            defaultModel: 'mock-1',
            enabledTasks: $enabled ? [AssistantTask::DRAFT_GUIDELINE_FIELD] : [],
            sandboxEnabled: false,
            humanApprovalRequired: true,
            writeActionsEnabled: false,
        );
    }

    #[Test]
    public function itPutsEvidenceIntoThePayloadAndCitesItOnSuccess(): void
    {
        $captured = null;
        $gateway = new class($captured) implements ModelGatewayInterface {
            /** @param array<string, mixed>|null $captured */
            public function __construct(private mixed &$captured)
            {
            }

            public function invoke(ModelRequest $request): ModelResponse
            {
                $this->captured = $request->input;

                return new ModelResponse(
                    provider: 'mock',
                    model: 'mock-1',
                    outputSchemaVersion: 'ai.guideline.field.v1',
                    output: ['structured_output' => ['value' => 'Mouse (Mus musculus).', 'rationale' => 'Grounded in computed species fact.']],
                    available: true,
                );
            }
        };

        $registry = new ModelGatewayRegistry(['mock' => $gateway], new NullModelGateway(), 'mock');
        $filler = new GuidelineAiFiller($this->flags(true), new AssistantTaskMapper($this->flags(true)), $registry, new NullLogger());

        $draft = $filler->draft($this->template(), $this->field(), ['strain' => 'C57BL/6J'], $this->bundle());

        self::assertTrue($draft->available);
        self::assertSame('Mouse (Mus musculus).', $draft->value);

        // Evidence reached the model payload (forField puts the species-mapped
        // computed item first, ahead of the general total-subjects fact).
        self::assertIsArray($captured);
        self::assertArrayHasKey('evidence', $captured);
        self::assertNotEmpty($captured['evidence']);
        self::assertSame('computed', $captured['evidence'][0]['source']);
        self::assertSame('mouse', $captured['evidence'][0]['value']);
        self::assertArrayNotHasKey('field_id', $captured['evidence'][0], 'prompt payload uses the compact evidence shape');

        // The draft cites the evidence used.
        $array = $draft->toArray();
        self::assertNotEmpty($array['used_evidence']);
        self::assertSame('computed', $array['used_evidence'][0]['source']);
    }

    #[Test]
    public function itReturnsDeterministicSuggestionsWhenAiIsUnavailable(): void
    {
        // No configured gateway => hasConfiguredGateway() is false (AI unavailable).
        $registry = new ModelGatewayRegistry([], new NullModelGateway(), 'mock');
        $filler = new GuidelineAiFiller($this->flags(true), new AssistantTaskMapper($this->flags(true)), $registry, new NullLogger());

        $draft = $filler->draft($this->template(), $this->field(), [], $this->bundle());

        self::assertFalse($draft->available);
        self::assertSame('', $draft->value);

        $array = $draft->toArray();
        self::assertNotEmpty($array['suggestions'], 'deterministic suggestions are available even with no AI provider');
        // The species computed fact is offered as a one-click suggestion.
        self::assertSame('mouse', $array['suggestions'][0]['value']);
        self::assertSame('computed', $array['suggestions'][0]['source']);
    }

    #[Test]
    public function itDegradesWithSuggestionsWhenTheAssistantIsDisabled(): void
    {
        $registry = new ModelGatewayRegistry(['mock' => new NullModelGateway()], new NullModelGateway(), 'mock');
        $filler = new GuidelineAiFiller($this->flags(false), new AssistantTaskMapper($this->flags(false)), $registry, new NullLogger());

        $draft = $filler->draft($this->template(), $this->field(), [], $this->bundle());

        self::assertFalse($draft->available);
        self::assertNotEmpty($draft->toArray()['suggestions']);
    }
}
