<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Gateway;

use App\AI\Gateway\MockModelGateway;
use App\AI\Gateway\ModelRequest;
use App\AI\Runtime\AssistantActionPlanner;
use App\AI\Runtime\AssistantTask;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The mock gateway must drive the agentic tool-use loop (not just chat), so the
 * harmonization flow works under AI_MODEL_PROVIDER=mock just like a real provider.
 */
final class MockModelGatewayAgenticTest extends TestCase
{
    #[Test]
    public function itIssuesTheFirstToolCallForAHarmonizeMessage(): void
    {
        $output = $this->gateway()->invoke($this->agentic('Standardize and harmonize my references', []))->output;

        self::assertSame('list_my_references', $output['tool_calls'][0]['name']);
        self::assertSame('', $output['content']);
    }

    #[Test]
    public function itStandardizesTheLibraryIdsReturnedByTheListTool(): void
    {
        $transcript = [
            ['role' => 'assistant', 'toolUse' => [['id' => 'mock-tu-1', 'name' => 'list_my_references', 'arguments' => []]]],
            ['role' => 'tool', 'toolResults' => [[
                'id' => 'mock-tu-1', 'name' => 'list_my_references', 'success' => true, 'error' => null,
                'data' => ['count' => 2, 'references' => [['referenceId' => 'jax:1'], ['referenceId' => 'impc:2']]],
            ]]],
        ];

        $output = $this->gateway()->invoke($this->agentic('harmonize my references', $transcript))->output;

        self::assertSame('standardize_references', $output['tool_calls'][0]['name']);
        self::assertSame('jax:1,impc:2', $output['tool_calls'][0]['arguments']['referenceIds']);
    }

    #[Test]
    public function itReturnsAFinalAnswerOnceTheToolsHaveRun(): void
    {
        $transcript = [
            ['role' => 'assistant', 'toolUse' => [['id' => 'mock-tu-1', 'name' => 'list_my_references', 'arguments' => []]]],
            ['role' => 'tool', 'toolResults' => [['id' => 'mock-tu-1', 'name' => 'list_my_references', 'success' => true, 'data' => ['references' => [['referenceId' => 'jax:1']]], 'error' => null]]],
            ['role' => 'assistant', 'toolUse' => [['id' => 'mock-tu-2', 'name' => 'standardize_references', 'arguments' => ['referenceIds' => 'jax:1']]]],
            ['role' => 'tool', 'toolResults' => [['id' => 'mock-tu-2', 'name' => 'standardize_references', 'success' => true, 'data' => ['clusters' => [['clusterId' => 'c1']]], 'error' => null]]],
        ];

        $output = $this->gateway()->invoke($this->agentic('harmonize my references', $transcript))->output;

        self::assertArrayNotHasKey('tool_calls', $output);
        self::assertStringContainsString('1 concept', $output['content']);
    }

    #[Test]
    public function itSearchesForAGenericQuery(): void
    {
        $output = $this->gateway()->invoke($this->agentic('find open field', []))->output;

        self::assertSame('search_reference_resources', $output['tool_calls'][0]['name']);
        self::assertNotSame('', $output['tool_calls'][0]['arguments']['query']);
    }

    private function gateway(): MockModelGateway
    {
        return new MockModelGateway(new AssistantActionPlanner());
    }

    /**
     * @param list<array<string, mixed>> $transcript
     */
    private function agentic(string $message, array $transcript): ModelRequest
    {
        return new ModelRequest(
            requestId: 'req-1',
            taskType: AssistantTask::AGENTIC_CHAT,
            promptVersion: 'assistant-agentic-chat.v1',
            outputSchemaVersion: 'ai.assistant.agentic-chat.v1',
            input: [
                'messages' => [['role' => 'user', 'content' => $message]],
                'tools' => [['name' => 'list_my_references']],
                'toolTranscript' => $transcript,
            ],
        );
    }
}
