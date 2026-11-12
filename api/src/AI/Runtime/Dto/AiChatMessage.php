<?php

declare(strict_types=1);

namespace App\AI\Runtime\Dto;

final readonly class AiChatMessage implements \JsonSerializable
{
    public function __construct(
        public string $role,
        public string $content,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $role = isset($payload['role']) && \is_string($payload['role']) ? trim($payload['role']) : '';
        $content = isset($payload['content']) && \is_string($payload['content']) ? trim($payload['content']) : '';

        if (!\in_array($role, ['assistant', 'user', 'system'], true)) {
            throw new \InvalidArgumentException('Chat messages must include a supported role.');
        }

        if ('' === $content) {
            throw new \InvalidArgumentException('Chat messages must include non-empty content.');
        }

        return new self($role, $content);
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
        ];
    }
}
