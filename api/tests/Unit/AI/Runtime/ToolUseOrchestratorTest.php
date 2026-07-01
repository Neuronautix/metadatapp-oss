<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Runtime;

use App\AI\Gateway\ModelGatewayInterface;
use App\AI\Gateway\ModelRequest;
use App\AI\Gateway\ModelResponse;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Mcp\McpToolCallResult;
use App\AI\Mcp\ToolAccessPolicy;
use App\AI\Runtime\AgenticToolCatalog;
use App\AI\Runtime\AssistantTask;
use App\AI\Runtime\ToolDispatcherInterface;
use App\AI\Runtime\ToolUseOrchestrator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ToolUseOrchestratorTest extends TestCase
{
    #[Test]
    public function itRunsABoundedLoopDispatchingToolsThenReturningTheFinalAnswer(): void
    {
        $gateway = $this->gateway([
            $this->responseWithToolCalls([['name' => 'list_my_references', 'arguments' => []]]),
            $this->finalResponse('Here are your references.'),
        ]);
        $dispatcher = $this->recordingDispatcher();

        $result = $this->orchestrator($dispatcher)->run($gateway, $this->request());

        self::assertSame('Here are your references.', $result->finalResponse->output['content']);
        self::assertSame(2, $result->iterations);
        self::assertFalse($result->iterationCapReached);
        self::assertCount(1, $result->toolProvenance);
        self::assertSame([], $result->warnings);
        self::assertSame(['list_my_references'], $dispatcher->dispatched);
    }

    #[Test]
    public function itRefusesWriteToolsWhenWriteBackIsDisabled(): void
    {
        $gateway = $this->gateway([
            $this->responseWithToolCalls([['name' => 'import_reference', 'arguments' => ['referenceIds' => 'jax:measure:1']]]),
            $this->finalResponse('done'),
        ]);
        $dispatcher = $this->recordingDispatcher();

        $result = $this->orchestrator($dispatcher, writeBack: false)->run($gateway, $this->request());

        self::assertSame([], $dispatcher->dispatched, 'A write tool must not be dispatched when write-back is off.');
        self::assertCount(0, $result->toolProvenance);
        self::assertNotEmpty($result->warnings);
        self::assertStringContainsString('refused', $result->warnings[0]);
    }

    #[Test]
    public function itDispatchesWriteToolsWhenWriteBackIsEnabled(): void
    {
        $gateway = $this->gateway([
            $this->responseWithToolCalls([['name' => 'import_reference', 'arguments' => ['referenceIds' => 'jax:measure:1']]]),
            $this->finalResponse('imported'),
        ]);
        $dispatcher = $this->recordingDispatcher();

        $result = $this->orchestrator($dispatcher, writeBack: true)->run($gateway, $this->request());

        self::assertSame(['import_reference'], $dispatcher->dispatched);
        self::assertCount(1, $result->toolProvenance);
    }

    #[Test]
    public function itStopsAtTheIterationCapAndWarns(): void
    {
        // A gateway that never stops asking for tools.
        $gateway = new class implements ModelGatewayInterface {
            public function invoke(ModelRequest $request): ModelResponse
            {
                return new ModelResponse('test', 'm', 'v', ['tool_calls' => [['name' => 'list_my_references', 'arguments' => []]]]);
            }
        };

        $result = $this->orchestrator($this->recordingDispatcher(), maxIterations: 2)->run($gateway, $this->request());

        self::assertTrue($result->iterationCapReached);
        self::assertSame(2, $result->iterations);
        self::assertStringContainsString('2-iteration cap', implode(' ', $result->warnings));
    }

    #[Test]
    public function itSkipsUnknownToolsWithAWarningButFinishes(): void
    {
        $gateway = $this->gateway([
            $this->responseWithToolCalls([['name' => 'definitely_not_a_tool', 'arguments' => []]]),
            $this->finalResponse('ok'),
        ]);
        $dispatcher = $this->recordingDispatcher();

        $result = $this->orchestrator($dispatcher)->run($gateway, $this->request());

        self::assertSame([], $dispatcher->dispatched);
        self::assertCount(0, $result->toolProvenance);
        self::assertStringContainsString('not registered', implode(' ', $result->warnings));
        self::assertSame('ok', $result->finalResponse->output['content']);
    }

    #[Test]
    public function itAdvertisesToolsAndThreadsTheTranscriptToTheGateway(): void
    {
        $gateway = new class([
            new ModelResponse('test', 'm', 'v', ['tool_calls' => [['id' => 'tu_1', 'name' => 'list_my_references', 'arguments' => []]]]),
            new ModelResponse('test', 'm', 'v', ['content' => 'done']),
        ]) implements ModelGatewayInterface {
            /** @var list<array<string, mixed>> */
            public array $received = [];

            /** @param list<ModelResponse> $responses */
            public function __construct(private array $responses)
            {
            }

            public function invoke(ModelRequest $request): ModelResponse
            {
                $this->received[] = $request->input;

                return array_shift($this->responses) ?? throw new \LogicException('Gateway over-invoked.');
            }
        };

        $this->orchestrator($this->recordingDispatcher())->run($gateway, $this->request());

        // First call advertises the tool catalog and an empty transcript.
        $toolNames = array_column($gateway->received[0]['tools'], 'name');
        self::assertContains('list_my_references', $toolNames);
        self::assertContains('import_reference', $toolNames);
        self::assertSame([], $gateway->received[0]['toolTranscript']);

        // Second call replays the assistant tool_use turn + a matching tool_result.
        $transcript = $gateway->received[1]['toolTranscript'];
        self::assertCount(2, $transcript);
        self::assertSame('assistant', $transcript[0]['role']);
        self::assertSame('tu_1', $transcript[0]['toolUse'][0]['id']);
        self::assertSame('list_my_references', $transcript[0]['toolUse'][0]['name']);
        self::assertSame('tool', $transcript[1]['role']);
        self::assertSame('tu_1', $transcript[1]['toolResults'][0]['id']);
        self::assertTrue($transcript[1]['toolResults'][0]['success']);
    }

    /**
     * @param list<ModelResponse> $responses
     */
    private function gateway(array $responses): ModelGatewayInterface
    {
        return new class($responses) implements ModelGatewayInterface {
            /** @param list<ModelResponse> $responses */
            public function __construct(private array $responses)
            {
            }

            public function invoke(ModelRequest $request): ModelResponse
            {
                return array_shift($this->responses) ?? throw new \LogicException('Gateway invoked more times than expected.');
            }
        };
    }

    /**
     * @param list<array{name: string, arguments: array<string, mixed>}> $toolCalls
     */
    private function responseWithToolCalls(array $toolCalls): ModelResponse
    {
        return new ModelResponse('test', 'm', 'v', ['tool_calls' => $toolCalls]);
    }

    private function finalResponse(string $content): ModelResponse
    {
        return new ModelResponse('test', 'm', 'v', ['content' => $content]);
    }

    private function recordingDispatcher(): ToolDispatcherInterface
    {
        return new class implements ToolDispatcherInterface {
            /** @var list<string> */
            public array $dispatched = [];

            public function dispatch(string $toolName, array $arguments): McpToolCallResult
            {
                $this->dispatched[] = $toolName;

                return new McpToolCallResult($toolName, true, ['ok' => true]);
            }
        };
    }

    private function orchestrator(ToolDispatcherInterface $dispatcher, bool $writeBack = false, int $maxIterations = 5): ToolUseOrchestrator
    {
        $flags = new AiFeatureFlags(
            assistantEnabled: true,
            provider: 'test',
            defaultModel: 'm',
            enabledTasks: [AssistantTask::AGENTIC_CHAT],
            sandboxEnabled: false,
            humanApprovalRequired: true,
            writeActionsEnabled: $writeBack,
            agenticModeEnabled: true,
        );

        $registry = [
            new ToolAccessPolicy('list_my_references', true, false, false),
            new ToolAccessPolicy('import_reference', false, true, true),
        ];

        return new ToolUseOrchestrator($flags, $dispatcher, $registry, new AgenticToolCatalog(), new NullLogger(), $maxIterations);
    }

    private function request(): ModelRequest
    {
        return new ModelRequest('req-1', AssistantTask::AGENTIC_CHAT, 'assistant-agentic-chat.v1', 'ai.assistant.agentic-chat.v1', ['messages' => []]);
    }
}
