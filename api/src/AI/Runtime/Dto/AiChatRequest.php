<?php

declare(strict_types=1);

namespace App\AI\Runtime\Dto;

final readonly class AiChatRequest
{
    /**
     * @param list<AiChatMessage>  $messages
     * @param array<string, mixed> $context
     */
    public function __construct(
        public array $messages,
        public array $context = [],
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $messages = $payload['messages'] ?? null;
        if (!\is_array($messages) || [] === $messages) {
            throw new \InvalidArgumentException('Chat requests must include at least one message.');
        }

        $mappedMessages = array_map(static function (mixed $message): AiChatMessage {
            if (!\is_array($message)) {
                throw new \InvalidArgumentException('Chat messages must be objects.');
            }

            return AiChatMessage::fromArray($message);
        }, $messages);

        $context = $payload['context'] ?? [];
        if (!\is_array($context)) {
            throw new \InvalidArgumentException('Chat context must be an object.');
        }

        return new self(array_values($mappedMessages), $context);
    }
}
