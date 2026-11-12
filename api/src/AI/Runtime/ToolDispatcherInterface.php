<?php

declare(strict_types=1);

namespace App\AI\Runtime;

use App\AI\Mcp\McpToolCallResult;

/**
 * Dispatches a single authorized MCP tool call to its backend handler and returns
 * the result. The {@see ToolUseOrchestrator} owns authorization (read/write +
 * human-approval gating); a dispatcher only executes already-approved calls.
 */
interface ToolDispatcherInterface
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function dispatch(string $toolName, array $arguments): McpToolCallResult;
}
