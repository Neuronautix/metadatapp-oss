<?php

declare(strict_types=1);

namespace App\AI\Runtime;

use App\AI\Mcp\McpToolCallResult;

/**
 * Routes a tool call to the first registered {@see ToolDispatcherInterface} that
 * handles the named tool, so the agentic loop can compose several dispatchers
 * (Reference Hub tools + guideline-evidence tool) behind the single dispatcher
 * the {@see ToolUseOrchestrator} consumes.
 *
 * The guideline-evidence dispatcher is tried first (it claims only its own tool
 * via {@see GuidelineToolDispatcher::supports()}); everything else falls through
 * to the Reference dispatcher, preserving the previous default behaviour.
 */
final readonly class CompositeToolDispatcher implements ToolDispatcherInterface
{
    public function __construct(
        private GuidelineToolDispatcher $guidelineDispatcher,
        private ReferenceToolDispatcher $referenceDispatcher,
    ) {
    }

    public function dispatch(string $toolName, array $arguments): McpToolCallResult
    {
        if ($this->guidelineDispatcher->supports($toolName)) {
            return $this->guidelineDispatcher->dispatch($toolName, $arguments);
        }

        return $this->referenceDispatcher->dispatch($toolName, $arguments);
    }
}
