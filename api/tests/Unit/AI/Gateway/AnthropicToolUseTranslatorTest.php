<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Gateway;

use App\AI\Gateway\AnthropicToolUseTranslator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AnthropicToolUseTranslatorTest extends TestCase
{
    #[Test]
    public function itParsesTextAndToolUseBlocksFromAResponse(): void
    {
        $parsed = AnthropicToolUseTranslator::parseToolUse(['content' => [
            ['type' => 'text', 'text' => 'Let me look that up.'],
            ['type' => 'tool_use', 'id' => 'tu_9', 'name' => 'standardize_references', 'input' => ['referenceIds' => 'a,b']],
        ]]);

        self::assertSame('Let me look that up.', $parsed['content']);
        self::assertCount(1, $parsed['tool_calls']);
        self::assertSame('tu_9', $parsed['tool_calls'][0]['id']);
        self::assertSame('standardize_references', $parsed['tool_calls'][0]['name']);
        self::assertSame(['referenceIds' => 'a,b'], $parsed['tool_calls'][0]['arguments']);
    }

    #[Test]
    public function aTextOnlyResponseHasNoToolCalls(): void
    {
        $parsed = AnthropicToolUseTranslator::parseToolUse(['content' => [
            ['type' => 'text', 'text' => 'All done.'],
        ]]);

        self::assertSame('All done.', $parsed['content']);
        self::assertSame([], $parsed['tool_calls']);
    }

    #[Test]
    public function itReplaysTheChatAndTranscriptAsPairedAnthropicMessages(): void
    {
        $messages = AnthropicToolUseTranslator::messages(
            [['role' => 'user', 'content' => 'harmonize my refs'], ['role' => 'assistant', 'content' => '']],
            [
                ['role' => 'assistant', 'toolUse' => [['id' => 'tu_1', 'name' => 'list_my_references', 'arguments' => []]]],
                ['role' => 'tool', 'toolResults' => [['id' => 'tu_1', 'name' => 'list_my_references', 'success' => true, 'data' => ['count' => 2], 'error' => null]]],
            ],
        );

        // Empty assistant chat turn is dropped; conversation starts with the user.
        self::assertSame('user', $messages[0]['role']);
        self::assertSame('harmonize my refs', $messages[0]['content']);

        // Assistant tool_use turn.
        self::assertSame('assistant', $messages[1]['role']);
        self::assertSame('tool_use', $messages[1]['content'][0]['type']);
        self::assertSame('tu_1', $messages[1]['content'][0]['id']);

        // Matching tool_result turn (paired by id), carrying the JSON result.
        self::assertSame('user', $messages[2]['role']);
        self::assertSame('tool_result', $messages[2]['content'][0]['type']);
        self::assertSame('tu_1', $messages[2]['content'][0]['tool_use_id']);
        self::assertFalse($messages[2]['content'][0]['is_error']);
        self::assertStringContainsString('count', (string) $messages[2]['content'][0]['content']);
    }

    #[Test]
    public function aFailedToolResultIsMarkedAsAnError(): void
    {
        $messages = AnthropicToolUseTranslator::messages(
            [['role' => 'user', 'content' => 'do it']],
            [
                ['role' => 'assistant', 'toolUse' => [['id' => 'tu_x', 'name' => 'import_reference', 'arguments' => ['items' => []]]]],
                ['role' => 'tool', 'toolResults' => [['id' => 'tu_x', 'name' => 'import_reference', 'success' => false, 'data' => [], 'error' => 'refused']]],
            ],
        );

        $resultBlock = $messages[2]['content'][0];
        self::assertTrue($resultBlock['is_error']);
        self::assertStringContainsString('refused', (string) $resultBlock['content']);
    }
}
