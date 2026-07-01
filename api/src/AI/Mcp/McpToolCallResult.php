<?php

declare(strict_types=1);

namespace App\AI\Mcp;

final readonly class McpToolCallResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $toolName,
        public bool $success,
        public array $data = [],
        public ?string $error = null,
        public int $callDurationMs = 0,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toProvenanceEntry(): array
    {
        $detail = $this->success
            ? \sprintf('MCP tool "%s" succeeded in %dms.', $this->toolName, $this->callDurationMs)
            : \sprintf(
                'MCP tool "%s" failed in %dms. %s',
                $this->toolName,
                $this->callDurationMs,
                null !== $this->error ? $this->error : 'No error details were provided.',
            );

        return [
            'label' => 'MCP tool call',
            'value' => $this->toolName,
            'detail' => $detail,
            'source' => 'mcp:' . $this->toolName,
            'tool' => $this->toolName,
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
            'callDurationMs' => $this->callDurationMs,
        ];
    }
}
