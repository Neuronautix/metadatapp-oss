<?php

declare(strict_types=1);

namespace App\AI\Gateway;

/**
 * Translates between the provider-agnostic agentic loop ({@see \App\AI\Runtime\ToolUseOrchestrator})
 * and Anthropic's native tool-use Messages API.
 *
 * - {@see messages()} replays the chat plus the running tool transcript as
 *   Anthropic messages: each assistant turn becomes a `tool_use` content block and
 *   each tool turn becomes a matching `tool_result` block (paired by id), which is
 *   exactly what the API requires to continue a tool-use conversation.
 * - {@see parseToolUse()} reads the model's reply, returning the final text plus
 *   any `tool_use` blocks as provider-agnostic tool calls.
 *
 * Pure and stateless so it can be unit-tested without the HTTP layer.
 */
final class AnthropicToolUseTranslator
{
    /**
     * @param list<mixed> $baseMessages chat messages ({role, content} strings) — raw input
     * @param list<mixed> $transcript   running tool transcript turns — raw input
     *
     * @return list<array<string, mixed>> Anthropic messages
     */
    public static function messages(array $baseMessages, array $transcript): array
    {
        $messages = [];

        foreach ($baseMessages as $message) {
            if (!\is_array($message)) {
                continue;
            }
            $content = trim((string) ($message['content'] ?? ''));
            if ('' === $content) {
                continue;
            }
            $messages[] = [
                'role' => 'assistant' === ($message['role'] ?? null) ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        // Anthropic needs the conversation to start with a user message.
        if ([] === $messages || 'user' !== $messages[0]['role']) {
            array_unshift($messages, ['role' => 'user', 'content' => 'Help with the lab reference library.']);
        }

        foreach ($transcript as $turn) {
            if (!\is_array($turn)) {
                continue;
            }
            if ('assistant' === ($turn['role'] ?? null)) {
                $messages[] = ['role' => 'assistant', 'content' => self::toolUseBlocks($turn['toolUse'] ?? [])];
            } elseif ('tool' === ($turn['role'] ?? null)) {
                $messages[] = ['role' => 'user', 'content' => self::toolResultBlocks($turn['toolResults'] ?? [])];
            }
        }

        return $messages;
    }

    /**
     * @param array<string, mixed> $response Anthropic Messages API response
     *
     * @return array{content: string, tool_calls: list<array{id: string, name: string, arguments: array<string, mixed>}>}
     */
    public static function parseToolUse(array $response): array
    {
        $text = [];
        $toolCalls = [];

        foreach (($response['content'] ?? []) as $block) {
            if (!\is_array($block)) {
                continue;
            }
            $type = $block['type'] ?? null;
            if ('text' === $type && isset($block['text'])) {
                $text[] = (string) $block['text'];
            } elseif ('tool_use' === $type) {
                $name = trim((string) ($block['name'] ?? ''));
                if ('' === $name) {
                    continue;
                }
                $toolCalls[] = [
                    'id' => trim((string) ($block['id'] ?? '')),
                    'name' => $name,
                    'arguments' => \is_array($block['input'] ?? null) ? $block['input'] : [],
                ];
            }
        }

        return ['content' => trim(implode("\n", $text)), 'tool_calls' => $toolCalls];
    }

    /**
     * @param mixed $toolUse list of {id, name, arguments}
     *
     * @return list<array<string, mixed>>
     */
    private static function toolUseBlocks(mixed $toolUse): array
    {
        $blocks = [];
        foreach (\is_array($toolUse) ? $toolUse : [] as $call) {
            if (!\is_array($call)) {
                continue;
            }
            $arguments = \is_array($call['arguments'] ?? null) ? $call['arguments'] : [];
            $blocks[] = [
                'type' => 'tool_use',
                'id' => (string) ($call['id'] ?? ''),
                'name' => (string) ($call['name'] ?? ''),
                // Anthropic requires `input` to be a JSON object, not an array.
                'input' => [] === $arguments ? new \stdClass() : $arguments,
            ];
        }

        return $blocks;
    }

    /**
     * @param mixed $toolResults list of {id, success, data, error}
     *
     * @return list<array<string, mixed>>
     */
    private static function toolResultBlocks(mixed $toolResults): array
    {
        $blocks = [];
        foreach (\is_array($toolResults) ? $toolResults : [] as $result) {
            if (!\is_array($result)) {
                continue;
            }
            $success = (bool) ($result['success'] ?? false);
            $payload = $success
                ? ($result['data'] ?? [])
                : ['error' => (string) ($result['error'] ?? 'Tool call failed.')];

            $blocks[] = [
                'type' => 'tool_result',
                'tool_use_id' => (string) ($result['id'] ?? ''),
                'content' => json_encode($payload, \JSON_THROW_ON_ERROR),
                'is_error' => !$success,
            ];
        }

        return $blocks;
    }
}
